<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Rebing\GraphQL\Support\Facades\GraphQL;

class RemovesBeamPackInputType extends InputType
{
    /**
     * Get the input type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'RemoveBeamPack',
            'description' => __('enjin-platform-beam::input_type.remove_beam_pack.description'),
        ];
    }

    /**
     * Get the input type's fields.
     */
    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::type('Int!'),
                'description' => __('enjin-platform-beam::input_type.beam_pack.field.id'),
            ],
            'tokenIds' => [
                'type' => GraphQL::type('[IntegerRangeString!]'),
                'description' => __('enjin-platform-beam::mutation.remove_tokens.args.tokenIds'),
            ],
        ];
    }
}
