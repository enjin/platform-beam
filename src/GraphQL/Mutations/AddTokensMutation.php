<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\GraphQL\Traits\HasTokenInputRules;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\BeamExists;
use Enjin\Platform\Beam\Services\BeamService;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class AddTokensMutation extends Mutation
{
    use HasBeamCommonFields;
    use HasTokenInputRules;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'AddTokens',
            'description' => __('enjin-platform-beam::mutation.add_tokens.description'),
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
            'tokens' => [
                'type' => GraphQL::type('[ClaimToken!]'),
                'description' => __('enjin-platform-beam::input_type.claim_token.description'),
            ],
            'packs' => [
                'type' => GraphQL::type('[BeamPackInput!]'),
                'description' => __('enjin-platform-beam::input_type.beam_pack.description'),
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
            fn () => $beam->addTokens(
                $args['code'],
                Arr::get($args, 'tokens', []),
                Arr::get($args, 'packs', [])
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
                !$beam?->is_pack => $this->tokenRules($args, $beam?->collection_chain_id),
                default => $this->packTokenRules($args, $beam?->collection_chain_id),
            },
        ];
    }
}
