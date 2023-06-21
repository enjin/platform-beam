<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;

trait HasClaimable
{
    /**
     * Local scope for claimable claims.
     */
    public function scopeClaimable(Builder $query): Builder
    {
        return $query->whereNull('claimed_at');
    }
}
