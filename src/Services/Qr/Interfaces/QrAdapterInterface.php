<?php

namespace Enjin\Platform\Beam\Services\Qr\Interfaces;

interface QrAdapterInterface
{
    /**
     * Return the URL to the QR image.
     */
    public function url(string $code): string;
}
