<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Rebing\GraphQL\Support\Facades\GraphQL;

class AttributeType extends Type
{
    /**
     * Get the type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'AttributeType',
            'description' => __('enjin-platform-beam::type.attribute.description'),
        ];
    }

    /**
     * Get the type's fields definition.
     */
    public function fields(): array
    {
        return [
            'key' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform::mutation.batch_set_attribute.args.key'),
            ],
            'value' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform::mutation.batch_set_attribute.args.value'),
            ],
        ];
    }
}
