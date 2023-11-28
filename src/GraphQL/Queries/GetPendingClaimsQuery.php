<?php

namespace Enjin\Platform\Beam\GraphQL\Queries;

use Closure;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\GraphQL\Types\Pagination\ConnectionInput;
use Enjin\Platform\Interfaces\PlatformPublicGraphQlOperation;
use Enjin\Platform\Rules\ValidSubstrateAccount;
use Enjin\Platform\Support\SS58Address;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Rebing\GraphQL\Support\Facades\GraphQL;

class GetPendingClaimsQuery extends Query implements PlatformPublicGraphQlOperation
{
    /**
     * Get the query's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'GetPendingClaims',
            'description' => __('enjin-platform-beam::query.get_pending_claims.description'),
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
     * Get the query's arguments definition.
     */
    public function args(): array
    {
        return ConnectionInput::args([
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
            'account' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.account'),
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
            ->hasCode(Arr::get($args, 'code'))
            ->where('wallet_public_key', SS58Address::getPublicKey(Arr::get($args, 'account')))
            ->cursorPaginateWithTotalDesc('id', $args['first']);
    }

    /**
     * Get the query's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'code' => ['filled', 'max:1024'],
            'account' => ['filled', new ValidSubstrateAccount()],
        ];
    }
}
