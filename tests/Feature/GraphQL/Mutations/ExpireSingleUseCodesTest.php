<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateBeamData;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Faker\Generator;
use Illuminate\Support\Arr;

class ExpireSingleUseCodesTest extends TestCaseGraphQL
{
    use CreateBeamData;
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'ExpireSingleUseCodes';

    /**
     * Test expire single use code.
     */
    public function test_it_can_expire_single_use_codes(): void
    {
        $this->truncateBeamTables();

        $code = $this->graphql('CreateBeam', $this->generateBeamData(
            BeamType::MINT_ON_DEMAND,
            1,
            [],
            [['flag' => 'SINGLE_USE']],
        ));
        $this->assertNotEmpty($code);

        $singleUseCodes = $this->graphql('GetSingleUseCodes', ['code' => $code]);
        $this->assertNotEmpty($singleUseCodes['totalCount']);

        $response = $this->graphql($this->method, [
            'codes' => [Arr::get($singleUseCodes, 'edges.0.node.code')],
        ]);
        $this->assertTrue($response);
    }

    public function test_it_can_expire_single_use_codes_beam_pack(): void
    {
        $this->truncateBeamTables();

        $code = $this->graphql('CreateBeam', $this->generateBeamPackData(
            BeamType::MINT_ON_DEMAND,
            1,
            [],
            [['flag' => 'SINGLE_USE']],
        ));
        $this->assertNotEmpty($code);

        $singleUseCodes = $this->graphql('GetSingleUseCodes', ['code' => $code]);
        $this->assertNotEmpty($singleUseCodes['totalCount']);

        $response = $this->graphql($this->method, [
            'codes' => [Arr::get($singleUseCodes, 'edges.0.node.code')],
        ]);
        $this->assertTrue($response);
    }

    public function test_it_cannot_claim_expire_single_use_codes_beam_pack(): void
    {
        $this->truncateBeamTables();

        $code = $this->graphql('CreateBeam', $this->generateBeamPackData(
            BeamType::MINT_ON_DEMAND,
            1,
            [],
            [['flag' => 'SINGLE_USE']],
        ));
        $this->assertNotEmpty($code);

        $singleUseCodes = $this->graphql('GetSingleUseCodes', ['code' => $code]);
        $this->assertNotEmpty($singleUseCodes['totalCount']);

        $response = $this->graphql($this->method, [
            'codes' => [Arr::get($singleUseCodes, 'edges.0.node.code')],
        ]);
        $this->assertTrue($response);

        $response = $this->graphql('ClaimBeam', [
            'code' => Arr::get($singleUseCodes, 'edges.0.node.code'),
            'account' => app(Generator::class)->public_key(),
        ], true);

    }

    /**
     * Test get single use beam with invalid claims.
     */
    public function test_it_will_fail_with_invalid_code(): void
    {
        $response = $this->graphql($this->method, ['codes' => [fake()->text(10)]], true);
        $this->assertArrayContainsArray(['codes' => ['The codes is invalid.']], $response['error']);
    }
}
