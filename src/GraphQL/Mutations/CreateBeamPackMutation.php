<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamPackCommonRules;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Rules\IsCollectionOwnerOrApproved;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class CreateBeamPackMutation extends Mutation
{
    use HasBeamCommonFields;
    use HasBeamPackCommonRules;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'CreateBeamPack',
            'description' => __('enjin-platform-beam::mutation.create_beam_pack.description'),
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
            'packs' => [
                'type' => GraphQL::type('[BeamPackInput!]!'),
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
        return DB::transaction(fn () => $beam->createPack($args)->code, 3);
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        return [
            'name' => ['filled', 'max:255'],
            'description' => ['filled', 'max:1024'],
            'image' => ['filled', 'url', 'max:1024'],
            'start' => ['filled', 'date', 'before:end'],
            'end' => ['filled', 'date', 'after:start'],
            'collectionId' => [
                'bail',
                'filled',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! Collection::where('collection_chain_id', $value)->exists()) {
                        $fail('validation.exists')->translate();
                    }
                },
                new IsCollectionOwnerOrApproved(),
            ],
            'flags.*.flag' => ['required', 'distinct'],
            ...$this->beamPackRules($args, $args['collectionId']),
        ];
    }
}