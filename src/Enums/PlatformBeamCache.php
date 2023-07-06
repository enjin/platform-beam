<?php

namespace Enjin\Platform\Beam\Enums;

use Enjin\Platform\Interfaces\PlatformCacheable;
use Enjin\Platform\Traits\EnumExtensions;
use Illuminate\Support\Collection;

enum PlatformBeamCache: string implements PlatformCacheable
{
    use EnumExtensions;

    case CLAIM_COUNT = 'claimCount';
    case IDEMPOTENCY_KEY = 'idempotencyKey';
    case BATCH_PROCESS = 'batchProcessKey';

    /**
     * The key for the cache item.
     */
    public function key(?string $suffix = null): string
    {
        return 'enjin-platform:beam:' . $this->value . ($suffix ? ":{$suffix}" : '');
    }

    /**
     * Returns a collection of all the cache items that can be cleared.
     */
    public static function clearable(): Collection
    {
        return collect();
    }
}
