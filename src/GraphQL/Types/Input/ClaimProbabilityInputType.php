<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Enjin\Platform\Beam\Rules\MaxBigIntIntegerRange;
use Enjin\Platform\Beam\Rules\MinBigIntIntegerRange;
use Enjin\Platform\Support\Hex;
use Rebing\GraphQL\Support\Facades\GraphQL;

class ClaimProbabilityInputType extends InputType
{
    /**
     * Get the input type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'ClaimProbability',
            'description' => __('enjin-platform-beam::input_type.claim_probability.description'),
        ];
    }

    /**
     * Get the input type's fields.
     */
    public function fields(): array
    {
        return [
            'ft' => [
                'type' => GraphQL::type('[FTProbability!]'),
                'description' => __('enjin-platform-beam::input_type.ft_probability.description'),
                'rules' => [new MinBigIntIntegerRange(), new MaxBigIntIntegerRange(Hex::MAX_UINT128)],
            ],
            'nft' => [
                'type' => GraphQL::type('Int!'),
                'description' => __('enjin-platform-beam::input_type.claim_probability.field.nft'),
                'defaultValue' => 0,
            ],
        ];
    }
}
