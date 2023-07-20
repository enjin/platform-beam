<?php

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Beam\Database\Factories\BeamScanFactory;
use Enjin\Platform\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class BeamScan extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use Traits\HasCodeScope;
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
        'wallet_public_key',
        'beam_id',
        'message',
        'count',
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
        'beam_id',
    ];

    /**
     * The beam's relationship.
     *
     * @return BelongsTo
     */
    public function beam(): BelongsTo
    {
        return $this->belongsTo(Beam::class);
    }

    /**
     * This model's factory.
     */
    protected static function newFactory(): BeamScanFactory
    {
        return BeamScanFactory::new();
    }
}
