<?php

namespace Enjin\Platform\Beam\Traits;

trait HasCustomQueue
{
    public function viaQueue(): string
    {
        return config('enjin-platform-beam.queue');
    }

    protected function setQueue(): void
    {
        $this->onQueue(config('enjin-platform-beam.queue'));
    }
}
