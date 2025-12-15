<?php

namespace Enjin\Platform\Beam\Commands;

use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamBatch;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Enums\Global\TransactionState;
use Enjin\Platform\Models\Transaction;
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
            ->whereNull('claimed_at')
            ->whereNotNull('beam_batch_id')
            ->distinct()
            ->pluck('beam_batch_id')
            ->all();

        if (empty($batchIds)) {
            $this->info('No unclaimed batches found for this beam.');

            return self::SUCCESS;
        }

        $this->info(
            'Found ' . count($batchIds) . ' batch(es) with unclaimed claims.',
        );

        $batches = BeamBatch::query()
            ->whereIn('id', $batchIds)
            ->whereNotNull('completed_at')
            ->whereNotNull('transaction_id')
            ->get([
                'id',
                'transaction_id',
                'processed_at',
                'beam_type',
                'collection_chain_id',
            ]);

        $eligibleBatches = [];
        $transactionIds = [];

        foreach ($batches as $batch) {
            $transaction = Transaction::query()
                ->where('id', $batch->transaction_id)
                ->first(['id', 'state', 'transaction_chain_hash']);

            if (!$transaction) {
                $this->line("  Skipping batch {$batch->id}: transaction not found.");

                continue;
            }

            if ($transaction->state !== TransactionState::ABANDONED->name) {
                $this->line("  Skipping batch {$batch->id}: transaction state is {$transaction->state}.");

                continue;
            }

            $eligibleBatches[] = $batch;
            $transactionIds[] = $transaction->id;
            $this->line(
                "  Eligible batch {$batch->id}: transaction {$transaction->id} (state: {$transaction->state})",
            );
        }

        if (empty($eligibleBatches)) {
            $this->info('No eligible batches to reset.');

            return self::SUCCESS;
        }

        $eligibleBatchIds = array_map(fn ($b) => $b->id, $eligibleBatches);

        $claimsToResetQuery = BeamClaim::query()
            ->whereIn('beam_batch_id', $eligibleBatchIds)
            ->whereNull('claimed_at')
            ->where('state', ClaimStatus::FAILED->name);

        $claimCount = (clone $claimsToResetQuery)->count();

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info('Eligible batches: ' . count($eligibleBatchIds));
        $this->info('Batch IDs: ' . implode(', ', $eligibleBatchIds));
        $this->info(
            'Transaction IDs to unlink: ' . implode(', ', $transactionIds),
        );
        $this->info("Claims to reset to PENDING: {$claimCount}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run enabled; no changes written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use (
            $claimsToResetQuery,
            $eligibleBatchIds,
            &$updatedClaims,
            &$updatedBatches,
        ): void {
            $updatedClaims = $claimsToResetQuery->update([
                'state' => ClaimStatus::PENDING->name,
            ]);

            $updatedBatches = BeamBatch::query()
                ->whereIn('id', $eligibleBatchIds)
                ->update([
                    'processed_at' => null,
                    'transaction_id' => null,
                ]);
        });

        $this->newLine();
        $this->info('=== Results ===');
        $this->info("Updated claims: {$updatedClaims}");
        $this->info("Updated batches: {$updatedBatches}");

        return self::SUCCESS;
    }
}
