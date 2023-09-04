<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

class TokenUploadNotExistInBeam implements Rule
{
    use IntegerRange;

    public function __construct(protected ?Model $beam = null)
    {
    }

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
        $ids = collect();
        $tokens = LazyCollection::make(function () use ($value, $ids) {
            $handle = fopen($value->getPathname(), 'r');
            while (($line = fgets($handle)) !== false) {
                if (!$this->tokenIdExists($ids->all(), $tokenId = trim($line))) {
                    $ids->push($tokenId);
                    yield $tokenId;
                }
            }
            fclose($handle);
        });

        $prepare = TokensDoNotExistInBeam::prepareStatement($this->beam);
        foreach ($tokens->chunk(1000) as $tokenIds) {
            $integers = collect($tokenIds)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                if ($prepare->whereIn('beam_claims.token_chain_id', $integers)->exists()) {
                    return false;
                }
            }
            $ranges = collect($tokenIds)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                if ($prepare->whereBetween('beam_claims.token_chain_id', [(int) $from, (int) $to])->exists()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.tokens_doesnt_exist_in_beam');
    }
}
