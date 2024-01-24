<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\BeamExists;
use Enjin\Platform\Beam\Rules\IsEndDateValid;
use Enjin\Platform\Beam\Rules\IsStartDateValid;
use Enjin\Platform\Beam\Rules\MaxTokenCount;
use Enjin\Platform\Beam\Rules\MaxTokenSupply;
use Enjin\Platform\Beam\Rules\TokensDoNotExistInBeam;
use Enjin\Platform\Beam\Rules\TokensDoNotExistInCollection;
use Enjin\Platform\Beam\Rules\TokensExistInCollection;
use Enjin\Platform\Beam\Rules\TokenUploadExistInCollection;
use Enjin\Platform\Beam\Rules\TokenUploadNotExistInBeam;
use Enjin\Platform\Beam\Rules\TokenUploadNotExistInCollection;
use Enjin\Platform\Beam\Rules\UniqueTokenIds;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Rules\DistinctAttributes;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Rebing\GraphQL\Support\Facades\GraphQL;

class UpdateBeamMutation extends Mutation
{
    use HasBeamCommonFields;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'UpdateBeam',
            'description' => __('enjin-platform-beam::mutation.update_beam.description'),
        ];
    }

    /**
     * Get the mutation's return type.
     */
    public function type(): Type
    {
        return GraphQL::type('Boolean!');
    }

    /**
     * Get the mutation's arguments definition.
     */
    public function args(): array
    {
        return [
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
            ...$this->getCommonFields([], true),
            'flags' => [
                'type' => GraphQL::type('[BeamFlagInputType!]'),
                'description' => __('enjin-platform-beam::mutation.update_beam.args.flags'),
            ],
            'tokens' => [
                'type' => GraphQL::type('[ClaimToken!]'),
                'description' => __('enjin-platform-beam::input_type.claim_token.description'),
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
        return DB::transaction(
            fn () => (bool) $beam->updateByCode(
                Arr::get($args, 'code'),
                Arr::except($args, 'code')
            ),
            3
        );
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        $beam = Beam::whereCode($args['code'])->first();

        return [
            'code' => [
                'filled',
                'max:1024',
                new BeamExists(),
            ],
            'name' => ['filled', 'max:255'],
            'description' => ['filled', 'max:1024'],
            'image' => ['filled', 'url', 'max:1024'],
            'flags.*.flag' => ['required', 'distinct'],
            'start' => ['filled', 'date', new IsStartDateValid()],
            'end' => ['filled', 'date', new IsEndDateValid()],
            'tokens' => ['bail', 'array', 'min:1', new UniqueTokenIds()],
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
                    Rule::prohibitedIf(BeamType::TRANSFER_TOKEN == BeamType::getEnumCase(Arr::get($args, str_replace('attributes', 'type', $attribute)))),
                ];
            }),
            'tokens.*.attributes.*.key' => 'max:255',
            'tokens.*.attributes.*.value' => 'max:1000',
            'tokens.*.tokenIds' => Rule::forEach(function ($value, $attribute) use ($args, $beam) {
                return [
                    'bail',
                    'required_without:tokens.*.tokenIdDataUpload',
                    'prohibits:tokens.*.tokenIdDataUpload',
                    'distinct',
                    BeamType::TRANSFER_TOKEN == BeamType::getEnumCase(Arr::get($args, str_replace('tokenIds', 'type', $attribute)))
                        ? new TokensExistInCollection($beam?->collection_chain_id)
                        : new TokensDoNotExistInCollection($beam?->collection_chain_id),
                    new TokensDoNotExistInBeam($beam),
                ];
            }),
            'tokens.*.tokenIdDataUpload' => Rule::forEach(function ($value, $attribute) use ($args, $beam) {
                return [
                    'bail',
                    'required_without:tokens.*.tokenIds',
                    'prohibits:tokens.*.tokenIds',
                    BeamType::TRANSFER_TOKEN == BeamType::getEnumCase(Arr::get($args, str_replace('tokenIdDataUpload', 'type', $attribute)))
                        ? new TokenUploadExistInCollection($beam?->collection_chain_id)
                        : new TokenUploadNotExistInCollection($beam?->collection_chain_id),
                    new TokenUploadNotExistInBeam($beam),
                ];
            }),
            'tokens.*.tokenQuantityPerClaim' => [
                'bail',
                'filled',
                'integer',
                'min:1',
                new MaxTokenSupply($beam?->collection_chain_id),
            ],
            'tokens.*.claimQuantity' => [
                'bail',
                'filled',
                'integer',
                'min:1',
                new MaxTokenCount($beam?->collection_chain_id),
            ],
        ];
    }
}
