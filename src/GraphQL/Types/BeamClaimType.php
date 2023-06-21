<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Traits\HasSelectFields;
use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamClaimType extends Type
{
    use HasSelectFields;

    /**
     * Get the type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamClaim',
            'description' => __('enjin-platform-beam::type.beam_claim.description'),
            'model' => BeamClaim::class,
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
            'collection' => [
                'type' => GraphQL::type('Collection'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.collection'),
                'is_relation' => true,
            ],
            'tokenId' => [
                'alias' => 'token_chain_id',
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.token'),
            ],
            'quantity' => [
                'type' => GraphQL::type('Int!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.quantity'),
            ],
            'beam' => [
                'type' => GraphQL::type('Beam'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.beam'),
                'is_relation' => true,
            ],
            'wallet' => [
                'type' => GraphQL::type('Wallet'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.account'),
                'is_relation' => true,
            ],
            'claimedAt' => [
                'type' => GraphQL::type('DateTime'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.field.claimedAt'),
                'alias' => 'claimed_at',
            ],
            'claimStatus' => [
                'type' => GraphQL::type('ClaimStatus'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.field.claimStatus'),
                'alias' => 'state',
            ],
            'type' => [
                'type' => GraphQL::type('BeamType'),
                'description' => __('enjin-platform-beam::mutation.common.args.type'),
            ],
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.code'),
                'resolve' => fn ($claim) => $claim->singleUseCode,
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
            ],
        ];
    }
}
