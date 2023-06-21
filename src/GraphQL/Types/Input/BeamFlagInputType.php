<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamFlagInputType extends InputType
{
    /**
     * Get the input type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamFlagInputType',
            'description' => __('enjin-platform-beam::input_type.beam_flag.description'),
        ];
    }

    /**
     * Get the input type's fields.
     */
    public function fields(): array
    {
        return [
            'flag' => [
                'type' => GraphQL::type('BeamFlag!'),
                'description' => __('enjin-platform-beam::input_type.beam_flag.field.flag'),
            ],
            'enabled' => [
                'type' => GraphQL::type('Boolean!'),
                'description' => __('enjin-platform-beam::input_type.beam_flag.field.enabled'),
                'defaultValue' => true,
            ],
        ];
    }
}
