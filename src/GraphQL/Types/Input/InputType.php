<?php

namespace Enjin\Platform\Beam\GraphQL\Types\Input;

use Enjin\Platform\Beam\GraphQL\Traits\InBeamSchema;
use Enjin\Platform\Interfaces\PlatformGraphQlType;
use Rebing\GraphQL\Support\InputType as InputTypeCore;

abstract class InputType extends InputTypeCore implements PlatformGraphQlType
{
    use InBeamSchema;
}
