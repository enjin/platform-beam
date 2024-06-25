<?php

namespace Enjin\Platform\Beam\Jobs;

use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class CreateClaim implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected ?array $claim) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->claim) {
            $scan = BeamScan::firstOrNew(Arr::only($this->claim, ['wallet_public_key', 'beam_id']));
            $scan->fill([
                'message' => $this->claim['message'] ?? BeamService::generateSigningRequestMessage(),
                'count' => (int) $scan->count + 1,
            ])->save();
        }
    }
}
