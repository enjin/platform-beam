<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamPackInputType extends InputType
{
    /**
     * Get the input type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamPackInput',
            'description' => __('enjin-platform-beam::input_type.beam_pack.description'),
        ];
    }

    /**
     * Get the input type's fields.
     */
    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::input_type.beam_pack.field.id'),
            ],
            'tokens' => [
                'type' => GraphQL::type('[ClaimToken!]!'),
                'description' => __('enjin-platform-beam::input_type.claim_token.description'),
            ],
        ];
    }
}
