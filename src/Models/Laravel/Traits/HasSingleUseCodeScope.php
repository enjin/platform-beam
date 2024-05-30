<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

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
                    return explode(':', decrypt($item))[0];
                }, $code);
            } else {
                $singleUseCode = explode(':', decrypt($code))[0];
            }

            return $query->whereIn('code', Arr::wrap($singleUseCode));
        } catch (\Throwable $exception) {
            return $query;
        }
    }
}
