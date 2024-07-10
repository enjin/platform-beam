<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Enjin\Platform\Beam\Models\BeamPack;
use Enjin\Platform\GraphQL\Schemas\Traits\HasAuthorizableFields;
use Enjin\Platform\Traits\HasSelectFields;
use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamPackType extends Type
{
    use HasAuthorizableFields;
    use HasSelectFields;

    /**
     * Get the type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamPack',
            'description' => __('enjin-platform-beam::type.beam_pack.description'),
            'model' => BeamPack::class,
        ];
    }

    /**
     * Get the type's fields.
     */
    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.id'),
            ],
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.code'),
                'resolve' => fn ($claim) => $claim->code ? $claim->singleUseCode : '',
                'excludeFrom' => ['GetBeam', 'GetBeams', 'GetPendingClaims'],
            ],
            'qr' => [
                'type' => GraphQL::type('BeamQr'),
                'description' => __('enjin-platform-beam::type.beam.field.qr'),
                'resolve' => function ($claim) {
                    return [
                        'url' => $claim->qrUrl,
                        'payload' => $claim->claimableCode,
                    ];
                },
                'selectable' => false,
                'is_relation' => false,
                'excludeFrom' => ['GetBeam', 'GetBeams', 'GetPendingClaims'],
            ],
        ];
    }
}
