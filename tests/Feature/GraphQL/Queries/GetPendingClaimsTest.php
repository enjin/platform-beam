<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Queries;

use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Providers\Faker\SubstrateProvider;

class GetPendingClaimsTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'GetPendingClaims';

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
    public function test_it_can_get_pending_claims(): void
    {
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $this->claims->first()->wallet_public_key,
        ]);
        $this->assertNotEmpty($response['totalCount']);

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => resolve(SubstrateProvider::class)->public_key(),
        ]);
        $this->assertEmpty($response['edges']);
    }

    /**
     * Test get claims with id and code.
     */
    public function test_will_fail_with_invalid_parameters(): void
    {
        $response = $this->graphql($this->method, [
            'code' => null,
            'account' => null,
        ], true);
        $this->assertArraySubset([
            ['message' => 'Variable "$code" of non-null type "String!" must not be null.'],
            ['message' => 'Variable "$account" of non-null type "String!" must not be null.'],
        ], $response['errors']);

        $response = $this->graphql($this->method, [
            'code' => '',
            'account' => '',
        ], true);
        $this->assertArraySubset([
            'code' => ['The code field must have a value.'],
            'account' => ['The account field must have a value.'],
        ], $response['error']);

        $response = $this->graphql($this->method, [
            'code' => fake()->text(),
            'account' => fake()->text(),
        ], true);
        $this->assertArraySubset([
            'account' => ['The account is not a valid substrate account.'],
        ], $response['error']);
    }
}
