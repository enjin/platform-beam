<?php

declare(strict_types=1);

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Override;

class EndBeamTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'EndBeam';

    /**
     * Setup test case.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam(claimsCount: 1, beam: ['end' => '9999-12-31 00:00:00']);
        BeamScan::factory()->for($this->beam)->create();
    }

    /**
     * Test deleting beam.
     */
    public function test_it_can_end_beam(): void
    {
        $response = $this->graphql($this->method, $this->generateBeamData());
        $this->assertTrue($response);

        $this->beam->refresh();
        $this->assertNotEquals('9999-12-31 00:00:00', $this->beam->end);
    }

    /**
     * Test deleting beam without providing beam code.
     */
    public function test_it_will_fail_on_empty_arguments(): void
    {
        $response = $this->graphql($this->method, [], true);

        $this->assertEquals('Variable "$code" of required type "String!" was not provided.', $response['error']);
    }

    /**
     * Test deleting beam with invalid beam code provided.
     */
    public function test_it_will_fail_on_invalid_arguments(): void
    {
        $response = $this->graphql($this->method, ['code' => fake()->text(10)], true);

        $this->assertArrayContainsArray(['code' => ['The selected code is invalid.']], $response['error']);
    }

    /**
     * Generate beam data.
     */
    protected function generateBeamData(): array
    {
        return [
            'code' => $this->beam->code,
        ];
    }
}
