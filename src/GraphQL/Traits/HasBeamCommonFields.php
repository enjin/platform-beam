<?php

namespace Enjin\Platform\Beam\GraphQL\Traits;

use Rebing\GraphQL\Support\Facades\GraphQL;

trait HasBeamCommonFields
{
    /**
     * Get the field type's common fields.
     */
    public function getCommonFields(array $exclude = [], bool $updating = false): array
    {
        $required = $updating ? '' : '!';
        $fields = [
            'name' => [
                'type' => GraphQL::type('String' . $required),
                'description' => __('enjin-platform-beam::mutation.common.args.name'),
            ],
            'description' => [
                'type' => GraphQL::type('String' . $required),
                'description' => __('enjin-platform-beam::mutation.common.args.description'),
            ],
            'image' => [
                'type' => GraphQL::type('String' . $required),
                'description' => __('enjin-platform-beam::mutation.common.args.image'),
            ],
            'start' => [
                'type' => GraphQL::type('DateTime' . $required),
                'description' => __('enjin-platform-beam::mutation.common.args.start'),
            ],
            'end' => [
                'type' => GraphQL::type('DateTime' . $required),
                'description' => __('enjin-platform-beam::mutation.common.args.end'),
            ],
            'source' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform-beam::mutation.common.args.source'),
            ],
            'tankId' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform-beam::mutation.create_beam.args.tankId'),
            ],
            'ruleSetId' => [
                'type' => GraphQL::type('BigInt!'),
                'description' => __('enjin-platform-beam::mutation.create_beam.args.ruleSetId'),
                'defaultValue' => 0,
            ],
        ];

        return array_diff_key($fields, array_flip($exclude));
    }

    public function hasBeamFlag(array $flags, string $flag): bool
    {
        return collect($flags)->where('flag', $flag)->count();
    }
}
