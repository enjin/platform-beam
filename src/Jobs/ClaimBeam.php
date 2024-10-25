<?php

namespace Enjin\Platform\Beam\Jobs;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Enums\PlatformBeamCache;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Services\BatchService;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Services\Database\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public function __construct(protected ?array $data) {}

    /**
     * Execute the job.
     */
    public function handle(BatchService $batch, WalletService $wallet): void
    {
        if ($data = $this->data) {
            $lock = Cache::lock(PlatformBeamCache::CLAIM_BEAM_JOB->key($data['beam']['id']), 5);

            try {
                $lock->block(5);
                if ($claim = $this->claimQuery($data)->first()) {
                    DB::beginTransaction();
                    $wallet->firstOrStore(['public_key' => $data['wallet_public_key']]);
                    $claim->forceFill($this->buildBeamClaimAttributes($batch, $claim))->save();

                    // Delete scan after claim is set up so the signed data can't be used to claim again.
                    BeamScan::firstWhere(['wallet_public_key' => $data['wallet_public_key'], 'beam_id' => $data['beam']['id']])?->delete();

                    DB::commit();

                    Log::info('ClaimBeamJob: Claim assigned.', $claim->toArray());
                } else {
                    Cache::put(BeamService::key(Arr::get($data, 'beam.code')), 0);
                    Log::info('ClaimBeamJob: No claim available, setting remaining count to 0', $data);
                }
            } catch (LockTimeoutException) {
                Log::info('ClaimBeamJob: Cannot obtain lock, retrying', $data);
                $this->release(1);
            } catch (Throwable $e) {
                DB::rollBack();

                Log::error('ClaimBeamJob: Claim error, message:' . $e->getMessage(), $data);

                throw $e;
            } finally {
                $lock?->release();
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        if ($data = $this->data) {
            if ($this->claimQuery($data)->count() > 0) {
                // Idempotency key prevents incrementing cache on same claim request even with manual retry on horizon
                $key = Arr::get($data, 'idempotency_key');
                if (! Cache::get(PlatformBeamCache::IDEMPOTENCY_KEY->key($key))) {
                    Cache::forever($key, true);
                    Cache::increment(BeamService::key(Arr::get($data, 'beam.code')));
                    Log::info('ClaimBeamJob: Job failed, incrementing remaining count to 1', $data);
                }
            } else {
                Log::info('ClaimBeamJob: Job failed, no more claims avaialble setting count to 0', $data);
                Cache::put(BeamService::key(Arr::get($data, 'beam.code')), 0);
            }
        }
    }

    /**
     * Get the claim query.
     */
    protected function claimQuery(array $data): Builder
    {
        $collection = Collection::where('collection_chain_id', Arr::get($data, 'beam.collection_chain_id'))
            ->first(['id', 'collection_chain_id', 'is_frozen']);

        return BeamClaim::where('beam_id', $data['beam']['id'])
            ->claimable()
            ->when($data['code'], fn ($query) => $query->withSingleUseCode($data['code']))
            ->when($collection?->is_frozen, fn ($query) => $query->where('type', BeamType::MINT_ON_DEMAND->name))
            ->unless($data['code'], fn ($query) => $query->inRandomOrder());
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
            ...Arr::only($this->data, [
                'wallet_public_key',
                'claimed_at',
                'state',
                'ip_address',
                'idempotency_key',
            ]),
            'beam_batch_id' => $batchService->getNextBatchId(
                BeamType::getEnumCase($claim->type),
                $claim->beam->collection_chain_id
            ),
        ];
    }
}
