<?php

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Beam\Database\Factories\BeamPackFactory;
use Enjin\Platform\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeamPack extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use Traits\EagerLoadSelectFields;

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

    /**
     * This model's factory.
     */
    protected static function newFactory(): BeamPackFactory
    {
        return BeamPackFactory::new();
    }
}
