<?php

namespace Enjin\Platform\Beam\GraphQL\Enums;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Interfaces\PlatformGraphQlEnum;
use Enjin\Platform\Traits\GraphQlEnumTypeExtensions;
use Rebing\GraphQL\Support\EnumType;

class BeamTypeEnum extends EnumType implements PlatformGraphQlEnum
{
    use GraphQlEnumTypeExtensions;

    /**
     * Get the enum's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'BeamType',
            'values' => BeamType::caseNamesAsArray(),
            'description' => __('enjin-platform-beam::enum.beam_type.description'),
        ];
    }
}
