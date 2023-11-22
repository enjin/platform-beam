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
                    '7' => 14.285714285714,
                    '8..10' => 42.857142857143,
                ],
                'nft' => 42.857142857143,
                'ftTokenIds' => [
                    '7' => 14.285714285714,
                    '8' => 14.285714285714,
                    '9' => 14.285714285714,
                    '10' => 14.285714285714,
                ],
                'nftTokenIds' => [
                    '1' => 7.1428571428571,
                    '2' => 7.1428571428571,
                    '3' => 7.1428571428571,
                    '4' => 7.1428571428571,
                    '5' => 7.1428571428571,
                    '6' => 7.1428571428571,
                ],
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
                    '7' => 14.285714285714,
                    '8..10' => 42.857142857143,
                ],
                'nft' => 42.857142857143,
                'ftTokenIds' => [
                    '7' => 14.285714285714,
                    '8' => 14.285714285714,
                    '9' => 14.285714285714,
                    '10' => 14.285714285714,
                ],
                'nftTokenIds' => [
                    '1' => 7.1428571428571,
                    '2' => 7.1428571428571,
                    '3' => 7.1428571428571,
                    '4' => 7.1428571428571,
                    '5' => 7.1428571428571,
                    '6' => 7.1428571428571,
                ],
            ],
            ClaimProbabilities::getProbabilities($this->beam->code)['probabilities']
        );

        $this->probabilities->removeTokens($this->beam->code, ['6', '8..10']);
        $this->assertEquals(
            [
                'ft' => [
                    '7' => 28.571428571429,
                ],
                'nft' => 71.428571428571,
                'ftTokenIds' => [
                    '7' => 28.571428571429,
                ],
                'nftTokenIds' => [
                    '1' => 14.285714285714,
                    '2' => 14.285714285714,
                    '3' => 14.285714285714,
                    '4' => 14.285714285714,
                    '5' => 14.285714285714,
                ],
            ],
            ClaimProbabilities::getProbabilities($this->beam->code)['probabilities']
        );
    }

    protected function generateTokens(): array
    {
        return [
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['1..5'],
                'claimQuantity' => 1,
                'tokenQuantityPerClaim' => 1,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['6'],
                'claimQuantity' => 1,
                'tokenQuantityPerClaim' => 1,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['7'],
                'claimQuantity' => 2,
                'tokenQuantityPerClaim' => 1,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['8..10'],
                'claimQuantity' => 2,
                'tokenQuantityPerClaim' => 1,
            ],
        ];
    }
}
