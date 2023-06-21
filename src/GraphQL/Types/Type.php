<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Enjin\Platform\Beam\GraphQL\Traits\InBeamSchema;
use Enjin\Platform\Interfaces\PlatformGraphQlType;
use Rebing\GraphQL\Support\Type as GraphQlType;

abstract class Type extends GraphQlType implements PlatformGraphQlType
{
    use InBeamSchema;
}
