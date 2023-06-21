<?php

namespace Enjin\Platform\Beam\GraphQL\Queries;

use Enjin\Platform\Beam\GraphQL\Traits\InBeamSchema;
use Enjin\Platform\Interfaces\PlatformGraphQlQuery;
use Rebing\GraphQL\Support\Query as GraphQlQuery;

abstract class Query extends GraphQlQuery implements PlatformGraphQlQuery
{
    use InBeamSchema;
}
