<?php declare(strict_types=1);

namespace Tests;

use Phact\Event\Dispatcher;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Tests\Mocks\InvokableListener;
use Tests\Mocks\SimpleEvent;
use Tests\Mocks\StoppableEvent;

class DispatcherTest extends TestCase
{
    public function testCorrectlyPassesEventToListener(): void
    {
        $event = new SimpleEvent();

        $listener = $this->createMock(InvokableListener::class);
        $listener
            ->expects($this->once())
            ->method('__invoke')
            ->with($event);

        $listenerProviderMock = $this->createMock(ListenerProviderInterface::class);
        $listenerProviderMock
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener]);

        $dispatcher = new Dispatcher($listenerProviderMock);
        $dispatcher->dispatch($event);
    }

    public function testStoppedEventNotProvidedToNextListener(): void
    {
        $event = new StoppableEvent();

        $listener = $this->createMock(InvokableListener::class);
        $listener
            ->expects($this->once())
            ->method('__invoke')
            ->with($event)
            ->willReturnCallback(function (StoppableEvent $event) {
                $event->stopPropagation();
            });

        $listenerNeverReach = $this->createMock(InvokableListener::class);
        $listenerNeverReach
            ->expects($this->never())
            ->method('__invoke');

        $listenerProviderMock = $this->createMock(ListenerProviderInterface::class);
        $listenerProviderMock
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->with($event)
            ->willReturn([$listener, $listenerNeverReach]);

        $dispatcher = new Dispatcher($listenerProviderMock);
        $dispatcher->dispatch($event);
    }
}
