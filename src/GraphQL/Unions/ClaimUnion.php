<?php

namespace Enjin\Platform\Beam\GraphQL\Unions;

use Enjin\Platform\Interfaces\PlatformGraphQlUnion;
use GraphQL\Type\Definition\ResolveInfo;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\UnionType;

class ClaimUnion extends UnionType implements PlatformGraphQlUnion
{
    /**
     * Get the type's attributes.
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'name' => 'ClaimUnion',
            'description' => __('enjin-platform-beam::union.claim_union.description'),
        ];
    }

    /**
     * The possible types that this union can be.
     */
    public function types(): array
    {
        return [
            GraphQL::type('BeamClaim'),
            GraphQL::type('BeamPack'),
        ];
    }

    /**
     * Resolves concrete ObjectType for given object value.
     */
    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        return GraphQL::type($objectValue?->is_pack ? 'BeamPack' : 'BeamClaim');
    }
}
