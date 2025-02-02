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
    #[\Override]
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
    #[\Override]
    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::type.beam_pack.field.id'),
            ],
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.code'),
                'resolve' => fn ($claim) => $claim->code ? $claim->singleUseCode : '',
                'excludeFrom' => ['GetBeam', 'GetBeams', 'GetPendingClaims'],
            ],
            'isClaimed' => [
                'type' => GraphQL::type('Boolean!'),
                'description' => __('enjin-platform-beam::type.beam_pack.field.isClaimed'),
                'alias' => 'is_claimed',
            ],
            'beam' => [
                'type' => GraphQL::type('Beam'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.beam'),
                'is_relation' => true,
            ],
            'qr' => [
                'type' => GraphQL::type('BeamQr'),
                'description' => __('enjin-platform-beam::type.beam.field.qr'),
                'resolve' => fn ($claim) => [
                    'url' => $claim->qrUrl,
                    'payload' => $claim->claimableCode,
                ],
                'selectable' => false,
                'is_relation' => false,
                'excludeFrom' => ['GetBeam', 'GetBeams', 'GetPendingClaims'],
            ],
            'claims' => [
                'type' => GraphQL::type('[BeamClaim!]'),
                'description' => __('enjin-platform-beam::type.beam_claim.description'),
                'selectable' => false,
                'is_relation' => true,
            ],
        ];
    }
}
