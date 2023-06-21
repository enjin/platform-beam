<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\BlockchainTools\HexConverter;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Enums\Substrate\CryptoSignatureType;
use Enjin\Platform\Services\Blockchain\Interfaces\BlockchainServiceInterface;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class VerifySignedMessage implements DataAwareRule, Rule
{
    use HasDataAwareRule;

    /**
     * The error message.
     */
    protected string $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$publicKey = SS58Address::getPublicKey($this->data['account'])) {
            $this->message = __('enjin-platform::validation.valid_substrate_account', ['attribute' => 'account']);

            return false;
        }

        if (BeamService::isSingleUse($this->data['code'])) {
            $this->data['code'] = explode(':', decrypt($this->data['code']))[1];
        }

        if (!$scan = BeamScan::hasCode($this->data['code'])->firstWhere(['wallet_public_key' => $publicKey])) {
            $this->message = __('enjin-platform-beam::validation.beam_scan_not_found');

            return false;
        }

        $this->message = __('enjin-platform-beam::validation.verify_signed_message');
        $type = $this->data['cryptoSignatureType'] ?? CryptoSignatureType::SR25519->name;
        $message = $scan->message;
        if ($type == CryptoSignatureType::ED25519->name) {
            $message = HexConverter::stringToHex($message);
        }

        return resolve(BlockchainServiceInterface::class)->verifyMessage(
            $message,
            $value,
            $scan->wallet_public_key,
            $type
        );
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
