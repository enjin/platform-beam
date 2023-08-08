<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Rules\CanClaim;
use Enjin\Platform\Beam\Rules\NotExpired;
use Enjin\Platform\Beam\Rules\NotOwner;
use Enjin\Platform\Beam\Rules\NotPaused;
use Enjin\Platform\Beam\Rules\PassesConditions;
use Enjin\Platform\Beam\Rules\SingleUseCodeExist;
use Enjin\Platform\Beam\Rules\VerifySignedMessage;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Enums\Substrate\CryptoSignatureType;
use Enjin\Platform\Interfaces\PlatformPublicGraphQlOperation;
use Enjin\Platform\Rules\ValidSubstrateAccount;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class ClaimBeamMutation extends Mutation implements PlatformPublicGraphQlOperation
{
    use HasBeamCommonFields;

    /**
     * Get the mutation's attributes.
     */
    public function attributes(): array
    {
        return [
            'name' => 'ClaimBeam',
            'description' => __('enjin-platform-beam::mutation.claim_beam.description'),
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
            'account' => [
                'type' => GraphQL::type('String!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.account'),
            ],
            'signature' => [
                'type' => GraphQL::type('String'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.signature'),
            ],
            'cryptoSignatureType' => [
                'type' => GraphQL::type('CryptoSignatureType!'),
                'description' => __('enjin-platform-beam::mutation.claim_beam.args.cryptoSignatureType'),
                'defaultValue' => CryptoSignatureType::SR25519->name,
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
        return DB::transaction(fn () => $beam->claim(
            $args['code'],
            $args['account']
        ));
    }

    /**
     * Get the mutation's request validation rules.
     */
    protected function rules(array $args = []): array
    {
        $beamCode = null;
        if ($singleUse = BeamService::isSingleUse($args['code'])) {
            $beamCode = explode(':', decrypt($args['code']), 3)[1] ?? null;
        }

        return [
            'code' => [
                'bail',
                'filled',
                'max:1024',
                new NotExpired($beamCode),
                $singleUse ? new SingleUseCodeExist() : '',
                new CanClaim($singleUse),
                new NotPaused($beamCode),
                new PassesConditions($singleUse),
            ],
            'account' => ['filled', new ValidSubstrateAccount(), new NotOwner($singleUse)],
            'signature' => ['sometimes', new VerifySignedMessage()],
        ];
    }
}
