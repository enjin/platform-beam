<?php

namespace Enjin\Platform\Beam\Models\Laravel\Traits;

use Enjin\Platform\Beam\GraphQL\Types\BeamClaimType;
use Enjin\Platform\Beam\GraphQL\Types\BeamType;
use Enjin\Platform\Models\Laravel\Traits\EagerLoadSelectFields as EagerLoadSelectFieldsBase;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Arr;

trait EagerLoadSelectFields
{
    use EagerLoadSelectFieldsBase {
        getRelationQuery as parentGetRelationQuery;
        loadWallet as parentLoadWallet;
    }

    /**
     * Load select and relationship fields.
     */
    public static function selectFields(ResolveInfo $resolveInfo, string $query): array
    {
        $select = ['*'];
        $with = [];
        $withCount = [];
        static::$query = $query;
        $queryPlan = $resolveInfo->lookAhead()->queryPlan();

        switch($query) {
            case 'GetBeams':
            case 'GetBeam':
                [$select, $with, $withCount] = static::loadBeams(
                    $queryPlan,
                    $query == 'GetBeams' ? 'edges.fields.node.fields' : '',
                    [],
                    null,
                    true
                );

                break;
            case 'GetPendingClaims':
            case 'GetClaims':
            case 'GetSingleUseCodes':
                [$select, $with, $withCount] = static::loadClaims(
                    $queryPlan,
                    'edges.fields.node.fields',
                    [],
                    null,
                    true
                );

                break;
        }


        return [$select, $with, $withCount];
    }

    /**
     * Load beam's select and relationship fields.
     */
    public static function loadBeams(
        array $selections,
        string $attribute,
        array $args = [],
        ?string $key = null,
        bool $isParent = false
    ): array {
        $fields = Arr::get($selections, $attribute, $selections);
        $select = array_filter([
            'id',
            Arr::hasAny($fields, ['qr', 'probabilities', 'isClaimable']) ? 'code' : null,
            isset($fields['collection']) ? 'collection_chain_id' : null,
            ...(isset($fields['isClaimable']) ? ['start', 'end', 'flags_mask'] : []),
            ...BeamType::getSelectFields($fieldKeys = array_keys($fields)),
        ]);

        $with = [];
        $withCount = [];

        if (!$isParent) {
            $with = [
                $key => function ($query) use ($select, $args) {
                    $query->select(array_unique($select))
                        ->when($cursor = Cursor::fromEncoded(Arr::get($args, 'after')), fn ($q) => $q->where('id', '>', $cursor->parameter('id')))
                        ->orderBy('beams.id');
                    // This must be done this way to load eager limit correctly.
                    if ($limit = Arr::get($args, 'first')) {
                        $query->limit($limit + 1);
                    }
                },
            ];
        }

        foreach (BeamType::getRelationFields($fieldKeys) as $relation) {
            if ($isParent && $relation == 'claims') {
                $withCount[] = $relation;
            }

            $with = array_merge(
                $with,
                static::getRelationQuery(
                    BeamType::class,
                    $relation,
                    $fields,
                    $key,
                    $with
                )
            );
        }

        return [$select, $with, $withCount];
    }

    /**
     * Load beam claim's select and relationship fields.
     */
    public static function loadClaims(
        array $selections,
        string $attribute,
        array $args = [],
        ?string $key = null,
        bool $isParent = false
    ): array {
        $fields = Arr::get($selections, $attribute, $selections);
        $select = array_filter([
            'id',
            'beam_id',
            'token_chain_id',
            isset($fields['wallet']) ? 'wallet_public_key' : null,
            isset($fields['collection']) ? 'collection_id' : null,
            ...(isset($fields['qr']) ? ['code'] : []),
            ...(static::$query == 'GetSingleUseCodes' ? ['code', 'nonce'] : ['nonce']),
            ...BeamClaimType::getSelectFields($fieldKeys = array_keys($fields)),
        ]);

        $with = [];
        $withCount = [];

        if (!$isParent) {
            $with = [
                $key => function ($query) use ($select, $args) {
                    $query->select(array_unique($select))
                        ->when($cursor = Cursor::fromEncoded(Arr::get($args, 'after')), fn ($q) => $q->where('id', '>', $cursor->parameter('id')))
                        ->orderBy('beam_claims.id');
                    // This must be done this way to load eager limit correctly.
                    if ($limit = Arr::get($args, 'first')) {
                        $query->limit($limit + 1);
                    }
                },
            ];
        }

        foreach ([
            ...BeamClaimType::getRelationFields($fieldKeys),
            ...(isset($fields['code']) ? ['beam'] : []),
        ] as $relation) {
            $with = array_merge(
                $with,
                static::getRelationQuery(
                    BeamClaimType::class,
                    $relation,
                    $fields,
                    $key,
                    $with
                )
            );
        }

        return [$select, $with, $withCount];
    }

    /**
     * Get relationship query.
     */
    public static function getRelationQuery(
        string $parentType,
        string $attribute,
        array $selections,
        ?string $parent = null,
        array $withs = []
    ): array {
        $key = $parent ? "{$parent}.{$attribute}" : $attribute;
        $alias = static::getAlias($attribute, $parentType);
        $args = Arr::get($selections, $attribute . '.args', []);
        switch($alias) {
            case 'claims':
                $relations = static::loadClaims(
                    $selections,
                    $attribute . '.fields.edges.fields.node.fields',
                    $args,
                    $key
                );
                $withs = array_merge($withs, $relations[1]);

                break;
            case 'beam':
                $relations = static::loadBeams(
                    $selections,
                    $attribute . '.fields',
                    $args,
                    $key
                );
                $withs = array_merge($withs, $relations[1]);

                break;
            default:
                return static::parentGetRelationQuery($parentType, $attribute, $selections, $parent, $withs);
        }

        return $withs;
    }
}
