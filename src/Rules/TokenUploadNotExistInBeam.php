<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

class TokenUploadNotExistInBeam implements DataAwareRule, ValidationRule
{
    use IntegerRange;
    use HasDataAwareRule;

    public function __construct(protected ?Model $beam = null)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
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

        $prepare = TokensDoNotExistInBeam::prepareStatement($this->beam, Arr::get($this->data, 'collectionId'));
        foreach ($tokens->chunk(1000) as $tokenIds) {
            $integers = collect($tokenIds)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                if ($prepare->whereIn('beam_claims.token_chain_id', $integers)->exists()) {
                    $fail($this->message())->translate();

                    return;
                }
            }
            $ranges = collect($tokenIds)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                if ($prepare->whereBetween('beam_claims.token_chain_id', [(int) $from, (int) $to])->exists()) {
                    $fail($this->message())->translate();
                }
            }
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'enjin-platform-beam::validation.tokens_doesnt_exist_in_beam';
    }
}
