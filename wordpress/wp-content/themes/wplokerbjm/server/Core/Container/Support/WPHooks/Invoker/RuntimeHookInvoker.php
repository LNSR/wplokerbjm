<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{RuntimeWPHookProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookInvokerTrait;

/**
 * Shared lifecycle + gate evaluation for runtime instance handlers
 * (method-based and property-based hooks).
 *
 * Cross-domain members (once-removal plumbing, named hook-args builder,
 * filter passthrough) come from the shared {@see HookInvokerTrait}; the
 * actual invocation step is domain-specific via {@see invokeOn()}.
 * @mixin RuntimeInstanceHookInvoker&RuntimeInstancePropertyHookInvoker&RuntimeCallableHookInvoker
 */
trait RuntimeInstanceInvokerTrait
{
    use HookInvokerTrait;

    /**
     * Invoke the actual hook callback on the (alive) owner instance.
     *
     * @param object $instance The resolved owner instance.
     * @param mixed  ...$args  Hook arguments received at fire time.
     */
    abstract private function invokeOn(object $instance, mixed ...$args): mixed;

    public function __invoke(mixed ...$args): mixed
    {

        try {
            $this->dispatching = true;

            $instance = $this->instanceRef->get();
            if ($instance === null) {
                // Owner instance is gone — nuke the hook (instance-lifetime scoping).
                $this->consumeLifetimeAndClean();
                return $this->filterPassthrough($args);
            }

            if ($this->once) {
                if ($this->consumed) {
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
                $this->consumeOnceAndClean();
            }

            if ($this->executeIf !== null) {
                $allowed = $this->hookPlanProvider !== null
                    ? $this->hookPlanProvider->evaluateRuntimeExecuteIf($this->executeIf, $this->executeIfParams, $this->label, $instance, $this->buildHookArgs($args), $this->variadicHookArgName())
                    : ($this->executeIf)();
                if (!is_bool($allowed)) {
                    throw new \RuntimeException(
                        'Condition for ' . $this->label . ' must return bool, got ' . get_debug_type($allowed)
                    );
                }

                Logger::debug(static::class, 'executeIf for ' . $this->label . ': ' . ($allowed ? 'PASS' : 'FAIL'));

                if ($allowed === false) {
                    Logger::warning(
                        static::class,
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    return $this->filterPassthrough($args);
                }
            }

            $result = $this->invokeOn($instance, ...$args);

            SharedUtils::isDevelopment() && Logger::debug(static::class, "Hook invoke {$this->label}" . ' executed ' . ++$this->numberExecutions . ' times');

            return $result;
        } catch (\Throwable $e) {
            Logger::error(
                static::class,
                'Error invoking hook ' . $this->label . ': ',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
            return $this->filterPassthrough($args);
        } finally {
            $this->consumeOnceAndClean();
            $this->dispatching = false;
        }
    }

    /**
     * Nuke the registration when its owning instance has been garbage-collected
     * (instance-lifetime scoping). Idempotent — shares the $removed guard with
     * the once flow.
     */
    private function consumeLifetimeAndClean(): void
    {
        if ($this->removed || $this->removeCallback === null) {
            return;
        }

        $this->removed = true;

        try {
            ($this->removeCallback)($this);
        } catch (\Throwable $e) {
            Logger::error(
                static::class,
                'Error removing lifetime hook ' . $this->label . ': ',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
        }
    }
}


/**
 * Runtime hook handler — invocable object that holds a direct instance reference.
 *
 * Unlike ContainerLazyHookInvoker (which resolves the service from the container at
 * hook-fire time), this handler is bound to an already-instantiated object.
 * Designed for use with WPHooksInstanceRegistry for anonymous class hooks
 * that cannot be discovered by the file-based WPHooksScanner.
 *
 * WordPress can match this by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 */
final class RuntimeInstanceHookInvoker
{
    use RuntimeInstanceInvokerTrait;

    /** @var \Closure|null Closure to invoke the method on the stored instance (for non-public methods) */
    private ?\Closure $invoker = null;

    /** @var \WeakReference<object> Weak reference to the owner instance — keeps it collectible (instance-lifetime scoping). */
    private readonly \WeakReference $instanceRef;

    /**
     * @template TClass
     * @param object<TClass> $instance The object instance to invoke methods on.
     * @param method-string<TClass> $method   The method name.
     * @param InstanceHookMetadataData['visibility'] $visibility
     * @param InstanceHookMetadataData['type'] $type
     * @param InstanceHookMetadataData['executeIf'] $executeIf Optional gate: invoked directly, must return bool.
     * @param InstanceHookMetadataData['executeIfParams'] $executeIfParams
     * @param InstanceHookMetadataData['hookArgNames'] $hookArgNames
     * @param InstanceHookMetadataData['once'] $once
     */
    public function __construct(
        object $instance,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $method,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly ?RuntimeWPHookProvider $hookPlanProvider = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
        public readonly bool $once = false,
    ) {
        // Keep the owner collectible: only a weak reference is retained, so
        // the instance can be garbage-collected while the hook is registered.
        // On death the hook nukes itself (instance-lifetime scoping).
        $this->instanceRef = \WeakReference::create($instance);

        $this->label = $instance instanceof ModuleClassHookMetadata
            ? $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance->getParentClass() . '->' . $instance->parentProperty . '->' . $this->method
            : $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance::class . '->' . $this->method;

        if ($this->visibility !== 'public') {

            // Bind inside the instance's class scope so
            // $instance->privateMethod(...) works natively.
            $this->invoker = \Closure::bind(
                static::$templateClosure ??= static fn(object $target, string $methodName, mixed ...$args): mixed => $target->{$methodName}(...$args),
                null,
                $instance::class,
            );
        }
    }


    private function invokeOn(object $instance, mixed ...$args): mixed
    {
        return $this->visibility === 'public'
            ? $instance->{$this->method}(...$args)
            : ($this->invoker)($instance, $this->method, ...$args);
    }
}

/**
 * Runtime property hook handler — invocable object that holds a direct instance reference.
 *
 * Reads the property value (a Closure or invokable object) at hook-fire time
 * and invokes it. Designed for use with WPHooksInstanceRegistry for anonymous
 * class property hooks that cannot be discovered by the file-based scanner.
 *
 * WordPress can match this by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 */
final class RuntimeInstancePropertyHookInvoker
{
    use RuntimeInstanceInvokerTrait;

    /** @var \Closure(object, string):mixed|null */
    private ?\Closure $reader = null;

    /** @var \WeakReference<object> Weak reference to the owner instance — keeps it collectible (instance-lifetime scoping). */
    private readonly \WeakReference $instanceRef;


    /**
     * @template TClass
     * @param object<TClass> $instance   The object instance whose property holds the callable.
     * @param property-string<TClass> $property   The property name.
     * @param InstanceHookMetadataData['executeIfParams'] $executeIfParams
     * @param InstanceHookMetadataData['visibility'] $visibility
     * @param InstanceHookMetadataData['type'] $type
     * @param InstanceHookMetadataData['executeIf'] $executeIf Optional gate: invoked directly, must return bool.
     * @param InstanceHookMetadataData['hookArgNames'] $hookArgNames
     * @param InstanceHookMetadataData['once'] $once
     */
    public function __construct(
        object $instance,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $property,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly ?RuntimeWPHookProvider $hookPlanProvider = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
        public readonly bool $once = false,
    ) {
        // Keep the owner collectible: only a weak reference is retained, so
        // the instance can be garbage-collected while the hook is registered.
        // On death the hook nukes itself (instance-lifetime scoping).
        $this->instanceRef = \WeakReference::create($instance);

        $this->label = $instance instanceof ModuleClassHookMetadata
            ? $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance->getParentClass() . '->' . $instance->parentProperty . '->' . $this->property
            : $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance::class . '->' . $this->property;

        if ($this->visibility !== 'public') {
            // Bind inside the instance's class scope so
            // $instance->privateProp works natively.
            $this->reader = \Closure::bind(
                static::$templateClosure ??= static fn(object $target, string $propertyName): mixed => $target->{$propertyName},
                null,
                $instance::class,
            );
        }
    }

    /**
     * Invoke the property's callable with the given arguments.
     */
    private function invokeOn(object $instance, mixed ...$args): mixed
    {
        $callable = $this->visibility === 'public'
            ? $instance->{$this->property}
            : ($this->reader)($instance, $this->property);

        if (!\is_callable($callable)) {
            throw new \RuntimeException(
                "Property {$this->label} is not a valid callable."
            );
        }

        return $callable(...$args);
    }
}

/**
 * Invocable wrapper for callables registered manually on the runtime registry.
 *
 * Used by WPHooksInstanceRegistry::registerAction()/registerFilter() so the
 * callback (closure, array-callable, invokable object) can capture the
 * surrounding scope directly — no container resolution involved. An optional
 * condition closure is invoked directly before the callback and must return
 * bool.
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 */
final class RuntimeCallableHookInvoker
{
    use HookInvokerTrait;

    /**
     * @param callable $callback  Callable invoked when the hook fires.
     * @param InstanceHookMetadataData['executeIf']   $executeIf Optional gate: invoked directly, must return bool.
     * @param InstanceHookMetadataData['type'] $type
     * @param InstanceHookMetadataData['once'] $once      When true, the registration removes itself after its first
     *                                   fire where the executeIf gate is evaluated (consume-on-any-evaluation).
     */
    public function __construct(
        private readonly mixed $callback,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        public readonly bool $once = false,
    ) {
        $this->label = $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . (is_array($callback)
            ? get_debug_type($callback[0]) . '::' . $callback[1] : ($callback instanceof \Closure ? 'closure:' . spl_object_hash($callback)
                : get_debug_type($callback)));
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $this->dispatching = true;

            if ($this->once) {
                if ($this->consumed) {
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
                $this->consumeOnceAndClean();
            }

            if ($this->executeIf !== null) {
                $allowed = ($this->executeIf)();
                if (!is_bool($allowed)) {
                    throw new \RuntimeException(
                        'Condition for ' . $this->label . ' must return bool, got ' . get_debug_type($allowed)
                    );
                }

                Logger::debug('RuntimeCallableHookInvoker', 'executeIf for ' . $this->label . ': ' . ($allowed ? 'PASS' : 'FAIL'));

                if ($allowed === false) {
                    Logger::warning(
                        'RuntimeCallableHookInvoker',
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    return $this->filterPassthrough($args);
                }
            }

            $callback = $this->callback;

            $result = $callback(...$args);

            SharedUtils::isDevelopment() && Logger::debug(static::class, "Hook invoke {$this->label}" . ' executed ' . ++$this->numberExecutions . ' times');
            return $result;
        } catch (\Throwable $e) {
            Logger::error(
                'RuntimeCallableHookInvoker',
                'Error invoking hook ' . $this->label . ': ',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->filterPassthrough($args);
        } finally {
            $this->consumeOnceAndClean();
            $this->dispatching = false;
        }
    }
}
