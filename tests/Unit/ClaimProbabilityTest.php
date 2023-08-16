<?php

namespace Enjin\Platform\Beam\Tests\Unit;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Support\ClaimProbabilities;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateCollectionData;
use Illuminate\Database\Eloquent\Model;

class ClaimProbabilityTest extends TestCaseGraphQL
{
    use CreateCollectionData;

    protected Model $beam;
    protected ClaimProbabilities $probabilities;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareCollectionData();
        $this->beam = Beam::factory()->create(['collection_chain_id' => $this->collection->collection_chain_id]);
        $this->probabilities = new ClaimProbabilities();
    }

    public function test_it_can_create_probabilities()
    {
        $this->probabilities->createOrUpdateProbabilities($this->beam->code, $this->generateTokens());
        $this->assertEquals(
            [
                'ft' => [
                    '41' => 10,
                    '42' => 20,
                    '43..45' => 30,
                ],
                'nft' => 40,
            ],
            ClaimProbabilities::getProbabilities($this->beam->code)['probabilities']
        );
    }

    public function test_it_can_remove_tokens()
    {
        $this->probabilities->createOrUpdateProbabilities($this->beam->code, $this->generateTokens());
        $this->assertEquals(
            [
                'ft' => [
                    '41' => 10,
                    '42' => 20,
                    '43..45' => 30,
                ],
                'nft' => 40,
            ],
            ClaimProbabilities::getProbabilities($this->beam->code)['probabilities']
        );

        $this->probabilities->removeTokens($this->beam->code, ['42', '43..45']);
        $this->assertEquals(
            [
                'ft' => ['41' => 20],
                'nft' => 80,
            ],
            ClaimProbabilities::getProbabilities($this->beam->code)['probabilities']
        );
    }

    protected function generateTokens(): array
    {
        return [
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['1..40'],
                'claimQuantity' => 1,
                'tokenQuantityPerClaim' => 1,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['41'],
                'claimQuantity' => 10,
                'tokenQuantityPerClaim' => 1,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['42'],
                'claimQuantity' => 20,
                'tokenQuantityPerClaim' => 1,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['43..45'],
                'claimQuantity' => 10,
                'tokenQuantityPerClaim' => 1,
            ],
        ];
    }
}
