<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamPackCommonRules;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\BeamExists;
use Enjin\Platform\Beam\Rules\CanUseOnBeamPack;
use Enjin\Platform\Beam\Rules\IsEndDateValid;
use Enjin\Platform\Beam\Rules\IsStartDateValid;
use Enjin\Platform\Beam\Services\BeamService;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class UpdateBeamPackMutation extends Mutation
{
    use HasBeamCommonFields;
    use HasBeamPackCommonRules;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'UpdateBeamPack',
            'description' => __('enjin-platform-beam::mutation.update_beam_pack.description'),
        ];
    }

    /**
     * Get the mutation's return type.
     */
    public function type(): Type
    {
        return GraphQL::type('Boolean!');
    }

    /**
     * Get the mutation's arguments definition.
     */
    public function args(): array
    {
        return [
            'code' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.code'),
            ],
            ...$this->getCommonFields([], true),
            'flags' => [
                'type' => GraphQL::type('[BeamFlagInputType!]'),
                'description' => __('enjin-platform-beam::mutation.update_beam.args.flags'),
            ],
            'packs' => [
                'type' => GraphQL::type('[BeamPackInput!]'),
                'description' => __('enjin-platform-beam::input_type.beam_pack.description'),
            ],
        ];
    }

    /**
     * Resolve the mutation's request.
     */
    public function resolve(
        $root,
        array $args,
        $context,
        ResolveInfo $resolveInfo,
        Closure $getSelectFields,
        BeamService $beam
    ) {
        return DB::transaction(
            fn () => (bool) $beam->updatePackByCode(
                Arr::get($args, 'code'),
                Arr::except($args, 'code')
            ),
            3
        );
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        $beam = Beam::whereCode($args['code'])->first();

        return [
            'code' => [
                'bail',
                'filled',
                'max:1024',
                new BeamExists(),
                new CanUseOnBeamPack($beam),
            ],
            'name' => ['filled', 'max:255'],
            'description' => ['filled', 'max:1024'],
            'image' => ['filled', 'url', 'max:1024'],
            'flags.*.flag' => ['required', 'distinct'],
            'start' => ['filled', 'date', new IsStartDateValid()],
            'end' => ['filled', 'date', new IsEndDateValid()],
            ...$this->beamPackRules($args, $beam?->collection_chain_id),
        ];
    }
}
