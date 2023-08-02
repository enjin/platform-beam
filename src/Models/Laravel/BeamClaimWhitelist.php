<?php

declare(strict_types=1);

namespace Enjin\Platform\Beam\Models\Laravel;

use Enjin\Platform\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\BeamClaimWhitelist.
 *
 * @property int $beam_id
 * @property string $address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Beam $beam
 * @method static Builder|BeamClaimWhitelist whereAddress($value)
 * @method static Builder|BeamClaimWhitelist whereBeamId($value)
 * @method static Builder|BeamClaimWhitelist whereCreatedAt($value)
 * @method static Builder|BeamClaimWhitelist whereUpdatedAt($value)
 * @method static Builder|BeamClaimWhitelist newModelQuery()
 * @method static Builder|BeamClaimWhitelist newQuery()
 * @method static Builder|BeamClaimWhitelist query()
 */
class BeamClaimWhitelist extends BaseModel
{
    public $table = 'beam_claim_whitelist';

    protected $fillable = [
        'beam_id',
        'address',
    ];

    public function beam(): BelongsTo
    {
        return $this->belongsTo(Beam::class);
    }
}
