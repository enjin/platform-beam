<?php

namespace Enjin\Platform\Beam\GraphQL\Traits;

use Enjin\Platform\Support\SS58Address;
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
        ];

        return array_diff_key($fields, array_flip($exclude));
    }
}
