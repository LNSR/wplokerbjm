<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\HookRegistration;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{WPHookPlanProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookInvokerTrait;

/**
 * Common lifecycle & gate evaluation logic for lazy container hook handlers.
 * @mixin ContainerLazyHookInvokerTrait&ContainerLazyHookInvoker Trait
 */
trait ContainerLazyHookInvokerTrait
{
    use HookInvokerTrait;
    /**
     * Invoke the actual hook callback on the (alive) owner instance.
     *
     * @param object $instance The resolved owner instance.
     * @param mixed  ...$args  Hook arguments received at fire time.
     */
    abstract private function invokeOn(object $instance, mixed ...$args): mixed;

    /**
     * Common __invoke execution pipeline wrapping gate checks, invocation, and error handling.
     */
    private function executeHook(array $args): mixed
    {

        try {
            $this->dispatching = true;
            $hookArgs = $this->buildHookArgs($args);

            if ($this->once) {
                if ($this->consumed) {
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
                $this->consumeOnceAndClean();
            }

            try {
                $gatePassed = $this->planProvider->evaluateExecuteIf(
                    $this->executeIf,
                    $this->executeIfParams,
                    $this->container,
                    $this->label,
                    $this->class,
                    $hookArgs,
                    $this->variadicHookArgName()
                );
            } catch (\Throwable $e) {
                if (!$this->once) {
                    throw $e;
                }
                Logger::info(
                    static::class,
                    'executeIf gate unresolvable for once-hook ' . $this->label . ' — treated as pass.',
                    [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]
                );
                $gatePassed = true;
            }

            if (!$gatePassed) {
                Logger::info(
                    static::class,
                    'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                );
                return $this->filterPassthrough($args);
            }

            $instance = $this->container->get($this->class);
            $result = $this->invokeOn($instance, ...$args);

            SharedUtils::isDevelopment() && Logger::debug(static::class, "Hook invoke {$this->label}" . ' executed successfully ' . ++$this->numberExecutions . ' times');
            return $result;
        } catch (\Throwable $e) {
            Logger::error(static::class, "Error invoking hook {$this->label}: ", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->filterPassthrough($args);
        } finally {
            $this->consumeOnceAndClean();
            $this->dispatching = false;
        }
    }
}

/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 * @phpstan-import-type HookType from HookRegistration
 */
final class ContainerLazyHookInvoker
{
    use ContainerLazyHookInvokerTrait;

    /** @var \Closure|null */
    private ?\Closure $invoker = null;

    /** Plan provider used for condition gates and hook-name resolution. */
    private readonly WPHookPlanProvider $planProvider;
    /**
     * @template TClass
     * @param class-string<TClass> $class
     * @param method-string<TClass> $method
     * @param HookType['visibility'] $visibility
     * @param HookType['type'] $type
     * @param HookType['executeIf'] $executeIf
     * @param HookType['executeIfParams'] $executeIfParams
     * @param HookType['hookArgs'] $hookArgNames
     * @param HookType['once'] $once
     */
    public function __construct(
        private readonly ContainerInterface $container,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $class,
        public readonly string $method,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        private readonly array $hookArgNames = [],
        public readonly bool $once = false,
    ) {
        $this->label = $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $this->class . '->' . $this->method;
        $this->planProvider = $hookPlanProvider ?? new WPHookPlanProvider();

        if ($this->visibility !== 'public') {
            $this->invoker = \Closure::bind(
                static::$templateClosure ??= static fn(object $instance, string $methodName, mixed ...$args): mixed => $instance->{$methodName}(...$args),
                null,
                $this->class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        return $this->executeHook($args);
    }

    private function invokeOn(object $instance, mixed ...$args): mixed
    {
        return $this->visibility === 'public'
            ? $instance->{$this->method}(...$args)
            : ($this->invoker)($instance, $this->method, ...$args);
    }
}

/**
 * Lazy hook handler for property closures.
 *
 * Reads the closure from a property at hook-fire time, using a pre-bound
 * Closure when the property is protected or private.
 *
 * Unlike raw closures, this is a named class that appears in debugging
 * tools and WordPress can match it by instance identity (spl_object_hash)
 * for remove_action()/remove_filter().
 * @phpstan-import-type HookType from HookRegistration
 */
final class ContainerLazyPropertyHookInvoker
{
    use ContainerLazyHookInvokerTrait;

    /** @var \Closure(object, string):mixed|null */
    private ?\Closure $reader = null;

    /** Plan provider used for condition gates and hook-name resolution. */
    private readonly WPHookPlanProvider $planProvider;

    /**
     * @template TClass
     * @param ContainerInterface $container
     * @param class-string<TClass> $class
     * @param property-string<TClass> $property
     * @param HookType['visibility'] $visibility
     * @param HookType['type'] $type
     * @param HookType['executeIf'] $executeIf
     * @param HookType['executeIfParams'] $executeIfParams
     * @param HookType['hookArgs'] $hookArgNames
     * @param HookType['once'] $once
     */
    public function __construct(
        private readonly ContainerInterface $container,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $class,
        public readonly string $property,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        private readonly array $hookArgNames = [],
        public readonly bool $once = false,
    ) {
        $this->label = $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $this->class . '->' . $this->property;
        $this->planProvider = $hookPlanProvider ?? new WPHookPlanProvider();

        if ($this->visibility !== 'public') {
            $this->reader = \Closure::bind(
                static::$templateClosure ??= static fn(object $instance, string $propertyName): mixed => $instance->{$propertyName},
                null,
                $this->class,
            );
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        return $this->executeHook($args);
    }

    private function invokeOn(object $instance, mixed ...$args): mixed
    {
        $callable = $this->visibility === 'public'
            ? $instance->{$this->property}
            : ($this->reader)($instance, $this->property);

        $isInvokable = $callable instanceof \Closure || (is_object($callable) && method_exists($callable, '__invoke'));

        if (!$isInvokable || !is_callable($callable)) {
            throw new \RuntimeException("Property {$this->label} is not a valid callable or invokable object.");
        }

        return $callable(...$args);
    }
}
