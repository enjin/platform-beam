<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\BlockchainTools\HexConverter;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Enums\Substrate\CryptoSignatureType;
use Enjin\Platform\Services\Blockchain\Interfaces\BlockchainServiceInterface;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class VerifySignedMessage implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$publicKey = SS58Address::getPublicKey($this->data['account'])) {
            $fail(__('enjin-platform::validation.valid_substrate_account', ['attribute' => 'account']));

            return;
        }

        if (BeamService::isSingleUse($this->data['code'])) {
            $this->data['code'] = explode(':', decrypt($this->data['code']))[1];
        }

        if (!$scan = BeamScan::hasCode($this->data['code'])->firstWhere(['wallet_public_key' => $publicKey])) {
            $fail(__('enjin-platform-beam::validation.beam_scan_not_found'));

            return;
        }

        $type = $this->data['cryptoSignatureType'] ?? CryptoSignatureType::SR25519->name;
        $message = $scan->message;
        if ($type == CryptoSignatureType::ED25519->name) {
            $message = HexConverter::stringToHex($message);
        }

        $passes = resolve(BlockchainServiceInterface::class)->verifyMessage(
            $message,
            $value,
            $scan->wallet_public_key,
            $type
        );

        if (!$passes) {
            $fail(__('enjin-platform-beam::validation.verify_signed_message'));
        }
    }
}
