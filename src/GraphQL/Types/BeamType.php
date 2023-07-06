<?php

namespace Enjin\Platform\Beam\GraphQL\Types;

use Carbon\Carbon;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Beam\Support\ClaimProbabilities;
use Enjin\Platform\GraphQL\Types\Pagination\ConnectionInput;
use Enjin\Platform\Traits\HasSelectFields;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Rebing\GraphQL\Support\Facades\GraphQL;

class BeamType extends Type
{
    use HasBeamCommonFields;
    use HasSelectFields;

    /**
     * Get the type's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'Beam',
            'description' => __('enjin-platform-beam::type.beam.description'),
            'model' => Beam::class,
        ];
    }

    /**
     * Get the type's fields.
     */
    public function fields(): array
    {
        return [
            'id' => [
                'type' => GraphQL::type('BigInt!'),
                'description' => __('enjin-platform-beam::type.beam_claim.field.id'),
            ],
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
            ...$this->getCommonFields(),
            'collection' => [
                'type' => GraphQL::type('Collection'),
                'description' => __('enjin-platform-beam::type.beam.field.collection'),
                'is_relation' => true,
            ],
            'message' => [
                'type' => GraphQL::type('BeamScan'),
                'description' => __('enjin-platform-beam::type.beam.field.message'),
                'resolve' => fn ($beam) => $beam->relationLoaded('scans')
                            ? $beam->scans->whereNull('id')->first()
                            : null,
                'selectable' => false,
                'is_relation' => true,
            ],
            'isClaimable' => [
                'type' => GraphQL::type('Boolean!'),
                'description' => __('enjin-platform-beam::type.beam.field.isClaimable'),
                'resolve' => fn ($beam) => Carbon::now()->between(
                    Carbon::parse($beam->start),
                    Carbon::parse($beam->end)
                ) && !$beam->hasFlag(BeamFlag::PAUSED)
                && ((int) Cache::get(BeamService::key($beam->code), BeamService::claimsCountResolver($beam->code))) > 0,
                'selectable' => false,
                'is_relation' => false,
            ],
            'flags' => [
                'type' => GraphQL::type('[BeamFlag!]'),
                'description' => __('enjin-platform-beam::type.beam.field.flags'),
                'selectable' => false,
                'is_relation' => false,
            ],
            'qr' => [
                'type' => GraphQL::type('BeamQr'),
                'description' => __('enjin-platform-beam::type.beam.field.qr'),
                'resolve' => function ($beam) {
                    return [
                        'url' => $beam->qrUrl,
                        'payload' => $beam->claimableCode,
                    ];
                },
                'selectable' => false,
                'is_relation' => false,
            ],
            'probabilities' => [
                'type' => GraphQL::type('Object'),
                'description' => __('enjin-platform-beam::type.beam.field.probabilities'),
                'resolve' => fn ($beam) => (new ClaimProbabilities())->getProbabilities($beam->code)['probabilities'] ?? null,
                'is_relation' => false,
            ],
            'claims' => [
                'type' => GraphQL::paginate('BeamClaim', 'BeamClaimConnection'),
                'description' => __('enjin-platform-beam::type.beam_claim.description'),
                'args' => ConnectionInput::args(),
                'resolve' => function ($beam, $args) {
                    if ($beam->hasFlag(BeamFlag::SINGLE_USE)) {
                        return [
                            'items' => new CursorPaginator([], 1),
                            'total' => 0,
                        ];
                    }

                    return [
                        'items' => new CursorPaginator(
                            $beam?->claims,
                            $args['first'],
                            Arr::get($args, 'after') ? Cursor::fromEncoded($args['after']) : null,
                            ['parameters'=>['id']]
                        ),
                        'total' => (int) $beam?->claims_count,
                    ];
                },
                'is_relation' => true,
            ],
            'claimsRemaining' => [
                'type' => GraphQL::type('Int'),
                'description' => __('enjin-platform-beam::type.beam.field.claimsRemaining'),
                'selectable' => false,
                'is_relation' => false,
            ],
        ];
    }
}
