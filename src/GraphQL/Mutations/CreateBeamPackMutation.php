<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Rules\MaxTokenSupply;
use Enjin\Platform\Beam\Rules\TokensDoNotExistInBeam;
use Enjin\Platform\Beam\Rules\TokensDoNotExistInCollection;
use Enjin\Platform\Beam\Rules\TokensExistInCollection;
use Enjin\Platform\Beam\Rules\TokenUploadExistInCollection;
use Enjin\Platform\Beam\Rules\TokenUploadNotExistInBeam;
use Enjin\Platform\Beam\Rules\TokenUploadNotExistInCollection;
use Enjin\Platform\Beam\Rules\UniqueTokenIds;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Rules\DistinctAttributes;
use Enjin\Platform\Rules\IsCollectionOwnerOrApproved;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Rebing\GraphQL\Support\Facades\GraphQL;

class CreateBeamPackMutation extends Mutation
{
    use HasBeamCommonFields;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'CreateBeamPack',
            'description' => __('enjin-platform-beam::mutation.create_beam_pack.description'),
        ];
    }

    /**
     * Get the mutation's return type.
     */
    public function type(): Type
    {
        return GraphQL::type('String!');
    }

    /**
     * Get the mutation's arguments definition.
     */
    public function args(): array
    {
        return [
            ...$this->getCommonFields(),
            'flags' => [
                'type' => GraphQL::type('[BeamFlagInputType!]'),
                'description' => __('enjin-platform-beam::mutation.update_beam.args.flags'),
            ],
            'collectionId' => [
                'alias' => 'collection_chain_id',
                'type' => GraphQL::type('BigInt!'),
                'description' => __('enjin-platform-beam::mutation.create_beam.args.collectionId'),
            ],
            'tokens' => [
                'type' => GraphQL::type('[ClaimTokenPack!]!'),
                'description' => __('enjin-platform-beam::input_type.claim_token_pack.description'),
            ],
            'quantity' => [
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::mutation.create_beam_pack.args.quantity'),
                'defaultValue' => 1,
            ],
        ];
    }

    /**
     * Resolve the mutation's request.
     */
    public function resolve(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        Closure $getSelectFields,
        BeamService $beam
    ) {
        return DB::transaction(fn () => $beam->createPack($args)->code, 3);
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'name' => ['filled', 'max:255'],
            'description' => ['filled', 'max:1024'],
            'image' => ['filled', 'url', 'max:1024'],
            'start' => ['filled', 'date', 'before:end'],
            'end' => ['filled', 'date', 'after:start'],
            'quantity' => ['integer', 'min:1', 'max:1000'],
            'collectionId' => [
                'bail',
                'filled',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Collection::where('collection_chain_id', $value)->exists()) {
                        $fail('validation.exists')->translate();
                    }
                },
                new IsCollectionOwnerOrApproved(),
            ],
            'tokens' => ['bail', 'array', 'min:1', 'max:1000', new UniqueTokenIds()],
            'tokens.*.attributes' => Rule::forEach(function ($value, $attribute) use ($args) {
                if (empty($value)) {
                    return [];
                }

                return [
                    'nullable',
                    'bail',
                    'array',
                    'min:1',
                    'max:10',
                    new DistinctAttributes(),
                    Rule::prohibitedIf(BeamType::getEnumCase(Arr::get($args, str_replace('attributes', 'type', $attribute))) == BeamType::TRANSFER_TOKEN),
                ];
            }),
            'tokens.*.attributes.*.key' => 'max:255',
            'tokens.*.attributes.*.value' => 'max:1000',
            'tokens.*.tokenIds' => Rule::forEach(function ($value, $attribute) use ($args) {
                return [
                    'bail',
                    'required_without:tokens.*.tokenIdDataUpload',
                    'prohibits:tokens.*.tokenIdDataUpload',
                    'distinct',
                    BeamType::getEnumCase(Arr::get($args, str_replace('tokenIds', 'type', $attribute))) == BeamType::TRANSFER_TOKEN
                        ? new TokensExistInCollection($args['collectionId'])
                        : new TokensDoNotExistInCollection($args['collectionId']),
                    new TokensDoNotExistInBeam(),
                ];
            }),
            'tokens.*.tokenIdDataUpload' => Rule::forEach(function ($value, $attribute) use ($args) {
                return [
                    'bail',
                    'required_without:tokens.*.tokenIds',
                    'prohibits:tokens.*.tokenIds',
                    BeamType::getEnumCase(Arr::get($args, str_replace('tokenIdDataUpload', 'type', $attribute))) == BeamType::TRANSFER_TOKEN
                        ? new TokenUploadExistInCollection($args['collectionId'])
                        : new TokenUploadNotExistInCollection($args['collectionId']),
                    new TokenUploadNotExistInBeam(),
                ];
            }),
            'tokens.*.tokenQuantityPerClaim' => [
                'bail',
                'filled',
                'integer',
                'min:1',
                new MaxTokenSupply($args['collectionId'], $args['quantity']),
            ],
            'flags.*.flag' => ['required', 'distinct'],
        ];
    }
}
