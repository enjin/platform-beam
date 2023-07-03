<?php

namespace Enjin\Platform\Beam\Jobs;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Services\BatchService;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Services\Database\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ClaimBeam implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected ?array $data)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(BatchService $batch, WalletService $wallet): void
    {
        if ($data = $this->data) {
            try {
                $beam = Beam::find($this->data['beam']['id']);
                $claim = null;
                if ($beam->probabilities) {
                    $claim = $this->computeClaim($beam);
                }
                if (!$claim) {
                    $claim = BeamClaim::where('beam_id', $data['beam']['id'])
                        ->claimable()
                        ->when($data['code'], fn ($query) => $query->withSingleUseCode($data['code']))
                        ->unless($data['code'], fn ($query) => $query->inRandomOrder())
                        ->first();
                }

                if ($claim) {
                    DB::beginTransaction();
                    $wallet->firstOrStore(['public_key' => $data['wallet_public_key']]);
                    $claim->forceFill($this->buildBeamClaimAttributes($batch, $claim))->save();

                    // Delete scan after claim is set up so the signed data can't be used to claim again.
                    BeamScan::firstWhere(['wallet_public_key' => $data['wallet_public_key'], 'beam_id' => $data['beam']['id']])?->delete();

                    DB::commit();
                }
            } catch (Throwable $e) {
                DB::rollBack();

                throw $e;
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Idempotency key prevents incrementing cache on same claim request even with manual retry on horizon
        if (!Cache::get($key = Arr::get($this->data, 'idempotency_key'))) {
            Cache::forever($key, true);
            Cache::increment(BeamService::key(Arr::get($this->data, 'beam.code')));
        }
    }

    /**
     * Compute the claim base from its probabilities.
     */
    protected function computeClaim(Model $beam): ?Model
    {
        $tryLimit = BeamClaim::where('beam_id', $beam->id)->claimable()->count() + count($beam->chances);
        $tries = 0;
        $claim = null;
        do {
            $rand = random_int(1, 100);
            foreach ($beam->chances as $tokenId => $chance) {
                if ($rand <= $chance) {
                    if ($tokenId === 'nft') {
                        $claim = BeamClaim::where('beam_id', $beam->id)
                            ->claimable()
                            ->nft($beam->collection_chain_id)
                            ->first();
                    } else {
                        $claim = BeamClaim::where('beam_id', $beam->id)
                            ->claimable()
                            ->where('token_chain_id', $tokenId)
                            ->first();
                    }
                }
            }
            $tries++;
        } while (is_null($claim) && $tries <= $tryLimit);

        return $claim;
    }

    /**
     * Build the claim attributes.
     */
    protected function buildBeamClaimAttributes(BatchService $batchService, Model $claim): array
    {
        return array_merge(
            $this->buildRequiredClaimAttributes($batchService, $claim),
            $this->data['extras'] ?? []
        );
    }

    /**
     * Build the required fields for claim attributes.
     */
    protected function buildRequiredClaimAttributes(BatchService $batchService, Model $claim): array
    {
        return [
            ...Arr::only($this->data, ['wallet_public_key', 'claimed_at', 'state', 'ip_address']),
            'beam_batch_id' => $batchService->getNextBatchId(
                BeamType::getEnumCase($claim->type),
                $claim->beam->collection_chain_id
            ),
        ];
    }
}
