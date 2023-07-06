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
                    '1' => 10,
                    '2' => 20,
                    '3' => 30,
                ],
                'nft' => 40,
            ],
            $this->probabilities->getProbabilities($this->beam->code)['probabilities']
        );
    }

    public function test_it_can_remove_tokens()
    {
        $this->probabilities->createOrUpdateProbabilities($this->beam->code, $this->generateTokens());
        $this->assertEquals(
            [
                'ft' => [
                    '1' => 10,
                    '2' => 20,
                    '3' => 30,
                ],
                'nft' => 40,
            ],
            $this->probabilities->getProbabilities($this->beam->code)['probabilities']
        );

        $this->probabilities->removeTokens($this->beam->code, ['2', '3']);
        $this->assertEquals(
            [
                'ft' => ['1' => 20],
                'nft' => 80,
            ],
            $this->probabilities->getProbabilities($this->beam->code)['probabilities']
        );
    }

    protected function generateTokens(): array
    {
        return [
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['1'],
                'claimQuantity' => 10,
                'isNft' => false,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['2'],
                'claimQuantity' => 20,
                'isNft' => false,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['3'],
                'claimQuantity' => 30,
                'isNft' => false,
            ],
            [
                'type' => BeamType::MINT_ON_DEMAND->name,
                'tokenIds' => ['4'],
                'claimQuantity' => 40,
                'isNft' => true,
            ],
        ];
    }
}
