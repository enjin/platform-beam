<?php

namespace Enjin\Platform\Beam\GraphQL\Traits;

use Enjin\Platform\Beam\Rules\PassesClaimCondition;
use Enjin\Platform\Beam\Rules\PassesClaimConditions;

trait HasBeamClaimConditions
{
    public function getClaimConditionRules(bool $singleUse): array
    {
        $conditions = collect(PassesClaimConditions::getConditionalFunctions());

        return $conditions
            ->map(fn ($condition) => new PassesClaimCondition($condition, $singleUse))
            ->toArray();
    }
}
