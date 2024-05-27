<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Queries;

use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;

class GetClaimsTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'GetClaims';

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam(10, true);
    }

    /**
     * Test get claims.
     */
    public function test_it_can_get_claims(): void
    {
        $response = $this->graphql($this->method, []);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get beam with ids.
     */
    public function test_it_can_get_claims_with_ids(): void
    {
        $response = $this->graphql($this->method, ['ids' => $this->claims->pluck('id')->toArray()]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get beam with codes.
     */
    public function test_it_can_get_claims_with_codes(): void
    {
        $response = $this->graphql($this->method, ['codes' => [$this->beam->code]]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get beam with codes.
     */
    public function test_it_can_get_claims_with_single_use_codes(): void
    {
        $response = $this->graphql($this->method, ['singleUseCodes' => [$this->beam->claims[0]->singleUseCode]]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get beam with codes.
     */
    public function test_it_can_get_claims_with_multiple_single_use_codes(): void
    {
        $response = $this->graphql($this->method, ['singleUseCodes' => [$this->beam->claims[0]->singleUseCode, $this->beam->claims[1]->singleUseCode]]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get beam with accounts.
     */
    public function test_it_can_get_claims_with_accounts(): void
    {
        $response = $this->graphql($this->method, ['accounts' => $this->claims->pluck('wallet_public_key')->toArray()]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get beam with statuses.
     */
    public function test_it_can_get_claims_with_statuses(): void
    {
        $response = $this->graphql($this->method, ['states' => [ClaimStatus::PENDING->name]]);
        $this->assertNotEmpty($response['totalCount']);
    }

    /**
     * Test get claims with id and code.
     */
    public function test_will_fail_with_invalid_parameters(): void
    {
        $response = $this->graphql($this->method, [
            'ids' => [1],
            'codes' => [fake()->text(2000)],
        ], true);

        $this->assertArraySubset([
            'ids' => ['The ids field prohibits codes from being present.'],
            'codes' => ['The codes field prohibits ids from being present.'],
            'codes.0' => ['The codes.0 field must not be greater than 1024 characters.'],
        ], $response['error']);
    }
}
