<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Database\Eloquent\Builder;

trait HasCodeScope
{
    /**
     * Local scope for beam code.
     */
    public function scopeHasCode(Builder $query, string|array|null $code): Builder
    {
        $codes = collect($code)->map(function ($code) {
            if (BeamService::isSingleUse($code)) {
                return BeamService::getSingleUseCodeData($code)->beamCode;
            }

            return $code;
        })->toArray();

        return $query->whereHas('beam', fn ($query) => $query->whereIn('code', $codes));
    }
}
