<?php

declare(strict_types=1);

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\BeamClaimCondition.
 *
 * @property int $beam_id
 * @property string $type
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Beam $beam
 * @method static Builder|BeamClaimWhitelist whereBeamId($value)
 * @method static Builder|BeamClaimWhitelist whereType($value)
 * @method static Builder|BeamClaimWhitelist whereValue($value)
 * @method static Builder|BeamClaimWhitelist whereCreatedAt($value)
 * @method static Builder|BeamClaimWhitelist whereUpdatedAt($value)
 * @method static Builder|BeamClaimWhitelist newModelQuery()
 * @method static Builder|BeamClaimWhitelist newQuery()
 * @method static Builder|BeamClaimWhitelist query()
 */
class BeamClaimCondition extends BaseModel
{
    protected $table = 'beam_claim_conditions';

    protected $fillable = [
        'beam_id',
        'type',
        'value',
    ];

    public function beam(): BelongsTo
    {
        return $this->belongsTo(Beam::class);
    }
}
