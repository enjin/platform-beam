<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Rules\SingleUseCodesExist;
use Enjin\Platform\Beam\Services\BeamService;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class ExpireSingleUseCodesMutation extends Mutation
{
    use HasBeamCommonFields;

    /**
     * Get the mutation's attributes.
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'name' => 'ExpireSingleUseCodes',
            'description' => __('enjin-platform-beam::mutation.expire_single_use_codes.description'),
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
    #[\Override]
    public function args(): array
    {
        return [
            'codes' => [
                'type' => GraphQL::type('[String!]!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
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
            fn () => (bool) $beam->expireSingleUseCodes($args['codes'])
        );
    }

    /**
     * Get the mutation's request validation rules.
     */
    #[\Override]
    protected function rules(array $args = []): array
    {
        return [
            'codes' => ['bail', 'array', 'min:1', new SingleUseCodesExist()],
            'codes.*' => ['max:1024'],
        ];
    }
}
