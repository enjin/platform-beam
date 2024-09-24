<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\BeamExists;
use Enjin\Platform\Beam\Rules\BeamPackExistInBeam;
use Enjin\Platform\Beam\Rules\TokensExistInBeam;
use Enjin\Platform\Beam\Rules\TokensExistInBeamPack;
use Enjin\Platform\Beam\Services\BeamService;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class RemoveTokensMutation extends Mutation
{
    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'RemoveTokens',
            'description' => __('enjin-platform-beam::mutation.remove_tokens.description'),
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
            'tokenIds' => [
                'type' => GraphQL::type('[IntegerRangeString!]'),
                'description' => __('enjin-platform-beam::mutation.remove_tokens.args.tokenIds'),
            ],
            'packs' => [
                'type' => GraphQL::type('[RemoveBeamPack!]'),
                'description' => __('enjin-platform-beam::input_type.remove_beam_pack.description'),
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
            fn () => $beam->removeTokens(
                $args['code'],
                Arr::get($args, 'tokenIds', []),
                Arr::get($args, 'packs', []),
            )
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
            ...match (true) {
                !$beam => [],
                !$beam?->is_pack => $this->tokenIdRules(),
                default => $this->packTokenIdRules(),
            },
        ];
    }

    protected function tokenIdRules(): array
    {
        return [
            'tokenIds' => [
                'bail',
                'required',
                'prohibits:packs',
                'array',
                'min:1',
                'max:1000',
            ],
            'tokenIds.*' => [
                'bail',
                'filled',
                'distinct',
                new TokensExistInBeam(),
            ],
        ];
    }

    protected function packTokenIdRules(): array
    {
        return [
            'packs' => [
                'bail',
                'required',
                'prohibits:tokens',
                'array',
                'min:1',
                'max:1000',
            ],
            'packs.*.id' => [
                'filled',
                'integer',
                'distinct',
                new BeamPackExistInBeam(),
            ],
            'packs.*.tokenIds' => [
                'array',
                'min:1',
                'max:1000',
                'distinct',
            ],
            'packs.*.tokenIds.*' => [
                'bail',
                'filled',
                'distinct',
                new TokensExistInBeamPack(),
            ],
        ];
    }
}
