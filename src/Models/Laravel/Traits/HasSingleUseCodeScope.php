<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;

trait HasSingleUseCodeScope
{
    /**
     * Local scope for beam code.
     */
    public function scopeHasSingleUseCode(Builder $query, string|array|null $code): Builder
    {
        try {
            if (is_array($code)) {
                $singleUseCode = array_map(function ($item) {
                    return BeamService::getSingleUseCodeData($item)?->claimCode;
                }, $code);
            } else {
                $singleUseCode = BeamService::getSingleUseCodeData($code)?->claimCode;
            }

            return $query->whereIn('code', Arr::wrap($singleUseCode));
        } catch (\Throwable $exception) {
            return $query;
        }
    }

    /**
     * Local scope for single use.
     */
    public function scopeSingleUse(Builder $query): Builder
    {
        return $query->whereNotNull('code');
    }

    /**
     * Local scope for single use code.
     */
    public function scopeWithSingleUseCode(Builder $query, string $code): Builder
    {
        $parsed = BeamService::getSingleUseCodeData($code);

        return $query->where(['code' => $parsed->claimCode, 'nonce' => $parsed->nonce]);
    }

    /**
     * The claimable code, encoded with the open platform host url.
     */
    public function singleUseCode(): Attribute
    {
        return Attribute::make(
            get: fn () => encrypt(implode(':', [$this->code, $this->beam?->code, $this->nonce]))
        );
    }
}
