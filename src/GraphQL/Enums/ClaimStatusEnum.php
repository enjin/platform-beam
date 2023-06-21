<?php

namespace Enjin\Platform\Beam\GraphQL\Enums;

use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Interfaces\PlatformGraphQlEnum;
use Enjin\Platform\Traits\GraphQlEnumTypeExtensions;
use Rebing\GraphQL\Support\EnumType;

class ClaimStatusEnum extends EnumType implements PlatformGraphQlEnum
{
    use GraphQlEnumTypeExtensions;

    /**
     * Get the enum's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'ClaimStatus',
            'values' => ClaimStatus::caseNamesAsArray(),
            'description' => __('enjin-platform-beam::enum.claim_status.description'),
        ];
    }
}
