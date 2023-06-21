<?php

namespace Enjin\Platform\Beam\Exceptions;

use Enjin\Platform\Exceptions\PlatformException;

class BeamException extends PlatformException
{
    /**
     * Get the exception's category.
     */
    public function getCategory(): string
    {
        return 'Platform Beam';
    }
}
