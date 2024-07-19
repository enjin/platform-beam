<?php

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Beam\Database\Factories\BeamPackFactory;
use Enjin\Platform\Beam\GraphQL\Types\BeamPackType;
use Enjin\Platform\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Arr;

class BeamPack extends BaseModel
{
    use HasFactory;
    use Traits\EagerLoadSelectFields;
    use Traits\HasBeamQr;
    use Traits\HasCodeScope;
    use Traits\HasSingleUseCodeScope;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    public $guarded = [];

    /**
     * The fillable fields.
     *
     * @var array
     */
    protected $fillable = [
        'is_claimed',
        'beam_id',
        'code',
        'nonce',
    ];

    /**
     * The hidden fields.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'beam_id',
    ];

    /**
     * The beam's relationship.
     */
    public function beam(): BelongsTo
    {
        return $this->belongsTo(Beam::class);
    }

    /**
     * The beam claim's relationship.
     */
    public function claims(): HasMany
    {
        return $this->hasMany(BeamClaim::class, 'beam_pack_id');
    }

    public function scopeClaimable(Builder $query): Builder
    {
        return $query->where('is_claimed', false);
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
            ...(isset($fields['qr']) ? ['code'] : []),
            ...(static::$query == 'GetSingleUseCodes' ? ['code', 'nonce'] : ['nonce']),
            ...BeamPackType::getSelectFields($fieldKeys = array_keys($fields)),
        ]);

        $with = [];
        $withCount = [];

        if (! $isParent) {
            $with = [
                $key => function ($query) use ($select, $args) {
                    $query->select(array_unique($select))
                        ->when($cursor = Cursor::fromEncoded(Arr::get($args, 'after')), fn ($q) => $q->where('id', '>', $cursor->parameter('id')))
                        ->orderBy('beam_packs.id');
                    // This must be done this way to load eager limit correctly.
                    if ($limit = Arr::get($args, 'first')) {
                        $query->limit($limit + 1);
                    }
                },
            ];
        }

        foreach ([
            ...BeamPackType::getRelationFields($fieldKeys),
            ...(isset($fields['code']) ? ['beam'] : []),
        ] as $relation) {
            $with = array_merge(
                $with,
                static::getRelationQuery(
                    BeamPackType::class,
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
     * This model's factory.
     */
    protected static function newFactory(): BeamPackFactory
    {
        return BeamPackFactory::new();
    }
}
