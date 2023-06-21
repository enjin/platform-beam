<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait HasCodeScope
{
    /**
     * Local scope for beam code.
     */
    public function scopeHasCode(Builder $query, string|array|null $code): Builder
    {
        return $query->whereHas('beam', fn ($query) => $query->whereIn('code', Arr::wrap($code)));
    }
}
