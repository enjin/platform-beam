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
                    '7' => 10.0,
                    '8..10' => 60.0,
                ],
                'nft' => 30.0,
                'ftTokenIds' => [
                    '7' => 10.0,
                    '8' => 20.0,
                    '9' => 20.0,
                    '10' => 20.0,
                ],
                'nftTokenIds' => [
                    '1' => 5.0,
                    '2' => 5.0,
                    '3' => 5.0,
                    '4' => 5.0,
                    '5' => 5.0,
                    '6' => 5.0,
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
                    '7' => 10.0,
                    '8..10' => 60.0,
                ],
                'nft' => 30.0,
                'ftTokenIds' => [
                    '7' => 10.0,
                    '8' => 20.0,
                    '9' => 20.0,
                    '10' => 20.0,
                ],
                'nftTokenIds' => [
                    '1' => 5.0,
                    '2' => 5.0,
                    '3' => 5.0,
                    '4' => 5.0,
                    '5' => 5.0,
                    '6' => 5.0,
                ],
            ],
            ClaimProbabilities::getProbabilities($this->beam->code)['probabilities']
        );

        $this->probabilities->removeTokens($this->beam->code, ['8..10']);
        $this->assertEquals(
            [
                'ft' => [
                    '7' => 25.0,
                ],
                'nft' => 75.0,
                'ftTokenIds' => [
                    '7' => 25.0,
                ],
                'nftTokenIds' => [
                    '1' => 12.5,
                    '2' => 12.5,
                    '3' => 12.5,
                    '4' => 12.5,
                    '5' => 12.5,
                    '6' => 12.5,
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
                'claimQuantity' => 4,
                'tokenQuantityPerClaim' => 1,
            ],
        ];
    }
}
