<?php

namespace Enjin\Platform\Beam\Jobs;

use Enjin\Platform\Beam\Events\CreateBeamClaimsCompleted;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CreateBeamClaims implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected ?Collection $chunk)
    {
        $this->onQueue(config('enjin-platform-beam.queue'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->chunk) {
            $timestamp = ['created_at' => $now = now(), 'updated_at' => $now];
            BeamClaim::insert($this->chunk->map(fn ($claim) => array_merge($claim, $timestamp))->toArray());

            $this->chunk
                ->pluck('beam_id', 'beam_id')
                ->each(function ($beamId) {
                    if ($beam = Beam::select('id', 'code')->find($beamId)) {
                        CreateBeamClaimsCompleted::safeBroadcast(event: $beam->toArray());
                    }
                });

            unset($this->chunk);
        }
    }
}
