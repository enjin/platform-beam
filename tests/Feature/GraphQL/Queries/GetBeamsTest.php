<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Queries;

use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GetBeamsTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'GetBeams';

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam();
    }

    /**
     * Test get beam without wallet.
     */
    public function test_it_can_get_beams(): void
    {
        $response = $this->graphql($this->method, ['codes' => $this->beam->code]);
        $this->assertNotEmpty($response['totalCount']);

        $response = $this->graphql($this->method, ['names' => $this->beam->name]);
        $this->assertNotEmpty($response['totalCount']);

        $response = $this->graphql($this->method);
        $this->assertNotEmpty($response['totalCount']);
    }

    public function test_it_will_fail_with_invalid_codes(): void
    {
        $response = $this->graphql($this->method, [
            'codes' => Collection::range(1, 200)->map(fn ($val) => (string) $val)->toArray(),
        ], true);
        $this->assertArraySubset([
            'codes' => ['The codes field must not have more than 100 items.'],
        ], $response['error']);

        $response = $this->graphql($this->method, [
            'codes' => [''],
        ], true);
        $this->assertArraySubset([
            'codes.0' => ['The codes.0 field must have a value.'],
        ], $response['error']);

        $response = $this->graphql($this->method, [
            'codes' => [Str::random(2000)],
        ], true);
        $this->assertArraySubset([
            'codes.0' => ['The codes.0 field must not be greater than 1024 characters.'],
        ], $response['error']);
    }

    public function test_it_will_fail_with_invalid_names(): void
    {
        $response = $this->graphql($this->method, [
            'names' => Collection::range(1, 200)->map(fn ($val) => (string) $val)->toArray(),
        ], true);
        $this->assertArraySubset([
            'names' => ['The names field must not have more than 100 items.'],
        ], $response['error']);

        $response = $this->graphql($this->method, [
            'names' => [''],
        ], true);
        $this->assertArraySubset([
            'names.0' => ['The names.0 field must have a value.'],
        ], $response['error']);

        $response = $this->graphql($this->method, [
            'names' => [Str::random(300)],
        ], true);
        $this->assertArraySubset([
            'names.0' => ['The names.0 field must not be greater than 255 characters.'],
        ], $response['error']);
    }

    public function test_it_hides_code_field_when_unauthenticated()
    {
        config([
            'enjin-platform.auth' => 'basic_token',
            'enjin-platform.auth_drivers.basic_token.token' => Str::random(),
        ]);

        $response = $this->graphql($this->method, [], true);
        $this->assertEquals('Cannot query field "code" on type "BeamClaim".', $response['errors'][0]['message']);

        config([
            'enjin-platform.auth' => null,
        ]);
    }
}
