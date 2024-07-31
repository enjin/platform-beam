<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Traits\HasSelectFields;
use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamScanType extends Type
{
    use HasSelectFields;

    /**
     * Get the type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamScan',
            'description' => __('enjin-platform-beam::type.beam_scan.description'),
            'model' => BeamScan::class,
        ];
    }

    /**
     * Get the type's fields.
     */
    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::type('BigInt'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.id'),
            ],
            'walletPublicKey' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_scan.field.walletPublicKey'),
                'alias' => 'wallet_public_key',
            ],
            'message' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_scan.field.message'),
            ],
        ];
    }
}
