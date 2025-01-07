<?php

namespace Enjin\Platform\Beam\GraphQL\Mutations;

use Closure;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamClaimConditions;
use Enjin\Platform\Beam\GraphQL\Traits\HasBeamCommonFields;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\CanClaim;
use Enjin\Platform\Beam\Rules\NotExpired;
use Enjin\Platform\Beam\Rules\NotOwner;
use Enjin\Platform\Beam\Rules\NotPaused;
use Enjin\Platform\Beam\Rules\SingleUseCodeExist;
use Enjin\Platform\Beam\Rules\VerifySignedMessage;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Enums\Substrate\CryptoSignatureType;
use Enjin\Platform\GraphQL\Types\Input\Substrate\Traits\HasIdempotencyField;
use Enjin\Platform\Interfaces\PlatformPublicGraphQlOperation;
use Enjin\Platform\Rules\ValidSubstrateAccount;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Rebing\GraphQL\Support\Facades\GraphQL;

class ClaimBeamMutation extends Mutation implements PlatformPublicGraphQlOperation
{
    use HasBeamClaimConditions;
    use HasBeamCommonFields;
    use HasIdempotencyField;

    /**
     * Get the mutation's attributes.
     */
    #[\Override]
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
    #[\Override]
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
            ...$this->getIdempotencyField(),
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
        $idempotencyKey = Arr::get($args, 'idempotencyKey');
        if ($idempotencyKey && BeamClaim::where('idempotency_key', $idempotencyKey)->exists()) {
            return true;
        }

        return DB::transaction(fn () => $beam->claim(
            $args['code'],
            $args['account'],
            $idempotencyKey,
        ));
    }

    /**
     * Get the mutation's request validation rules.
     */
    #[\Override]
    protected function rules(array $args = []): array
    {
        $beamCode = null;
        if ($singleUse = BeamService::isSingleUse($args['code'])) {
            $beamCode = BeamService::getSingleUseCodeData($args['code'])?->beamCode;
        }

        return [
            'code' => [
                'filled',
                'max:1024',
                new NotExpired($beamCode),
                $singleUse ? new SingleUseCodeExist(true) : '',
                new CanClaim($singleUse),
                new NotPaused($beamCode),
                ...$this->getClaimConditionRules($singleUse),
            ],
            'account' => ['filled', new ValidSubstrateAccount(), new NotOwner($singleUse)],
            'signature' => ['sometimes', new VerifySignedMessage()],
        ];
    }
}
