<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Queries;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Providers\Faker\SubstrateProvider;
use Illuminate\Support\Str;

class GetBeamTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'GetBeam';

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
    public function test_it_can_get_beam_without_wallet(): void
    {
        $response = $this->graphql($this->method, ['code' => $this->beam->code]);
        $this->assertNotEmpty($response);
    }

    public function test_it_can_get_beam_with_single_use_codes(): void
    {
        $this->seedBeam(
            1,
            false,
            BeamType::MINT_ON_DEMAND,
            ['flags_mask' => BeamService::getFlagsValue([['flag'=> 'SINGLE_USE']])]
        );
        $claim = $this->claims->first();
        $singleUseCode = encrypt(implode(':', [$claim->code, $this->beam->code, $claim->nonce]));
        $response = $this->graphql($this->method, ['code' => $singleUseCode]);
        $this->assertNotEmpty($response);

        $this->seedBeam(
            1,
            true,
            BeamType::MINT_ON_DEMAND,
            ['flags_mask' => BeamService::getFlagsValue([['flag'=> 'SINGLE_USE']])]
        );
        $claim = $this->claims->first();
        $singleUseCode = encrypt(implode(':', [$claim->code, $this->beam->code, $claim->nonce]));
        $response = $this->graphql($this->method, ['code' => $singleUseCode]);
        $this->assertNotEmpty($response);
    }

    /**
     * Test get beam with wallet.
     */
    public function test_it_can_get_beam_with_wallet(): void
    {
        $publickKey = resolve(SubstrateProvider::class)->public_key();
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publickKey,
        ]);
        $this->assertNotEmpty($response['message']);
        $message = [
            'wallet_public_key' => $response['message']['walletPublicKey'],
            'message' => $response['message']['message'],
        ];
        $this->assertNotEmpty(BeamScan::firstWhere($message));

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publickKey,
        ]);
        $this->assertNotEmpty($response['message']);
        $message = [
            'wallet_public_key' => $response['message']['walletPublicKey'],
            'message' => $response['message']['message'],
        ];
        $this->assertNotEmpty($scan = BeamScan::firstWhere($message));
        $this->assertEquals($scan->count, 2);
    }

    /**
     * Test get beam with scan limit.
     */
    public function test_it_will_fail_with_scan_limit(): void
    {
        config(['enjin-platform-beam.scan_limit' => 1]);
        $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publickKey = resolve(SubstrateProvider::class)->public_key(),
        ]);

        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => $publickKey,
        ], true);

        $this->assertArraySubset(['account' => ['You have reached the maximum limit to retry.']], $response['error']);
    }

    /**
     * Test isclaimable flag with no more claims.
     */
    public function test_isclaimable_flag_with_no_more_claims(): void
    {
        $this->beam->fill(['start' => now()->subDay()])->save();
        $response = $this->graphql($this->method, ['code' => $this->beam->code]);
        $this->assertTrue($response['isClaimable']);
        $this->claimAllBeams(resolve(SubstrateProvider::class)->public_key());

        $response = $this->graphql($this->method, ['code' => $this->beam->code]);
        $this->assertFalse($response['isClaimable']);
    }

    /**
     * Test get beam with invalid code.
     */
    public function test_it_will_fail_with_invalid_code(): void
    {
        $response = $this->graphql($this->method, [
            'code' => fake()->text(10),
            'account' => resolve(SubstrateProvider::class)->public_key(),
        ], true);

        $this->assertArraySubset(['code' => ['The selected code is invalid.']], $response['error']);
    }

    /**
     * Test get beam with empty params.
     */
    public function test_it_will_fail_with_empty_params(): void
    {
        $response = $this->graphql($this->method, [], true);

        $this->assertEquals('Variable "$code" of required type "String!" was not provided.', $response['error']);
    }

    /**
     * Test get beam with invalid wallet.
     */
    public function test_it_will_fail_with_invalid_wallet(): void
    {
        $response = $this->graphql($this->method, [
            'code' => $this->beam->code,
            'account' => fake()->text(10),
        ], true);

        $this->assertArraySubset(['account' => ['The account is not a valid substrate account.']], $response['error']);
    }

    /**
     * Test get beam with max character limit.
     */
    public function test_it_will_fail_with_max_character_limit(): void
    {
        $response = $this->graphql($this->method, ['code' => fake()->text(2000)], true);

        $this->assertArraySubset(['code' => ['The code field must not be greater than 1024 characters.']], $response['error']);
    }

    public function test_it_hides_code_field_when_unauthenticated()
    {
        config([
            'enjin-platform.auth' => 'basic_token',
            'enjin-platform.auth_drivers.basic_token.token' => Str::random(),
        ]);

        $response = $this->graphql($this->method, ['code' => $this->beam->code], true);
        $this->assertEquals('Cannot query field "code" on type "BeamClaim".', $response['error']);

        config([
            'enjin-platform.auth' => null,
        ]);
    }
}
