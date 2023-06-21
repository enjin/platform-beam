<?php

namespace Enjin\Platform\Beam\Tests\Feature\Commands;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Illuminate\Support\Facades\Config;

class PruneTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * Setup test case.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test pruning expired claims.
     *
     * @return void
     */
    public function test_it_can_prune_expired_claims(): void
    {
        $this->truncateBeamTables();
        $this->seedBeam(
            5,
            false,
            null,
            [
                'end' => now()->subDays(config('enjin-platform-beam.prune_expired_claims')),
                'flags_mask' => BeamService::getFlagsValue([['flag'=> 'PRUNABLE']]),
            ]
        );
        $this->artisan('model:prune', ['--model' => BeamClaim::resolveClassFqn()]);
        $this->assertDatabaseCount('beam_claims', 0);
    }

    /**
     * Test pruning immediately.
     *
     * @return void
     */
    public function test_it_can_prune_immediately(): void
    {
        Config::set('enjin-platform-beam.prune_expired_claims', 0);
        $this->truncateBeamTables();
        $this->seedBeam(
            5,
            false,
            null,
            [
                'end' => now()->subMinute(1),
                'flags_mask' => BeamService::getFlagsValue([['flag'=> 'PRUNABLE']]),
            ]
        );
        $this->artisan('model:prune', ['--model' => BeamClaim::resolveClassFqn()]);
        $this->assertDatabaseCount('beam_claims', 0);
    }

    /**
     * Test pruning unexpired claims.
     *
     * @return void
     */
    public function test_it_cannot_prune_unexpired_claims(): void
    {
        $this->truncateBeamTables();
        $this->seedBeam(
            5,
            false,
            null,
            [
                'end' => now()->addDays(config('enjin-platform-beam.prune_expired_claims')),
                'flags_mask' => BeamService::getFlagsValue([['flag'=> 'PRUNABLE']]),
            ]
        );
        $this->artisan('model:prune', ['--model' => BeamClaim::class]);
        $this->assertDatabaseCount('beam_claims', 5);
    }

    /**
     * Test pruning with no config.
     *
     * @return void
     */
    public function test_it_cannot_prune_with_empty_config(): void
    {
        Config::set('enjin-platform-beam.prune_expired_claims', null);
        $this->truncateBeamTables();
        $this->seedBeam(
            5,
            false,
            null,
            [
                'end' => now()->subDays(365),
                'flags_mask' => BeamService::getFlagsValue([['flag'=> 'PRUNABLE']]),
            ]
        );
        $this->artisan('model:prune', ['--model' => BeamClaim::resolveClassFqn()]);
        $this->assertDatabaseCount('beam_claims', 5);
    }
}
