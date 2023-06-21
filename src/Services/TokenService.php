<?php

namespace Enjin\Platform\Beam\Services;

use Enjin\Platform\GraphQL\Types\Scalars\Traits\HasIntegerRanges;
use Enjin\Platform\Services\Database\TokenService as DatabaseTokenService;
use Illuminate\Support\Arr;

class TokenService extends DatabaseTokenService
{
    use HasIntegerRanges;

    /**
     * Check if tokens exist in collection.
     */
    public function tokensExistInCollection(array $tokenIds, string $collectionId): bool
    {
        $tokenIds = Arr::flatten($tokenIds);

        return $this->inCollection($collectionId)
            ->whereIn('token_chain_id', $tokenIds)
            ->count() == count($tokenIds);
    }

    /**
     * Check if tokens doesn't exist in collection.
     */
    public function tokensDoNotExistInCollection(array $tokenIds, string $collectionId): bool
    {
        $tokenIds = Arr::flatten($tokenIds);

        return 0 == $this->inCollection($collectionId)
            ->whereIn('token_chain_id', $tokenIds)
            ->count();
    }
}
