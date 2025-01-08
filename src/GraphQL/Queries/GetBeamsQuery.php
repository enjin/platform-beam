<?php

namespace Enjin\Platform\Beam\GraphQL\Queries;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\GraphQL\Middleware\ResolvePage;
use Enjin\Platform\GraphQL\Types\Pagination\ConnectionInput;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Rebing\GraphQL\Support\Facades\GraphQL;

class GetBeamsQuery extends Query
{
    use HasBeamCommonFields;

    protected $middleware = [
        ResolvePage::class,
    ];

    /**
     * Get the query's attributes.
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'name' => 'GetBeams',
            'description' => __('enjin-platform-beam::query.get_beams.description'),
        ];
    }

    /**
     * Get the query's return type.
     */
    public function type(): Type
    {
        return GraphQL::paginate('Beam', 'BeamConnection');
    }

    /**
     * Get the query's arguments definition.
     */
    #[\Override]
    public function args(): array
    {
        return ConnectionInput::args([
            'codes' => [
                'type' => GraphQL::type('[String!]'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
            'names' => [
                'type' => GraphQL::type('[String!]'),
                'description' => __('enjin-platform-beam::mutation.common.args.name'),
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
        Closure $getSelectFields,
        BeamService $beam
    ) {
        return Beam::loadSelectFields($resolveInfo, $this->name)
            ->when($codes = Arr::get($args, 'codes'), fn ($query) => $query->whereIn('code', $codes))
            ->when($names = Arr::get($args, 'names'), fn ($query) => $query->whereIn('name', $names))
            ->cursorPaginateWithTotalDesc('id', $args['first']);
    }

    /**
     * Get the query's request validation rules.
     */
    #[\Override]
    protected function rules(array $args = []): array
    {
        return [
            'names' => ['bail', 'nullable', 'array', 'min:1', 'max:100'],
            'names.*' => [
                'bail',
                'filled',
                'max:255',
            ],
            'codes' => ['bail', 'nullable', 'array', 'min:1', 'max:100'],
            'codes.*' => [
                'bail',
                'filled',
                'max:1024',
            ],
        ];
    }
}
