<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\GraphQL\Traits\HasTokenInputRules;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\FuelTanks\Rules\FuelTankExists;
use Enjin\Platform\Beam\Rules\RuleSetExists;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Rules\IsCollectionOwnerOrApproved;
use Enjin\Platform\Rules\MaxBigInt;
use Enjin\Platform\Rules\MinBigInt;
use Enjin\Platform\Rules\ValidSubstrateAddress;
use Enjin\Platform\Support\Hex;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class CreateBeamMutation extends Mutation
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
            'name' => 'CreateBeam',
            'description' => __('enjin-platform-beam::mutation.create_beam.description'),
        ];
    }

    /**
     * Get the mutation's return type.
     */
    public function type(): Type
    {
        return GraphQL::type('String!');
    }

    /**
     * Get the mutation's arguments definition.
     */
    #[\Override]
    public function args(): array
    {
        return [
            ...$this->getCommonFields(),
            'flags' => [
                'type' => GraphQL::type('[BeamFlagInputType!]'),
                'description' => __('enjin-platform-beam::mutation.update_beam.args.flags'),
            ],
            'collectionId' => [
                'alias' => 'collection_chain_id',
                'type' => GraphQL::type('BigInt!'),
                'description' => __('enjin-platform-beam::mutation.create_beam.args.collectionId'),
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
            fn () => $beam->create($args, (bool) Arr::get($args, 'packs'))->code,
            3
        );
    }

    /**
     * Get the mutation's request validation rules.
     */
    #[\Override]
    protected function rules(array $args = []): array
    {
        $hasFuelTankFlag = $this->hasBeamFlag(Arr::get($args, 'flags', []), BeamFlag::USES_FUEL_TANK->name);

        return [
            'name' => ['filled', 'max:255'],
            'description' => ['filled', 'max:1024'],
            'image' => ['filled', 'url', 'max:1024'],
            'start' => ['filled', 'date', 'before:end'],
            'end' => ['filled', 'date', 'after:start'],
            'collectionId' => [
                'bail',
                'filled',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Collection::where('collection_chain_id', $value)->exists()) {
                        $fail('validation.exists')->translate();
                    }
                },
                new IsCollectionOwnerOrApproved(),
            ],
            'flags.*.flag' => ['required', 'distinct'],
            'tankId' => [
                $hasFuelTankFlag ? 'required' : 'nullable',
                'string',
                new ValidSubstrateAddress(),
                new FuelTankExists(),
            ],
            'ruleSetId' => [
                $hasFuelTankFlag ? 'required' : 'nullable',
                new MinBigInt(),
                new MaxBigInt(Hex::MAX_UINT32),
                new RuleSetExists(),
            ],
            ...$this->tokenRules($args, $args['collectionId'], true),
            ...$this->packTokenRules($args, $args['collectionId'], true),
            'source' => [
                'nullable',
                new ValidSubstrateAddress(),
            ],
        ];
    }
}
