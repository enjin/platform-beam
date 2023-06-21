<?php

namespace Enjin\Platform\Beam\GraphQL\Queries;

use Closure;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\HasBeamFlag;
use Enjin\Platform\GraphQL\Middleware\ResolvePage;
use Enjin\Platform\GraphQL\Types\Pagination\ConnectionInput;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;

class GetSingleUseCodesQuery extends Query
{
    protected $middleware = [
        ResolvePage::class,
    ];

    /**
     * Get the query's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'GetSingleUseCodes',
            'description' => __('enjin-platform-beam::query.get_single_use_codes.description'),
        ];
    }

    /**
     * Get the query's return type.
     */
    public function type(): Type
    {
        return GraphQL::paginate('BeamClaim', 'BeamClaimConnection');
    }

    /**
     * Get the query's arguments defintion.
     */
    public function args(): array
    {
        return ConnectionInput::args([
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
        ]);
    }

    /**
     * Resolve the query's request.
     */
    public function resolve(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        Closure $getSelectFields
    ) {
        return BeamClaim::loadSelectFields($resolveInfo, $this->name)
            ->hasCode($args['code'])
            ->with('beam')
            ->claimable()
            ->cursorPaginateWithTotalDesc('id', $args['first']);
    }

    /**
     * Get the query's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'code' => [
                'bail',
                'required',
                'max:1024',
                new HasBeamFlag(BeamFlag::SINGLE_USE),
            ],
        ];
    }
}
