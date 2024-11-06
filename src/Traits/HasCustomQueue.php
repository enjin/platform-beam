<?php

namespace Enjin\Platform\Beam\Traits;

trait HasCustomQueue
{
    protected function setQueue(): void
    {
        $this->onQueue(config('enjin-platform-beam.queue'));
    }

    public function viaQueue(): string
    {
        return config('enjin-platform-beam.queue');
    }
}
