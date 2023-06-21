<?php

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Beam\Database\Factories\BeamClaimFactory;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Enums\BeamRoute;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Models\BaseModel;
use Enjin\Platform\Models\Laravel\Collection;
use Enjin\Platform\Models\Laravel\Token;
use Enjin\Platform\Models\Laravel\Wallet;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class BeamClaim extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use Traits\HasBeamQr;
    use Traits\HasCodeScope;
    use Prunable;
    use Traits\HasClaimable;
    use Traits\EagerLoadSelectFields;
    use HasEagerLimit;


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
        'token_chain_id',
        'collection_id',
        'beam_id',
        'wallet_public_key',
        'claimed_at',
        'state',
        'attributes',
        'quantity',
        'type',
        'beam_batch_id',
        'ip_address',
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
        'deleted_at',
        'collection_id',
        'beam_batch_id',
        'beam_id',
        'nonce',
        'code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'attributes' => 'array',
    ];

    /**
     * The beam's relationship.
     */
    public function beam(): BelongsTo
    {
        return $this->belongsTo(Beam::class);
    }

    /**
     * The batch's relationship.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(BeamBatch::class, 'beam_batch_id');
    }

    /**
     * The wallet's relationship.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_public_key', 'public_key');
    }

    /**
     * The token's relationship.
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class, 'token_chain_id', 'token_chain_id')
            ->where('tokens.collection_id', '=', 'beam_claims.collection_id');
    }

    /**
     * The collection's relationship.
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
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
        $parsed = explode(':', decrypt($code), 3);

        return $query->where(['code' => $parsed[0], 'nonce' => $parsed[2]]);
    }

    /**
     * The claimable code, encoded with the open platform host url.
     */
    public function singleUseCode(): Attribute
    {
        return Attribute::make(
            get: fn () => encrypt(implode(':', [$this->code, $this->beam->code, $this->nonce]))
        );
    }

    /**
     * The claimable code, encoded with the Platform host url.
     */
    public function claimableCode(): Attribute
    {
        return Attribute::make(
            get: fn () => secure_url(Str::replace('{code}', $this->singleUseCode, BeamRoute::CLAIM->value))
        );
    }

    /**
     * Get the prunable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function prunable()
    {
        if (!is_null($days = config('enjin-platform-beam.prune_expired_claims'))) {
            $query = static::whereHas(
                'beam',
                fn ($query) => $query->where('end', '<', now()->addDays($days))
                    ->whereRaw('flags_mask & (1 << ' . BeamFlag::PRUNABLE->value . ') != 0')
            )->claimable();

            // We'll decrement the cache for each beam that has expired claims.
            (clone $query)
                ->selectRaw('beams.code, COUNT(beam_claims.id) as count')
                ->leftJoin('beams', 'beams.id', '=', 'beam_claims.beam_id')
                ->groupBy('beams.id')
                ->get()
                ->each(fn ($row) => Cache::decrement(BeamService::key($row->code), (int) $row->count));

            return $query;
        }

        // If we don't have a prune expired claims config, we'll just return a query that will never return any results.
        return static::where('id', 0);
    }

    /**
     * This model's factory.
     */
    protected static function newFactory(): BeamClaimFactory
    {
        return BeamClaimFactory::new();
    }
}
