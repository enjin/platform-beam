<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Enjin\Platform\Beam\GraphQL\Traits\InBeamSchema;
use Enjin\Platform\Interfaces\PlatformGraphQlMutation;
use Rebing\GraphQL\Support\Mutation as GraphQlMutation;

abstract class Mutation extends GraphQlMutation implements PlatformGraphQlMutation
{
    use InBeamSchema;

    /**
     * Adhoc rules.
     *
     * @var array
     */
    public static $adhocRules = [];

    /**
     * Get validation rules.
     */
    #[\Override]
    public function getRules(array $arguments = []): array
    {
        return collect(parent::getRules($arguments))->mergeRecursive(static::$adhocRules)->all();
    }
}
