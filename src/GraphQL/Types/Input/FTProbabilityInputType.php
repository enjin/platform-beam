<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Enjin\Platform\Rules\MaxBigInt;
use Enjin\Platform\Rules\MinBigInt;
use Enjin\Platform\Support\Hex;
use Rebing\GraphQL\Support\Facades\GraphQL;

class FTProbabilityInputType extends InputType
{
    /**
     * Get the input type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'FTProbability',
            'description' => __('enjin-platform-beam::input_type.ft_probability.description'),
        ];
    }

    /**
     * Get the input type's fields.
     */
    public function fields(): array
    {
        return [
            'tokenId' => [
                'type' => GraphQL::type('BigInt!'),
                'description' => __('enjin-platform-beam::input_type.claim_token.field.tokenId'),
                'rules' => [new MinBigInt(), new MaxBigInt(Hex::MAX_UINT128)],
            ],
            'chance' => [
                'type' => GraphQL::type('Float!'),
                'description' => __('enjin-platform-beam::input_type.ft_probability.field.chance'),
                'defaultValue' => 0,
            ],
        ];
    }
}
