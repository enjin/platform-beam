<?php

namespace Enjin\Platform\Beam\Tests\Feature\Traits;

use Carbon\Carbon;
use Enjin\Platform\Beam\Enums\BeamType;

trait CreateBeamData
{
    /**
     * Generate beam data.
     */
    protected function generateBeamData(
        BeamType $type = BeamType::TRANSFER_TOKEN,
        int $count = 1,
        array $attributes = [],
        array $singleUse = [],
        bool $isPack = false
    ): array {
        return [
            'name' => fake()->name(),
            'description' => fake()->word(),
            'image' => fake()->url(),
            'start' => Carbon::now()->toDateTimeString(),
            'end' => Carbon::now()->addDays(random_int(1, 1000))->toDateTimeString(),
            'collectionId' => $this->collection->collection_chain_id,
            'flags' => $singleUse,
            'isPack' => $isPack,
            'quantity' => $count,
            'tokens' => [[
                'type' => $type->name,
                'tokenIds' => $type == BeamType::TRANSFER_TOKEN
                    ? [(string) $this->token->token_chain_id]
                    : [(string) fake()->unique()->numberBetween(100, 10000), fake()->unique()->numberBetween(0, 10) . '..' . fake()->unique()->numberBetween(11, 20)],
                'tokenQuantityPerClaim' => random_int(1, $count),
                'claimQuantity' => $count,
                'attributes' => $attributes ?: null,
            ]],
        ];
    }
}
