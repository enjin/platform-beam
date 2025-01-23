<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\GraphQL\Traits\HasTokenInputRules;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\BeamExists;
use Enjin\Platform\Beam\Rules\IsEndDateValid;
use Enjin\Platform\Beam\Rules\IsStartDateValid;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\FuelTanks\Rules\FuelTankExists;
use Enjin\Platform\FuelTanks\Rules\RuleSetExists;
use Enjin\Platform\Rules\MaxBigInt;
use Enjin\Platform\Rules\MinBigInt;
use Enjin\Platform\Rules\ValidSubstrateAddress;
use Enjin\Platform\Support\Hex;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class UpdateBeamMutation extends Mutation
{
    use HasBeamCommonFields;
    use HasTokenInputRules;

    /**
     * Get the mutation's attributes.
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'name' => 'UpdateBeam',
            'description' => __('enjin-platform-beam::mutation.update_beam.description'),
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
    #[\Override]
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
            'tokens' => [
                'type' => GraphQL::type('[ClaimToken!]'),
                'description' => __('enjin-platform-beam::input_type.claim_token.description'),
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
            fn () => (bool) $beam->updateByCode(
                Arr::get($args, 'code'),
                Arr::except($args, 'code')
            ),
            3
        );
    }

    /**
     * Get the mutation's request validation rules.
     */
    #[\Override]
    protected function rules(array $args = []): array
    {
        $beam = Beam::whereCode($args['code'])->first();

        return [
            'code' => [
                'filled',
                'max:1024',
                new BeamExists(),
            ],
            'name' => ['filled', 'max:255'],
            'description' => ['filled', 'max:1024'],
            'image' => ['filled', 'url', 'max:1024'],
            'flags.*.flag' => ['required', 'distinct'],
            'tankId' => [
                'nullable',
                'string',
                new ValidSubstrateAddress(),
                new FuelTankExists(),
            ],
            'tankRuleId' => [
                'nullable',
                new MinBigInt(),
                new MaxBigInt(Hex::MAX_UINT32),
                new RuleSetExists(),
            ],
            'start' => ['filled', 'date', new IsStartDateValid()],
            'end' => ['filled', 'date', new IsEndDateValid()],
            ...match (true) {
                !$beam => [],
                !$beam?->is_pack => $this->tokenRules($args, $beam?->collection_chain_id),
                default => $this->packTokenRules($args, $beam?->collection_chain_id),
            },
        ];
    }
}
