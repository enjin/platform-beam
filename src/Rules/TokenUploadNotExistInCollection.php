<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Token;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\LazyCollection;

class TokenUploadNotExistInCollection implements ValidationRule
{
    use IntegerRange;

    public function __construct(protected ?string $collectionId) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->collectionId && $value) {
            $ids = collect();
            $tokens = LazyCollection::make(function () use ($value, $ids) {
                $handle = fopen($value->getPathname(), 'r');
                while (($line = fgets($handle)) !== false) {
                    if (! $this->tokenIdExists($ids->all(), $tokenId = trim($line))) {
                        $ids->push($tokenId);
                        yield $tokenId;
                    }
                }
                fclose($handle);
            });

            foreach ($tokens->chunk(10000) as $tokenIds) {
                $integers = $tokenIds->filter(fn ($val) => $this->integerRange($val) === false)->all();
                if ($integers) {
                    $exists = Token::whereIn('token_chain_id', $integers)
                        ->whereHas('collection', fn ($query) => $query->where('collection_chain_id', $this->collectionId))
                        ->exists();
                    if ($exists) {
                        $fail($this->message())->translate();

                        return;
                    }
                }

                $ranges = collect($tokenIds)->filter(fn ($val) => $this->integerRange($val) !== false)->all();
                foreach ($ranges as $range) {
                    [$from, $to] = $this->integerRange($range);
                    $exists = Token::whereBetween('token_chain_id', [(int) $from, (int) $to])
                        ->whereHas('collection', fn ($query) => $query->where('collection_chain_id', $this->collectionId))
                        ->exists();
                    if ($exists) {
                        $fail($this->message())->translate();
                    }
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
        return 'enjin-platform-beam::validation.tokens_doesnt_exist_in_collection';
    }
}
