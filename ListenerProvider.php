<?php declare(strict_types=1);

namespace Phact\Event;

use Closure;
use Phact\Event\Exception\IncorrectListenerException;
use Phact\Event\Exception\InvalidConfigurationException;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class ListenerProvider implements ListenerProviderInterface, ListenerAggregate
{
    /**
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * Attached listeners
     *
     * Structure:
     *
     * [
     *      eventClassName => [
     *          priority => [listener, listener, ...]
     *      ]
     * ]
     *
     * Example:
     *
     * [
     *      'MyApp\Events\PostAddedEvent' => [
     *          10 => [callable],
     *          100 => [callable, callable]
     *      ]
     * ]
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * Analyze listener with reflection
     *
     * @var bool
     */
    protected $analyzeListener;

    public function __construct($analyzeListener = true, ContainerInterface $container = null)
    {
        $this->analyzeListener = $analyzeListener;
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function addListener($listener, int $priority = 100, string $eventClassName = ''): void
    {
        $targetClasses = [];

        $listener = $this->resolveListener($listener);

        if ($this->analyzeListener) {
            $targetClasses[] = $this->resolveListenerTargetClass($listener);
        }

        if ($eventClassName !== '' ) {
            $targetClasses[] = $eventClassName;
        }

        $this->addListenerToClasses($listener, $priority, ...$targetClasses);
    }

    /**
     * @param string|array|callable $listener
     * @return callable
     * @throws InvalidConfigurationException|IncorrectListenerException
     */
    protected function resolveListener($listener): callable
    {
        $containerRequired = !is_callable($listener);
        $containerOptional = is_array($listener);
        $hasContainer = $this->container !== null;

        if ($containerRequired && !$hasContainer) {
            throw new InvalidConfigurationException('Please, provide container for usage non-callable listeners');
        }

        if ($hasContainer && ($containerRequired || $containerOptional)) {
            $listener = $this->resolveNonCallableListener($listener);
        }
        return $listener;
    }

    protected function resolveNonCallableListener($listener): callable
    {
        if (is_string($listener)) {
            return $this->resolveStringListener($listener);
        }

        if (is_array($listener)) {
            return $this->resolveArrayListener($listener);
        }

        throw new IncorrectListenerException('Unsupported type of listener');
    }

    protected function resolveElementFromContainer($id)
    {
        if ($this->container->has($id)) {
            return $this->container->get($id);
        }
        throw new IncorrectListenerException('Could not find listener in container');
    }

    protected function resolveStringListener(string $listener): callable
    {
        return $this->resolveElementFromContainer($listener);
    }

    protected function resolveArrayListener(array $listener): callable
    {
        if (!isset($listener[0], $listener[1])) {
            throw new IncorrectListenerException('Array listeners must contain 2 elements');
        }
        list($id, $method) = $listener;
        if (is_object($id)) {
            return $listener;
        }
        $object = $this->resolveElementFromContainer($id);
        return [$object, $method];
    }

    protected function addListenerToClasses(callable $listener, int $priority = 100, string ...$classNames): void
    {
        foreach ($classNames as $className) {
            $this->listeners[$className][$priority][] = $listener;
        }
    }

    protected function resolveListenerTargetClass(callable $callable): string
    {
        if (is_array($callable)) {
            $reflection = new ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_object($callable) && !$callable instanceof Closure) {
            $reflection = new ReflectionMethod($callable, '__invoke');
        } else {
            $reflection = new ReflectionFunction($callable);
        }

        return $this->resolveFunctionDependencyClass($reflection);
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     *
     * @return string
     * @throws IncorrectListenerException
     */
    protected function resolveFunctionDependencyClass(ReflectionFunctionAbstract $reflection): string
    {
        $params = $reflection->getParameters();
        if (!isset($params[0])) {
            throw new IncorrectListenerException('Event listener must accept an object');
        }

        $param = $params[0];

        $class = $param->getClass();
        if (!$class) {
            throw new IncorrectListenerException('Event listener must accept an object of a particular class');
        }

        return $class->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        yield from $this->getListenersForClass(get_class($event));
        yield from $this->getListenersForClass(...array_values(class_parents($event)));
        yield from $this->getListenersForClass(...array_values(class_implements($event)));
    }

    /**
     * Get listeners for class/classes
     *
     * @param string ...$classes
     * @return iterable
     */
    protected function getListenersForClass(string ...$classes): iterable
    {
        foreach ($classes as $class) {
            $listenersByPriority = $this->listeners[$class] ?? [];
            krsort($listenersByPriority);
            foreach ($listenersByPriority as $priority => $listeners) {
                yield from $listeners;
            }
        }
    }
}
