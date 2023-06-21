<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Enjin\Platform\Beam\Enums\BeamRoute;
use Enjin\Platform\Beam\Services\Qr\Interfaces\QrAdapterInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

trait HasBeamQr
{
    /**
     * The claimable code, encoded with the Platform host url.
     */
    public function claimableCode(): Attribute
    {
        return Attribute::make(
            get: fn () => secure_url(Str::replace('{code}', $this->code, BeamRoute::CLAIM->value))
        );
    }

    /**
     * The QR URL attribute.
     */
    public function qrUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => resolve(QrAdapterInterface::class)->url($this->claimableCode)
        );
    }
}
