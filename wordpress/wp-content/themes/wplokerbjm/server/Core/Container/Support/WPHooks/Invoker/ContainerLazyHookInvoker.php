<?php
namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{WPHookPlanProvider};

/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 * @phpstan-import-type CallableHookParams from WPHookPlanProvider
 */
final class ContainerLazyHookHandler
{
    public readonly string $label;

    /** @var Closure to invoke the method on any instance (for non-public methods) */
    private ?\Closure $invoker = null;

    /** Plan provider used for condition gates and hook-name resolution. */
    private readonly WPHookPlanProvider $planProvider;

    /** @var \Closure():void|null Callback invoked when a once-hook consumes its registration (self-removal). */
    private ?\Closure $removeCallback = null;

    /** True once the first fire has reached gate evaluation; the registration is spent. */
    private bool $consumed = false;

    /** True once the removal callback has been invoked (idempotency guard). */
    private bool $removed = false;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $method
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     * @param \Closure():bool|null $executeIf Gate evaluated before the hook fires; must return bool.
     * @param CallableHookParams $executeIfParams Pre-computed DI plan for the executeIf closure.
     * @param WPHookPlanProvider|null $hookPlanProvider Plan provider for condition/hook-name resolution.
     * @param bool $once When true, the registration removes itself after its first fire where the executeIf gate is evaluated (consume-on-any-evaluation).
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $method,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        private readonly array $hookArgNames = [],
        private readonly bool $once = false,
    ) {
        $this->label = $this->class . '::' . $this->method;
        $this->planProvider = $hookPlanProvider ?? new WPHookPlanProvider();

        if ($this->visibility !== 'public') {
            $methodName = $this->method;

            // Bind inside $class scope so $instance->privateMethod(...) works natively.
            // newThis=null means the closure is scoped but not bound to a specific instance.
            $this->invoker = \Closure::bind(
                static fn(object $instance, mixed ...$args): mixed => $instance->{$methodName}(...$args),
                null,
                $this->class,
            );
        }
    }

    /**
     * Set the callback invoked when this once-hook is consumed (registry self-removal).
     */
    public function setRemoveCallback(?\Closure $callback): void
    {
        $this->removeCallback = $callback;
    }

    /**
     * Consume a once-hook registration: fires the removal callback exactly once.
     */
    private function consumeOnce(): void
    {
        if (!$this->once || $this->removed || $this->removeCallback === null) {
            return;
        }
        $this->removed = true;
        try {
            ($this->removeCallback)();
        } catch (\Throwable $e) {
            Logger::error('ContainerLazyHookHandler', 'Error removing once-hook ' . $this->label . ': ' . $e->getMessage());
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        $hookArgs = $this->hookArgNames === []
            ? []
            : array_combine(array_slice($this->hookArgNames, 0, count($args)), $args);

        // Once-hooks: the first fire that reaches gate evaluation consumes the
        // registration, regardless of whether the gate passes, fails, or cannot
        // be resolved. Any later fire is a no-op (registration is already gone).
        if ($this->once) {
            if ($this->consumed) {
                return ($this->type === 'filter' && array_key_exists(0, $args)) ? $args[0] : null;
            }
            $this->consumed = true;
        }

        try {
            try {
                $gatePassed = $this->planProvider->evaluateExecuteIf($this->executeIf, $this->executeIfParams, $this->container, $this->label, $this->class, $hookArgs);
            } catch (\Throwable $e) {
                if (!$this->once) {
                    throw $e;
                }
                Logger::warning(
                    'ContainerLazyHookHandler',
                    'executeIf gate unresolvable for once-hook ' . $this->label . ' — treated as pass.'
                );
                $gatePassed = true;
            }

            if (!$gatePassed) {
                Logger::warning(
                    'ContainerLazyHookHandler',
                    'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                );
                $this->consumeOnce();
                return ($this->type === 'filter' && array_key_exists(0, $args)) ? $args[0] : null;
            }

            $instance = $this->container->get($this->class);

            $execute = $this->visibility === 'public'
                ? $instance->{$this->method}(...$args)
                : ($this->invoker)($instance, ...$args);
            SharedUtils::isDevelopment() && Logger::debug("ContainerLazyHookHandler", "Hook invoke {$this->label}");
            $this->consumeOnce();
            return $execute;
        } catch (\Throwable $e) {
            $this->consumeOnce();
            Logger::error('WPHooksContainerRegistry', 'Error invoking hook for class ' . $this->class . ' and method ' . $this->method . ': ' . $e->getMessage());
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
        }
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
 * @phpstan-import-type CallableHookParams from WPHookPlanProvider
 */
final class ContainerLazyPropertyHookHandler
{

    public readonly string $label;

    /** @var \Closure(object):mixed|null Closure to read the property on any instance (for non-public props) */
    private ?\Closure $reader = null;

    /** Plan provider used for condition gates and hook-name resolution. */
    private readonly WPHookPlanProvider $planProvider;

    /** @var \Closure():void|null Callback invoked when a once-hook consumes its registration (self-removal). */
    private ?\Closure $removeCallback = null;

    /** True once the first fire has reached gate evaluation; the registration is spent. */
    private bool $consumed = false;

    /** True once the removal callback has been invoked (idempotency guard). */
    private bool $removed = false;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $property
     * @param 'public'|'protected'|'private' $visibility
     * @param 'action'|'filter' $type
     * @param \Closure():bool|null $executeIf Gate evaluated before the hook fires; must return bool.
     * @param CallableHookParams $executeIfParams Pre-computed DI plan for the executeIf closure.
     * @param WPHookPlanProvider|null $hookPlanProvider Plan provider for condition/hook-name resolution.
     * @param bool $once When true, the registration removes itself after its first fire where the executeIf gate is evaluated (consume-on-any-evaluation).
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $property,
        private readonly string $visibility = 'public',
        private readonly string $type = 'action',
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        private readonly array $hookArgNames = [],
        private readonly bool $once = false,
    ) {
        $this->label = $this->class . '::$' . $this->property;
        $this->planProvider = $hookPlanProvider ?? new WPHookPlanProvider();

        if ($this->visibility !== 'public') {
            $propertyName = $this->property;

            // Bind inside $class scope so $instance->privateProp works natively.
            $this->reader = \Closure::bind(
                static fn(object $instance): mixed => $instance->{$propertyName},
                null,
                $this->class,
            );
        }
    }

    /**
     * Set the callback invoked when this once-hook is consumed (registry self-removal).
     */
    public function setRemoveCallback(?\Closure $callback): void
    {
        $this->removeCallback = $callback;
    }

    /**
     * Consume a once-hook registration: fires the removal callback exactly once.
     */
    private function consumeOnce(): void
    {
        if (!$this->once || $this->removed || $this->removeCallback === null) {
            return;
        }
        $this->removed = true;
        try {
            ($this->removeCallback)();
        } catch (\Throwable $e) {
            Logger::error('ContainerLazyPropertyHookHandler', 'Error removing once-hook ' . $this->label . ': ' . $e->getMessage());
        }
    }

    public function __invoke(mixed ...$args): mixed
    {
        $hookArgs = $this->hookArgNames === []
            ? []
            : array_combine(array_slice($this->hookArgNames, 0, count($args)), $args);

        // Once-hooks: the first fire that reaches gate evaluation consumes the
        // registration, regardless of whether the gate passes, fails, or cannot
        // be resolved. Any later fire is a no-op (registration is already gone).
        if ($this->once) {
            if ($this->consumed) {
                return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
            }
            $this->consumed = true;
        }

        try {
            try {
                $gatePassed = $this->planProvider->evaluateExecuteIf($this->executeIf, $this->executeIfParams, $this->container, $this->label, $this->class, $hookArgs);
            } catch (\Throwable $e) {
                if (!$this->once) {
                    throw $e;
                }
                Logger::warning(
                    'ContainerLazyPropertyHookHandler',
                    'executeIf gate unresolvable for once-hook ' . $this->label . ' — treated as pass.'
                );
                $gatePassed = true;
            }

            if (!$gatePassed) {
                Logger::warning(
                    'ContainerLazyHookHandler',
                    'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                );
                $this->consumeOnce();
                return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
            }

            $instance = $this->container->get($this->class);

            $callable = $this->visibility === 'public'
                ? $instance->{$this->property}
                : ($this->reader)($instance);

            $isInvokable = $callable instanceof \Closure || (is_object($callable) && method_exists($callable, '__invoke'));

            if (!$isInvokable || !is_callable($callable)) {
                throw new \RuntimeException(
                    "Property {$this->label} is not a valid callable or invokable object."
                );
            }
            SharedUtils::isDevelopment() && Logger::debug('WPHooksContainerRegistry', "Property hook {$this->label} invoked successfully.");
            $result = $callable(...$args);
            $this->consumeOnce();
            return $result;
        } catch (\Throwable $e) {
            $this->consumeOnce();
            Logger::error('WPHooksContainerRegistry', "Error invoking property hook {$this->label}: {$e->getMessage()}");
            // Filters must pass through the first argument; actions are fire-and-forget.
            return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}