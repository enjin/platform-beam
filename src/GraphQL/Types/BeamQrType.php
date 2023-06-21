<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamQrType extends Type
{
    /**
     * Get the type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamQr',
            'description' => __('enjin-platform-beam::type.beam_qr.description'),
        ];
    }

    /**
     * Get the type's fields.
     */
    public function fields(): array
    {
        return [
            'url' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_qr.field.url'),
                'selectable' => false,
            ],
            'payload' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_qr.field.payload'),
                'selectable' => false,
            ],
        ];
    }
}
