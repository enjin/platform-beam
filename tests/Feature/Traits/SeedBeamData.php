<?php

namespace Enjin\Platform\Beam\Tests\Feature\Traits;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamBatch;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

trait SeedBeamData
{
    /**
     * The beam.
     */
    protected Model $beam;

    /**
     * The beam claims.
     *
     * @var Collection
     */
    protected Collection $claims;

    /**
     * The beam batch.
     */
    protected Model $batch;

    /**
     * Seed beam data.
     */
    public function seedBeam(?int $claimsCount = null, bool $isClaimed = false, ?BeamType $type = null, array $beam = []): void
    {
        $states = [
            'collection_id' => $this->collection->id,
            ...(!$isClaimed ? ['wallet_public_key' => null, 'claimed_at' => null, 'state' => null] : ['state' => ClaimStatus::PENDING->name]),
            ...($type ? ['type' => $type->name] : []),
        ];

        $this->claims = BeamClaim::factory()
            ->count($claimsCount ?: random_int(1, 10))
            ->for(Beam::factory()->state(['collection_chain_id' => $this->collection->collection_chain_id, ...$beam]))
            ->create($states);

        $this->beam = Beam::find($this->claims[0]->beam_id);

        Cache::remember(BeamService::key($this->beam->code), 3600, fn () => count($this->claims));
    }

    /**
     * Claim all beam.
     */
    public function claimAllBeams(string $publicKey): void
    {
        $this->claims->pluck('type')->each(function ($type) use ($publicKey) {
            if ($this->batch = BeamBatch::create(['beam_type' => $type])) {
                $this->claims->each(fn ($claim) => $claim->forceFill([
                    'wallet_public_key' => $publicKey,
                    'claimed_at' => now(),
                    'beam_batch_id' => $this->batch->id,
                    'state' => ClaimStatus::PENDING->name,
                ])->save());
                Cache::forget(BeamService::key($this->beam->code));
            }
        });
    }

    /**
     * Truncate beam tables.
     */
    public function truncateBeamTables(): void
    {
        Schema::disableForeignKeyConstraints();
        BeamClaim::truncate();
        BeamScan::truncate();
        BeamBatch::truncate();
        Beam::truncate();
        Schema::enableForeignKeyConstraints();
    }
}
