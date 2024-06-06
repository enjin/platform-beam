<?php

namespace Enjin\Platform\Beam\Services;

use Carbon\Carbon;
use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Enums\PlatformBeamCache;
use Enjin\Platform\Beam\Exceptions\BeamException;
use Enjin\Platform\Beam\Models\BeamBatch;
use Enjin\Platform\Beam\Models\BeamClaim;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BatchService
{
    /**
     * The batch threshold.
     */
    protected int $threshold = 0;

    /**
     * Create instance.
     */
    public function __construct()
    {
        if (! $this->threshold = config('enjin-platform-beam.batch_process.threshold')) {
            throw new BeamException(__('enjin-platform-beam::error.threshold_not_set'));
        }
    }

    /**
     * Get next batch ID.
     */
    public function getNextBatchId(BeamType $type, string $collectionid): int
    {
        $batch = BeamBatch::firstOrNew([
            'completed_at' => null,
            'beam_type' => $type->name,
            'collection_chain_id' => $collectionid,
        ]);
        if (! $batch->exists) {
            $batch->save();
        }

        $count = Cache::get(
            $key = $this->key($batch->id),
            $this->claimCountResolver($batch)
        );

        if ($count < $this->threshold) {
            Cache::increment($key);
        }

        if ($count + 1 >= $this->threshold) {
            $batch->fill(['completed_at' => now()])->save();
            Cache::forget($key);
        }

        return $batch->id;
    }

    /**
     * Get batches for processing.
     */
    public function getBatchesForProcessing(BeamType $type): Collection
    {
        return BeamClaim::select(
            'id',
            'token_chain_id',
            'beam_batch_id',
            'wallet_public_key',
            'beam_id',
            'quantity',
            'collection_id',
            'attributes',
            'idempotency_key',
            'code as identifierCode',
        )->where('state', ClaimStatus::PENDING)
            ->with(['beam', 'collection.owner'])
            ->whereHas('collection')
            ->whereHas(
                'batch',
                fn ($query) => $query->whereNotNull('completed_at')
                    ->whereNull('processed_at')
                    ->whereBeamType($type->name)
            )->get();
    }

    /**
     * Complete expired batches.
     */
    public function completeExpiredBatches(?int $minutes): int
    {
        return BeamBatch::when(
            $minutes,
            fn ($query) => $query->where('created_at', '<=', Carbon::now()->addMinutes($minutes))
        )->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }

    /**
     * The claim count resolver.
     */
    public function claimCountResolver(Model $batch): Closure
    {
        return function () use ($batch) {
            Cache::forever(
                $this->key($batch->id),
                $count = BeamClaim::whereBeamBatchId($batch->id)
                    ->whereHas('beam', fn ($query) => $query->whereType($batch->beam_type))
                    ->count()
            );

            return $count;
        };
    }

    /**
     * Generate cache key.
     */
    public function key(string $name): string
    {
        return PlatformBeamCache::BATCH_PROCESS->key($name);
    }
}
