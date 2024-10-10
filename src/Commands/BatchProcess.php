<?php

namespace Enjin\Platform\Beam\Commands;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Events\BeamBatchTransactionCreated;
use Enjin\Platform\Beam\Events\BeamClaimInProgress;
use Enjin\Platform\Beam\Models\BeamBatch;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BatchService;
use Enjin\Platform\Enums\Substrate\TokenMintCapType;
use Enjin\Platform\GraphQL\Schemas\Primary\Substrate\Mutations\BatchMintMutation;
use Enjin\Platform\Models\Token;
use Enjin\Platform\Services\Blockchain\Implementations\Substrate;
use Enjin\Platform\Services\Database\TransactionService;
use Enjin\Platform\Services\Serialization\Interfaces\SerializationServiceInterface;
use Enjin\Platform\Support\Account;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BatchProcess extends Command
{
    /**
     * The signing account resolver.
     */
    public static $signingAccountResolver;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:process-beam-claims {--T|test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending beam transactions.';

    /**
     * The batch service.
     */
    protected BatchService $batch;

    /**
     * The Substrate service.
     */
    protected Substrate $substrate;

    /**
     * The Serialization service.
     */
    protected SerializationServiceInterface $serialize;

    /**
     * The Transaction service.
     */
    protected TransactionService $transaction;

    /**
     * Token Created Cache.
     *
     * @var array
     */
    protected $tokenCreatedCache = [];

    /**
     * Execute the console command.
     */
    public function handle(
        BatchService $batch,
        Substrate $substrate,
        SerializationServiceInterface $serialize,
        TransactionService $transaction
    ): int {
        $this->batch = $batch;
        $this->substrate = $substrate;
        $this->serialize = $serialize;
        $this->transaction = $transaction;

        $this->info('================ Starting Batch Process  ================');

        while (true) {
            $start = microtime(true);

            $this->process();

            $finished = number_format(microtime(true) - $start, 2);
            $this->info("Took {$finished} seconds.");
            $this->info('');

            sleep(config('enjin-platform-beam.batch_process.polling'));
            if ($this->option('test')) {
                break;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Updates the claims claim status.
     */
    protected function updateStatus(Collection $claims, array $reassignedClaims = []): void
    {
        BeamClaim::whereIn('id', $claims->pluck('id')->diff($reassignedClaims))->update(['state' => ClaimStatus::IN_PROGRESS->name]);
        foreach ($claims as $claim) {
            event(new BeamClaimInProgress($claim->toArray()));
        }
    }

    /**
     * Process the claims in batch.
     */
    protected function process(): void
    {
        $total = $this->batch->completeExpiredBatches(config('enjin-platform-beam.batch_process.time_duration'));
        $this->info("Total batch completed: {$total}");

        $transferedCount = $this->processBatch(BeamType::TRANSFER_TOKEN);
        $this->info('Total transfers completed: ' . $transferedCount);

        $mintedCount = $this->processBatch(BeamType::MINT_ON_DEMAND);
        $this->info('Total mints completed: ' . $mintedCount);
    }

    /**
     * Process a specific beam type in batch.
     */
    protected function processBatch(BeamType $type): int
    {
        $batches = $this->batch->getBatchesForProcessing($type)->groupBy('beam_batch_id');
        if (!$batches->isEmpty()) {
            $batches->each(function ($claims, $batchId) use ($type) {
                $params = [];
                $createdTokens = [];
                $reassignedClaims = [];
                $claims->each(function ($claim) use (&$params, $type, &$createdTokens, &$reassignedClaims) {
                    $collectionId = Arr::get($claim, 'beam.collection_chain_id');
                    if (!isset($params[$collectionId])) {
                        $params[$collectionId] = [
                            'collectionId' => $collectionId,
                            'recipients' => [],
                        ];
                    }

                    $params[$collectionId]['beamId'] = $claim->beam_id;

                    if ($type == BeamType::TRANSFER_TOKEN) {
                        $params[$collectionId]['recipients'][] = [
                            'accountId' => $claim->wallet_public_key,
                            'params' => $this->substrate->getTransferParams([
                                'tokenId' => ['integer' => $claim->token_chain_id],
                                'amount' => $claim->quantity,
                                'keepAlive' => false,
                                'source' => match (true) {
                                    !empty($claim->beam?->source) => SS58Address::getPublicKey($claim->beam->source),
                                    Account::daemonPublicKey() !== $claim->collection->owner->public_key => $claim->collection->owner->public_key,
                                    default => null,
                                },
                            ])->toEncodable(),
                        ];
                    } else {
                        $key = $claim->token_chain_id . '|' . $claim->collection_id;
                        if (isset($createdTokens[$key])) {
                            // Succeeding claims should be minted. Reassigning claims to the next batch
                            $claim->update(['beam_batch_id' => $batchId = $this->batch->getNextBatchId($type, $collectionId)]);
                            $this->info("Reassigning claim ID({$claim->id}) to the next batch:{$batchId}");
                            $reassignedClaims[] = $claim->id;

                            return;
                        }

                        if (!isset($this->tokenCreatedCache[$key])) {
                            $this->tokenCreatedCache[$key] = Token::where(['token_chain_id' => $claim->token_chain_id, 'collection_id' => $claim->collection_id])->exists();
                        }

                        $params[$collectionId]['recipients'][] = [
                            'accountId' => $claim->wallet_public_key,
                            'params' => $this->substrate->getMintOrCreateParams([
                                'tokenId' => ['integer' => $claim->token_chain_id],
                                'initialSupply' => $this->tokenCreatedCache[$key] ? null : $claim->quantity,
                                'amount' => $this->tokenCreatedCache[$key] ? $claim->quantity : null,
                                'cap' => [
                                    'type' => $claim->quantity == 1 || $claim->collection?->force_single_mint ? TokenMintCapType::SINGLE_MINT->name : TokenMintCapType::INFINITE->name,
                                    'amount' => null,
                                ],
                                'listingForbidden' => false,
                                'behaviour' => null,
                                'unitPrice' => config('enjin-platform-beam.unit_price'),
                                'attributes' => $claim->attributes ?: [],
                            ]),
                        ];

                        if (!$this->tokenCreatedCache[$key]) {
                            $this->tokenCreatedCache[$key] = true;
                            $createdTokens[$key] = true;
                        }
                    }
                });

                $method = BeamType::MINT_ON_DEMAND == $type ? 'BatchMint' : 'BatchTransfer';
                foreach ($params as $param) {
                    // TODO: With the v1010 upgrade we run into a bug with the php-scale-codec lib where it cannot
                    //  Encode the transaction with `0x` the solution here is to use Batch and within each call append the 0's
                    if ($method === 'BatchMint') {
                        $encodedData = $this->serialize->encode('Batch', BatchMintMutation::getEncodableParams(
                            collectionId: $param['collectionId'],
                            recipients: $param['recipients'],
                            continueOnFailure: true
                        ));
                    } else {
                        $encodedData = $this->serialize->encode(isRunningLatest() ? $method . 'V1010' : $method, [
                            'collectionId' => $param['collectionId'],
                            'recipients' => $param['recipients'],
                        ]);
                    }

                    $transaction = $this->transaction->store([
                        'method' => $method,
                        'encoded_data' => $encodedData,
                        'idempotency_key' => Str::uuid()->toString(),
                    ]);
                    BeamBatch::where('id', $batchId)->update(['transaction_id' => $transaction->id]);
                    BeamBatchTransactionCreated::safeBroadcast($param['beamId'], $param['collectionId'], $transaction->id);
                }

                $this->updateStatus($claims, $reassignedClaims);
            });
        }

        return $batches->count();
    }

    /**
     * Resolve the signing account for the given collection ID.
     */
    protected function resolveSigningAccount(string $beamId): ?Model
    {
        if (static::$signingAccountResolver) {
            return call_user_func(static::$signingAccountResolver, $beamId);
        }

        return null;
    }
}
