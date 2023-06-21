<?php

namespace Enjin\Platform\Beam\GraphQL\Queries;

use Closure;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\GraphQL\Middleware\ResolvePage;
use Enjin\Platform\GraphQL\Types\Pagination\ConnectionInput;
use Enjin\Platform\Rules\MaxBigInt;
use Enjin\Platform\Rules\MinBigInt;
use Enjin\Platform\Rules\ValidSubstrateAccount;
use Enjin\Platform\Support\SS58Address;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Rebing\GraphQL\Support\Facades\GraphQL;

class GetClaimsQuery extends Query
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
            'name' => 'GetClaims',
            'description' => __('enjin-platform-beam::query.get_claims.description'),
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
            'ids' => [
                'type' => GraphQL::type('[BigInt]'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.id'),
                'rules' => ['prohibits:codes'],
            ],
            'codes' => [
                'type' => GraphQL::type('[String]'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
                'rules' => ['prohibits:ids'],
            ],
            'accounts' => [
                'type' => GraphQL::type('[String]'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.account'),
            ],
            'states' => [
                'type' => GraphQL::type('[ClaimStatus]'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.field.claimStatus'),
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
            ->when(Arr::get($args, 'ids'), fn ($query) => $query->whereIn('id', $args['ids']))
            ->when(Arr::get($args, 'codes'), fn ($query) => $query->hasCode($args['codes']))
            ->when(Arr::get($args, 'accounts'), fn ($query) => $query->whereIn(
                'wallet_public_key',
                collect($args['accounts'])->map(fn ($account) => SS58Address::getPublicKey($account))
            ))
            ->when(Arr::get($args, 'states'), fn ($query) => $query->whereIn('state', $args['states']))
            ->cursorPaginateWithTotalDesc('id', $args['first']);
    }

    /**
     * Get the query's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'ids.*' => [new MinBigInt(1), new MaxBigInt()],
            'codes.*' => ['max:1024'],
            'accounts.*' => [new ValidSubstrateAccount()],
        ];
    }
}
