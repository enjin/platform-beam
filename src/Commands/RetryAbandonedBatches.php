<?php

namespace Enjin\Platform\Beam\Commands;

use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamBatch;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Enums\Global\TransactionState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryAbandonedBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:beam:retry-abandoned-batches
                            {code : The beam code}
                            {--dry-run : Show what would change without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset failed/in-progress claims for a beam whose batch transaction was abandoned (e.g. after a runtime upgrade).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $code = (string) $this->argument('code');
        $dryRun = (bool) $this->option('dry-run');

        $beam = Beam::query()->where('code', $code)->first();
        if (!$beam) {
            $this->error("Beam not found for code: {$code}");

            return self::FAILURE;
        }

        $this->info("Found beam: {$beam->name} (ID: {$beam->id})");

        $batchIds = BeamClaim::query()
            ->where('beam_id', $beam->id)
            ->whereNotNull('beam_batch_id')
            ->distinct()
            ->pluck('beam_batch_id');

        if ($batchIds->isEmpty()) {
            $this->info('No batches found for this beam.');

            return self::SUCCESS;
        }

        $eligibleBatchIds = BeamBatch::query()
            ->whereIn('id', $batchIds)
            ->whereNotNull('completed_at')
            ->whereHas('transaction', fn ($q) => $q->where('state', TransactionState::ABANDONED->name))
            ->pluck('id');

        if ($eligibleBatchIds->isEmpty()) {
            $this->info('No eligible batches to reset.');

            return self::SUCCESS;
        }

        $claimsToResetQuery = BeamClaim::query()
            ->whereIn('beam_batch_id', $eligibleBatchIds)
            ->whereIn('state', [ClaimStatus::FAILED->name, ClaimStatus::IN_PROGRESS->name]);

        $claimCount = (clone $claimsToResetQuery)->count();

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info('Eligible batches: ' . $eligibleBatchIds->count());
        $this->info("Claims to reset to PENDING: {$claimCount}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run enabled; no changes written.');

            return self::SUCCESS;
        }

        $updatedClaims = 0;
        $updatedBatches = 0;

        DB::transaction(function () use ($claimsToResetQuery, $eligibleBatchIds, &$updatedClaims, &$updatedBatches): void {
            $updatedClaims = $claimsToResetQuery->update(['state' => ClaimStatus::PENDING->name]);
            $updatedBatches = BeamBatch::query()
                ->whereIn('id', $eligibleBatchIds)
                ->update(['processed_at' => null, 'transaction_id' => null]);
        });

        $this->newLine();
        $this->info('=== Results ===');
        $this->info("Updated claims: {$updatedClaims}");
        $this->info("Updated batches: {$updatedBatches}");

        return self::SUCCESS;
    }
}
