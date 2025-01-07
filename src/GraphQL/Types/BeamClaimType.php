<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\GraphQL\Schemas\Traits\HasAuthorizableFields;
use Enjin\Platform\Traits\HasSelectFields;
use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamClaimType extends Type
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
            'name' => 'BeamClaim',
            'description' => __('enjin-platform-beam::type.beam_claim.description'),
            'model' => BeamClaim::class,
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
                'resolve' => fn ($claim) => $claim->code ? $claim->singleUseCode : '',
                'excludeFrom' => ['GetBeam', 'GetBeams', 'GetPendingClaims'],
            ],
            'identifierCode' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.identifierCode'),
                'alias' => 'code',
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
            'attributes' => [
                'type' => GraphQL::type('[AttributeType]'),
                'description' => __('enjin-platform-beam::type.attribute.description'),
            ],
            'transaction' => [
                'type' => GraphQL::type('Transaction'),
                'description' => __('enjin-platform::type.transaction.description'),
                'is_relation' => true,
            ],
            'idempotencyKey' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform::type.transaction.field.idempotencyKey'),
                'alias' => 'idempotency_key',
            ],
        ];
    }
}
