<?php declare(strict_types=1);

namespace Phact\Event;

use Phact\Event\Exception\EventException;
use Phact\Event\Exception\IncorrectListenerException;

interface ListenerAggregate
{
    /**
     * Append listener
     *
     * @param string|array|callable $listener
     * @param int $priority Listener priority, higher value - higher priority
     * @param string $eventClassName Class name for event match
     * @throws IncorrectListenerException on incorrect listener type or structure
     */
    public function addListener($listener, int $priority = 100, string $eventClassName = ''): void;
}