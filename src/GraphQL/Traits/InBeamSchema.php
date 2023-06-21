<?php

namespace Enjin\Platform\Beam\GraphQL\Traits;

use Enjin\Platform\GraphQL\Schemas\Traits\GetsMiddleware;

trait InBeamSchema
{
    use GetsMiddleware;

    /**
     * The schema name.
     */
    public static function getSchemaName(): string
    {
        return 'beam';
    }

    /**
     * The schema network.
     */
    public static function getSchemaNetwork(): string
    {
        return '';
    }
}
