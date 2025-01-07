<?php

namespace Enjin\Platform\Beam\Tests\Feature\Commands;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Events\BeamClaimInProgress;
use Enjin\Platform\Beam\Listeners\UpdateClaimStatus;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Enums\Global\TransactionState;
use Enjin\Platform\Enums\Substrate\SystemEventType;
use Enjin\Platform\Events\Global\TransactionUpdated;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class BatchProcessTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * Setup test case.
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam();
        $this->claimAllBeams(resolve(SubstrateProvider::class)->public_key());
    }

    /**
     * Test batch process with finalized state.
     */
    public function test_batch_process_with_finalized_state(): void
    {
        $this->genericBatchProcessTest(TransactionState::FINALIZED, ClaimStatus::COMPLETED);
    }

    /**
     * Test batch process with abandoned state.
     */
    public function test_batch_process_with_abandoned_state(): void
    {
        $this->genericBatchProcessTest(TransactionState::ABANDONED, ClaimStatus::FAILED);
    }

    /**
     * Test batch process with threshold.
     */
    public function test_batch_process_with_threshold(): void
    {
        Config::set('enjin-platform-beam.batch_process.time_duration', 1);
        Config::set('enjin-platform-beam.batch_process.threshold', 5);

        $this->truncateBeamTables();
        $this->seedBeam(5, false, BeamType::MINT_ON_DEMAND);
        $this->seedBeam(5, false, BeamType::TRANSFER_TOKEN);
        $this->claimAllBeams(resolve(SubstrateProvider::class)->public_key());

        $batches = BeamClaim::get()->groupBy('beam_batch_id');
        $this->assertCount(2, $batches);
        $this->assertCount(5, $batches->shift());
        $this->assertCount(5, $batches->shift());
    }

    /**
     * Generic batch process test.
     */
    protected function genericBatchProcessTest(TransactionState $txnState, ClaimStatus $claimStatus): void
    {
        Event::fake();
        $this->artisan('platform:process-beam-claims', ['--test' => true])->assertSuccessful();
        Event::assertDispatched(BeamClaimInProgress::class);

        $this->claims->each(
            fn ($claim) => $this->assertEquals(
                $claim->refresh()->state,
                ClaimStatus::IN_PROGRESS->name
            )
        );
        $this->batch->refresh()->load('transaction');

        $this->assertEquals([
            'method' => BeamType::getEnumCase($this->batch->beam_type) == BeamType::MINT_ON_DEMAND ? 'BatchMint' : 'BatchTransfer',
            'state' => TransactionState::PENDING->name,
        ], Arr::only($this->batch->transaction->toArray(), ['method', 'state']));

        resolve(UpdateClaimStatus::class)->handle(
            new TransactionUpdated(
                event: [],
                transaction: $this->batch->transaction->fill([
                    'state' => $txnState->name,
                    'result' => $txnState == TransactionState::FINALIZED ? SystemEventType::EXTRINSIC_SUCCESS->name : SystemEventType::EXTRINSIC_FAILED->name,
                ])
            )
        );

        $this->batch->refresh()->load('claims');
        $this->assertNotNull($this->batch->processed_at);
        $this->batch->claims->each(fn ($claim) => $this->assertEquals($claimStatus->name, $claim->state));
    }
}
