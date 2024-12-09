<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Database\Eloquent\Builder;
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
}
