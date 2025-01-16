<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Queries;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateBeamData;

class GetSingleUseCodesTest extends TestCaseGraphQL
{
    use CreateBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'GetSingleUseCodes';

    /**
     * Test creating single use beam.
     */
    public function test_it_can_get_single_use_codes(): void
    {
        $code = $this->graphql('CreateBeam', $this->generateBeamData(
            BeamType::MINT_ON_DEMAND,
            10,
            [],
            [['flag' => 'SINGLE_USE']]
        ));

        $response = $this->graphql($this->method, ['code' => $code]);
        $this->assertNotEmpty($response['totalCount']);

        $code = $this->graphql('CreateBeam', $this->generateBeamPackData(
            BeamType::MINT_ON_DEMAND,
            10,
            [],
            [['flag' => 'SINGLE_USE']]
        ));

        $response = $this->graphql($this->method, ['code' => $code]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get single use beam with invalid claims.
     */
    public function test_it_will_fail_with_invalid_code(): void
    {
        $response = $this->graphql($this->method, ['code' => fake()->text(10)], true);
        $this->assertArrayContainsArray(['code' => ['The code is invalid.']], $response['error']);

        $response = $this->graphql($this->method, ['code' => fake()->text(10000)], true);
        $this->assertArrayContainsArray(['code' => ['The code field must not be greater than 1024 characters.']], $response['error']);
    }
}
