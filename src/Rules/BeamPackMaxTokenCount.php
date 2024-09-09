<?php

namespace Enjin\Platform\Beam\Rules;

use Illuminate\Support\Arr;

class BeamPackMaxTokenCount extends MaxTokenCount
{
    protected function getInputTokens(): array
    {
        return collect(Arr::get($this->data, 'packs'))->flatMap(fn ($row) => Arr::get($row, 'tokens', []))->toArray();
    }
}
