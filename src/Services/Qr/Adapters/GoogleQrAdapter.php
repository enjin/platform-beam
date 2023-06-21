<?php

namespace Enjin\Platform\Beam\Services\Qr\Adapters;

use Enjin\Platform\Beam\Services\Qr\Interfaces\QrAdapterInterface;

class GoogleQrAdapter implements QrAdapterInterface
{
    /**
     * Return the URL to the QR image.
     */
    public function url(string $code): string
    {
        $size = config('enjin-platform-beam.qr.size');

        return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$code}";
    }
}
