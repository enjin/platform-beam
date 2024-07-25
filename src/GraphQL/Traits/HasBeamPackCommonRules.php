<?php

namespace Enjin\Platform\Beam\GraphQL\Traits;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Rules\BeamPackExistInBeam;
use Enjin\Platform\Beam\Rules\MaxTokenSupply;
use Enjin\Platform\Beam\Rules\TokensDoNotExistInBeam;
use Enjin\Platform\Beam\Rules\TokensExistInCollection;
use Enjin\Platform\Beam\Rules\TokenUploadExistInCollection;
use Enjin\Platform\Beam\Rules\TokenUploadNotExistInBeam;
use Enjin\Platform\Beam\Rules\UniqueTokenIds;
use Enjin\Platform\Rules\DistinctAttributes;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

trait HasBeamPackCommonRules
{
    public function beamPackRules(array $args, ?int $collectionId): array
    {
        return [
            'packs' => ['bail', 'array', 'min:1', 'max:100'],
            'packs.*.id' => [new BeamPackExistInBeam()],
            'packs.*.tokens' => ['bail', 'array', 'min:1', 'max:1000', new UniqueTokenIds()],
            'packs.*.tokens.*.attributes' => Rule::forEach(function ($value, $attribute) use ($args) {
                if (empty($value)) {
                    return [];
                }

                return [
                    'nullable',
                    'bail',
                    'array',
                    'min:1',
                    'max:10',
                    new DistinctAttributes(),
                    Rule::prohibitedIf(BeamType::getEnumCase(Arr::get($args, str_replace('attributes', 'type', $attribute))) == BeamType::TRANSFER_TOKEN),
                ];
            }),
            'packs.*.tokens.*.attributes.*.key' => 'max:255',
            'packs.*.tokens.*.attributes.*.value' => 'max:1000',
            'packs.*.tokens.*.tokenIds' => Rule::forEach(function ($value, $attribute) use ($args, $collectionId) {
                $key = str_replace('tokenIds', 'tokenIdDataUpload', $attribute);

                return [
                    'bail',
                    "required_without:{$key}",
                    "prohibits:{$key}",
                    'distinct',
                    BeamType::getEnumCase(Arr::get($args, str_replace('tokenIds', 'type', $attribute))) == BeamType::TRANSFER_TOKEN
                        ? new TokensExistInCollection($collectionId)
                        : '',
                    new TokensDoNotExistInBeam(),
                ];
            }),
            'packs.*.tokens.*.tokenIdDataUpload' => Rule::forEach(function ($value, $attribute) use ($args, $collectionId) {
                $key = str_replace('tokenIdDataUpload', 'tokenIds', $attribute);

                return [
                    'bail',
                    "required_without:{$key}",
                    "prohibits:{$key}",
                    BeamType::getEnumCase(Arr::get($args, str_replace('tokenIdDataUpload', 'type', $attribute))) == BeamType::TRANSFER_TOKEN
                        ? new TokenUploadExistInCollection($collectionId)
                        : '',
                    new TokenUploadNotExistInBeam(),
                ];
            }),
            'packs.*.tokens.*.tokenQuantityPerClaim' => [
                'bail',
                'filled',
                'integer',
                'min:1',
                new MaxTokenSupply($collectionId),
            ],
        ];
    }
}