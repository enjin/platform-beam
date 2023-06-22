<?php

namespace Enjin\Platform\Beam\GraphQL\Queries;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\CanClaim;
use Enjin\Platform\Beam\Rules\ScanLimit;
use Enjin\Platform\Beam\Rules\SingleUseCodeExist;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Interfaces\PlatformPublicGraphQlOperation;
use Enjin\Platform\Rules\ValidSubstrateAccount;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;

class GetBeamQuery extends Query implements PlatformPublicGraphQlOperation
{
    use HasBeamCommonFields;

    /**
     * Get the query's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'GetBeam',
            'description' => __('enjin-platform-beam::query.get_beam.description'),
        ];
    }

    /**
     * Get the query's return type.
     */
    public function type(): Type
    {
        return GraphQL::type('Beam!');
    }

    /**
     * Get the query's arguments definition.
     */
    public function args(): array
    {
        return [
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
            'account' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.account'),
            ],
        ];
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
        return Beam::lazyLoadSelectFields(
            $beam->scanByCode($args['code'], $args['account'] ?? null),
            $resolveInfo,
            $this->name
        );
    }

    /**
     * Get the query's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        $singleUse = BeamService::isSingleUse($args['code']);

        return [
            'code' => [
                'bail',
                'filled',
                'max:1024',
                $singleUse ? new SingleUseCodeExist() : 'exists:beams,code,deleted_at,NULL',
                new CanClaim($singleUse),
            ],
            'account' => ['sometimes', 'bail', new ValidSubstrateAccount(), new ScanLimit()],
        ];
    }
}
