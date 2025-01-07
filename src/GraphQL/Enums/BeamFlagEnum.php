<?php

namespace Enjin\Platform\Beam\GraphQL\Enums;

use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Interfaces\PlatformGraphQlEnum;
use Rebing\GraphQL\Support\EnumType;

class BeamFlagEnum extends EnumType implements PlatformGraphQlEnum
{
    /**
     * Get the enum's attributes.
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'name' => 'BeamFlag',
            'values' => BeamFlag::caseNamesAsArray(),
            'description' => __('enjin-platform-beam::enum.beam_flag.description'),
        ];
    }
}
