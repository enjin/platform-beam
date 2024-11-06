<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Rules\MaxBigIntIntegerRange;
use Enjin\Platform\Beam\Rules\MinBigIntIntegerRange;
use Enjin\Platform\Support\Hex;
use Rebing\GraphQL\Support\Facades\GraphQL;

class ClaimTokenInputType extends InputType
{
    /**
     * Get the input type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'ClaimToken',
            'description' => __('enjin-platform-beam::input_type.claim_token.description'),
        ];
    }

    /**
     * Get the input type's fields.
     */
    public function fields(): array
    {
        return [
            'tokenIds' => [
                'type' => GraphQL::type('[IntegerRangeString!]'),
                'description' => __('enjin-platform-beam::input_type.claim_token.field.tokenId'),
                'rules' => [new MinBigIntIntegerRange(), new MaxBigIntIntegerRange(Hex::MAX_UINT128)],
            ],
            'tokenIdDataUpload' => [
                'type' => GraphQL::type('Upload'),
                'description' => __('enjin-platform-beam::input_type.claim_token.field.tokenIdDataUpload'),
                'rules' => ['file', 'mimes:json,txt'],
            ],
            'attributes' => [
                'type' => GraphQL::type('[AttributeInput]'),
                'description' => __('enjin-platform::input_type.create_token_params.field.attributes'),
                'defaultValue' => [],
            ],
            'tokenQuantityPerClaim' => [
                'alias' => 'quantity',
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::input_type.claim_token.field.tokenQuantityPerClaim'),
                'rules' => ['integer'],
                'defaultValue' => 1,
            ],
            'claimQuantity' => [
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::input_type.claim_token.field.claimQuantity'),
                'rules' => ['integer'],
            ],
            'type' => [
                'type' => GraphQL::type('BeamType'),
                'description' => __('enjin-platform-beam::mutation.common.args.type'),
                'defaultValue' => BeamType::TRANSFER_TOKEN->name,
            ],
        ];
    }
}
