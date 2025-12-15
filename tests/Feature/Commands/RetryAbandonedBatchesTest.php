<?php

namespace Enjin\Platform\Beam\Tests\Feature\Commands;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Enums\Global\TransactionState;
use Enjin\Platform\Models\Transaction;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Illuminate\Support\Str;

class RetryAbandonedBatchesTest extends TestCaseGraphQL
{
    use SeedBeamData;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam(5, false, BeamType::MINT_ON_DEMAND);
        $this->claimAllBeams(resolve(SubstrateProvider::class)->public_key());
    }

    public function test_it_resets_abandoned_batches(): void
    {
        $this->setupAbandonedBatch();

        BeamClaim::query()->update(['state' => ClaimStatus::FAILED->name]);

        $this->artisan('platform:beam:retry-abandoned-batches', [
            'code' => $this->beam->code,
            '--yes' => true,
        ])
            ->expectsOutputToContain('Updated claims:')
            ->expectsOutputToContain('Updated batches:')
            ->assertSuccessful();

        BeamClaim::all()->each(fn ($claim) => $this->assertEquals(ClaimStatus::PENDING->name, $claim->state));

        $this->batch->refresh();
        $this->assertNull($this->batch->processed_at);
        $this->assertNull($this->batch->transaction_id);
    }

    public function test_dry_run_does_not_modify_data(): void
    {
        $this->setupAbandonedBatch();

        BeamClaim::query()->update(['state' => ClaimStatus::FAILED->name]);

        $this->artisan('platform:beam:retry-abandoned-batches', [
            'code' => $this->beam->code,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run enabled')
            ->assertSuccessful();

        $this->assertEquals(ClaimStatus::FAILED->name, BeamClaim::first()->state);
        $this->assertNotNull($this->batch->refresh()->transaction_id);
    }

    protected function setupAbandonedBatch(): void
    {
        $transaction = Transaction::create([
            'transaction_chain_id' => fake()->numerify('######'),
            'transaction_chain_hash' => fake()->sha256(),
            'method' => 'BatchMint',
            'state' => TransactionState::ABANDONED->name,
            'encoded_data' => '0x',
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $this->batch->update([
            'completed_at' => now(),
            'processed_at' => now(),
            'transaction_id' => $transaction->id,
        ]);
    }
}
