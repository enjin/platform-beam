<?php

namespace Enjin\Platform\Beam\Models\Laravel;

use Carbon\Carbon;
use Enjin\Platform\Beam\Database\Factories\BeamFactory;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Beam\Support\ClaimProbabilities;
use Enjin\Platform\Models\BaseModel;
use Enjin\Platform\Models\Laravel\Collection;
use Enjin\Platform\Support\BitMask;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Beam extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use Traits\EagerLoadSelectFields;
    use Traits\HasBeamQr;

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
        'name',
        'code',
        'description',
        'image',
        'start',
        'end',
        'collection_chain_id',
        'flags_mask',
        'source',
    ];

    /**
     * Cascade softdeletes.
     */
    protected $cascadeDeletes = ['claims', 'scans'];

    /**
     * The hidden fields.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The beam claim's relationship.
     */
    public function claims(): HasMany
    {
        return $this->hasMany(BeamClaim::class, 'beam_id');
    }

    /**
     * The beam scans relationship.
     */
    public function scans(): HasMany
    {
        return $this->hasMany(BeamScan::class, 'beam_id');
    }

    /**
     * The collection relationship.
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection_chain_id', 'collection_chain_id');
    }

    /**
     * Check if the beam has a flag.
     */
    public function hasFlag(BeamFlag $flag): bool
    {
        return BitMask::getBit($flag->value, $this->flags_mask ?? 0);
    }

    /**
     * Boot model.
     */
    public static function boot()
    {
        static::deleting(function ($model) {
            BeamScan::where('beam_id', $model->id)->update(['deleted_at' => $now = now()]);
            BeamClaim::where('beam_id', $model->id)->update(['deleted_at' => $now]);
        });

        static::deleted(function ($model) {
            Cache::forget(BeamService::key($model->code));
        });

        parent::boot();
    }

    /**
     * Interact with the beam's start attribute.
     */
    protected function start(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => Carbon::parse($value)->toDateTimeString(),
        );
    }

    /**
     * Interact with the beam's end attribute.
     */
    protected function end(): Attribute
    {
        return $this->start();
    }

    /**
     * Interact with the beam's claims remaining attribute.
     */
    protected function claimsRemaining(): Attribute
    {
        return Attribute::make(
            get: fn () => Cache::get(BeamService::key($this->code), BeamService::claimsCountResolver($this->code))
        );
    }

    /**
     * Interact with the beam's claims remaining attribute.
     */
    protected function probabilities(): Attribute
    {
        return Attribute::make(
            get: fn () => ClaimProbabilities::getProbabilities($this->code)['probabilities'] ?? null
        );
    }

    /**
     * This model's factory.
     */
    protected static function newFactory(): BeamFactory
    {
        return BeamFactory::new();
    }

    /**
     * The beam flags attribute.
     */
    protected function flags(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(BitMask::getBits($this->flags_mask))->map(function ($flag) {
                return BeamFlag::from($flag)->name;
            })->toArray()
        );
    }

    /**
     * This model's specific pivot identifier.
     */
    protected function pivotIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->code,
        );
    }
}
