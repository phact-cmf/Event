<?php declare(strict_types=1);

namespace Tests;

use Phact\Event\Exception\IncorrectListenerException;
use Phact\Event\Exception\InvalidConfigurationException;
use Phact\Event\ListenerProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Tests\Mocks\InvokableListener;
use Tests\Mocks\MethodListener;
use Tests\Mocks\MethodNotArgsListener;
use Tests\Mocks\MethodNotObjectListener;
use Tests\Mocks\OtherEvent;
use Tests\Mocks\SimpleEvent;
use Tests\Mocks\StaticMethodListener;

class ListenerProviderTest extends TestCase
{
    public function testSimple()
    {
        $this->assertTrue(true);
    }

    public function testNotFoundListenersOnIncorrectEventRequired(): void
    {
        $targetEvent = new SimpleEvent();

        $listener = function (OtherEvent $event) {};

        $provider = new ListenerProvider();
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        $countOfFoundListeners = 0;
        foreach ($listeners as $foundListener) {
            $countOfFoundListeners++;
        }
        $this->assertEquals(0, $countOfFoundListeners);
    }

    public function testListenerAsCallableFunction(): void
    {
        $targetEvent = new SimpleEvent();

        $listener = function (SimpleEvent $event) {};

        $provider = new ListenerProvider();
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testListenerAsCallableFunctionWithEventClassName(): void
    {
        $targetEvent = new SimpleEvent();

        $listener = function (OtherEvent $event) {};

        $provider = new ListenerProvider();
        $provider->addListener($listener, 100, SimpleEvent::class);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testWithoutAnalyzeArgs(): void
    {
        $targetEvent = new SimpleEvent();

        $listener = function ($event) {};

        $provider = new ListenerProvider(false);
        $provider->addListener($listener, 100, SimpleEvent::class);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testCorrectPriorityOrder(): void
    {
        $targetEvent = new SimpleEvent();

        $highPriorityListener = function (OtherEvent $event) {};
        $middlePriorityListener = function (OtherEvent $event) {};
        $lowPriorityListener = function (OtherEvent $event) {};

        $provider = new ListenerProvider();
        $provider->addListener($middlePriorityListener, 100, SimpleEvent::class);
        $provider->addListener($lowPriorityListener, 10, SimpleEvent::class);
        $provider->addListener($highPriorityListener, 500, SimpleEvent::class);

        $listeners = $provider->getListenersForEvent($targetEvent);
        $counter = 0;
        foreach ($listeners as $targetListener) {
            switch ($counter) {
                case 0:
                    $this->assertSame($highPriorityListener, $targetListener);
                    break;
                case 1:
                    $this->assertSame($middlePriorityListener, $targetListener);
                    break;
                case 2:
                    $this->assertSame($lowPriorityListener, $targetListener);
                    break;
            }

            $counter++;
        }
    }

    public function testInvokableClassListenerWithContainer(): void
    {
        $listener = new InvokableListener();

        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->expects($this->once())
            ->method('has')
            ->with(InvokableListener::class)
            ->willReturn(true);
        $containerMock
            ->expects($this->once())
            ->method('get')
            ->with(InvokableListener::class)
            ->willReturn($listener);

        $targetEvent = new SimpleEvent();

        $provider = new ListenerProvider(true, $containerMock);
        $provider->addListener(InvokableListener::class);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testInvokableContainerIdListenerWithContainer(): void
    {
        $listener = new InvokableListener();

        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->expects($this->once())
            ->method('has')
            ->with('invokable')
            ->willReturn(true);
        $containerMock
            ->expects($this->once())
            ->method('get')
            ->with('invokable')
            ->willReturn($listener);

        $targetEvent = new SimpleEvent();

        $provider = new ListenerProvider(true, $containerMock);
        $provider->addListener('invokable');

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testListenerArrayObjectAndMethod(): void
    {
        $targetEvent = new SimpleEvent();

        $methodListener = new MethodListener();

        $listener = [$methodListener, 'event'];

        $provider = new ListenerProvider();
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testListenerArrayObjectAndMethodWithContainer(): void
    {
        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->expects($this->never())
            ->method('has');
        $containerMock
            ->expects($this->never())
            ->method('get');

        $targetEvent = new SimpleEvent();

        $methodListener = new MethodListener();

        $listener = [$methodListener, 'event'];

        $provider = new ListenerProvider(true, $containerMock);
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testListenerArrayClassAndStaticMethodWithoutContainer(): void
    {
        $targetEvent = new SimpleEvent();

        $listener = [StaticMethodListener::class, 'event'];

        $provider = new ListenerProvider();
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertEquals($listener, $targetListener);
        }
    }

    public function testListenerArrayClassAndMethodWithContainer(): void
    {
        $methodListener = new MethodListener();

        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->expects($this->once())
            ->method('has')
            ->with(MethodListener::class)
            ->willReturn(true);
        $containerMock
            ->expects($this->once())
            ->method('get')
            ->with(MethodListener::class)
            ->willReturn($methodListener);

        $targetEvent = new SimpleEvent();

        $listener = [MethodListener::class, 'event'];

        $provider = new ListenerProvider(true, $containerMock);
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertSame([$methodListener, 'event'], $targetListener);
        }
    }

    public function testListenerArrayContainerIdAndMethodWithContainer(): void
    {
        $methodListener = new MethodListener();

        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->expects($this->once())
            ->method('has')
            ->with('listener')
            ->willReturn(true);
        $containerMock
            ->expects($this->once())
            ->method('get')
            ->with('listener')
            ->willReturn($methodListener);

        $targetEvent = new SimpleEvent();

        $listener = ['listener', 'event'];

        $provider = new ListenerProvider(true, $containerMock);
        $provider->addListener($listener);

        $listeners = $provider->getListenersForEvent($targetEvent);
        foreach ($listeners as $targetListener) {
            $this->assertSame([$methodListener, 'event'], $targetListener);
        }
    }

    public function testExceptionOnRequiredNotProvidedContainerForListener(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $provider = new ListenerProvider();
        $provider->addListener(InvokableListener::class);
    }

    public function testExceptionOnIncorrectTypeOfListener(): void
    {
        $this->expectException(IncorrectListenerException::class);

        $provider = new ListenerProvider(true, $this->createMock(ContainerInterface::class));
        $provider->addListener(123);
    }

    public function testExceptionOnNotFoundListenerInContainer(): void
    {
        $this->expectException(IncorrectListenerException::class);

        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->expects($this->once())
            ->method('has')
            ->with('listener')
            ->willReturn(false);

        $listener = ['listener', 'event'];

        $provider = new ListenerProvider(true, $containerMock);
        $provider->addListener($listener);
    }

    public function testExceptionOnIncorrectArrayListener(): void
    {
        $this->expectException(IncorrectListenerException::class);

        $provider = new ListenerProvider(true, $this->createMock(ContainerInterface::class));
        $provider->addListener([InvokableListener::class]);
    }

    public function testExceptionOnNoArgsListener(): void
    {
        $this->expectException(IncorrectListenerException::class);

        $provider = new ListenerProvider(true);
        $provider->addListener([new MethodNotArgsListener(), 'event']);
    }

    public function testExceptionOnNoClassArgsListener(): void
    {
        $this->expectException(IncorrectListenerException::class);

        $provider = new ListenerProvider(true);
        $provider->addListener([new MethodNotObjectListener(), 'event']);
    }
}
