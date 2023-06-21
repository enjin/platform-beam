<?php

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Beam\Database\Factories\BeamFactory;
use Enjin\Platform\Models\BaseModel;
use Enjin\Platform\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeamBatch extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

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
        'completed_at',
        'processed_at',
        'transaction_id',
        'beam_type',
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
        'transaction_id',
    ];

    /**
     * The beam claim's relationship.
     */
    public function claims(): HasMany
    {
        return $this->hasMany(BeamClaim::class);
    }

    /**
     * The transaction's relationship.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * This model's factory.
     */
    protected static function newFactory(): BeamFactory
    {
        return BeamFactory::new();
    }
}
