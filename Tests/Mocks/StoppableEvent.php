<?php declare(strict_types=1);

namespace Tests\Mocks;

use Psr\EventDispatcher\StoppableEventInterface;

class StoppableEvent extends SimpleEvent implements StoppableEventInterface
{
    protected $stopped = false;

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
