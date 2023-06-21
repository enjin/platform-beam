<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\Rules\TokensExistInBeam;
use Enjin\Platform\Beam\Services\BeamService;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
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
                'type' => GraphQL::type('[IntegerRangeString!]!'),
                'description' => __('enjin-platform-beam::mutation.remove_tokens.args.tokenIds'),
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
        return DB::transaction(fn () => $beam->removeTokens($args['code'], $args['tokenIds']));
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'code' => ['filled', 'max:1024', 'exists:beams,code,deleted_at,NULL'],
            'tokenIds' => [
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
}
