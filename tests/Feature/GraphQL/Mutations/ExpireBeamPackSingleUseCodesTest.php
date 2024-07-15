<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateBeamData;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Illuminate\Support\Arr;

class ExpireBeamPackSingleUseCodesTest extends TestCaseGraphQL
{
    use CreateBeamData;
    use SeedBeamData;

    /**
     * The graphql method.
     */
    protected string $method = 'ExpireBeamPackSingleUseCodes';

    /**
     * Test expire single use code.
     */
    public function test_it_can_expire_single_use_codes(): void
    {
        $this->truncateBeamTables();

        $code = $this->graphql('CreateBeamPack', $this->generateBeamPackData(
            BeamType::MINT_ON_DEMAND,
            1,
            [],
            [['flag' => 'SINGLE_USE']],
        ));
        $this->assertNotEmpty($code);

        $singleUseCodes = $this->graphql('GetBeamPackSingleUseCodes', ['code' => $code]);
        $this->assertNotEmpty($singleUseCodes['totalCount']);

        $response = $this->graphql($this->method, [
            'codes' => [Arr::get($singleUseCodes, 'edges.0.node.code')],
        ]);
        $this->assertTrue($response);
    }

    /**
     * Test get single use beam with invalid claims.
     */
    public function test_it_will_fail_with_invalid_code(): void
    {
        $response = $this->graphql($this->method, ['codes' => [fake()->text(10)]], true);
        $this->assertArraySubset(['codes' => ['The codes is invalid.']], $response['error']);
    }
}
