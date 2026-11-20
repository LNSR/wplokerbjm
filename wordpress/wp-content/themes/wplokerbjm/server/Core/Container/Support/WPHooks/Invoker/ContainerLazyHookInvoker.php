<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use Closure;
use HookType;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\HookRegistration;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{DebugProbes, HookDebugEmitter, HookDebugIdentity, LiveDebugPhase};
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

    public function initOnUnregisterDoCallback(): void
    {
        $this->onUnregisterDoCallback ??= $this->planProvider->resolveOnUnregisterDo($this->onUnregisterDo, $this->onUnregisterDoParams, $this->label, $this->class);
    }


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

        $debugStart = \hrtime(true);
        $this->emitLiveDebug(LiveDebugPhase::Dispatching, registered: $this->type === 'action'
            ? \has_action($this->hookName, $this, $this->priority)
            : \has_filter($this->hookName, $this, $this->priority));
        try {
            $this->dispatching = true;
            if ($this->once) {
                if ($this->consumed) {
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $debugStart);
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
                $this->consumeOnceAndClean();
            }
            if ($this->executeIf !== null) {
                try {
                    $hookArgs = $this->buildHookArgs($args);
                    $gatePassed = $this->planProvider->evaluateExecuteIf(
                        $this->executeIf,
                        $this->executeIfParams,
                        $this->label,
                        $this->class,
                        $hookArgs,
                        $this->variadicHookArgName()
                    );
                } catch (\RuntimeException $e) {
                    Logger::error(
                        __METHOD__,
                        'executeIf gate unresolvable for hook ' . $this->label . ' TREATED AS PASSED',
                        [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]
                    );
                    $gatePassed = true;
                    $this->emitLiveOutcome(LiveDebugPhase::Failed, 'failed', $debugStart, error: $e);
                    return $this->filterPassthrough($args);
                }

                if (!$gatePassed) {
                    Logger::info(
                        __METHOD__,
                        'Skipping hook ' . $this->label . ' — executeIf gate returned false.'
                    );
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $debugStart, error: new \RuntimeException('executeIf returned false'));
                    return $this->filterPassthrough($args);
                }
            }

            $instance = $this->container->get($this->class);
            $result = $this->invokeOn($instance, ...$args);

            SharedUtils::isDevelopment() && Logger::debug(__METHOD__, "Hook invoke {$this->label}" . ' executed successfully ' . ++$this->numberExecutions . ' times');
            $this->emitLiveOutcome(LiveDebugPhase::Completed, 'completed', $debugStart, result: $result, owner: $instance);
            return $result;
        } catch (\Throwable $e) {
            Logger::error(__METHOD__, "Error invoking hook {$this->label}: ", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->emitLiveOutcome(LiveDebugPhase::Failed, 'failed', $debugStart, error: $e);
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

    /** @var (\Closure(object, method-string, mixed...):mixed)|null Closure to invoke the method on the stored instance (for non-public methods) */
    private ?\Closure $invoker = null;
    /** @var array<class-string, (\Closure(object, method-string, mixed...):mixed)> */
    private static array $methodInvokerCache = [];
    /**
     * @param class-string $class
     * @param method-string $method
     * @param HookType['visibility'] $visibility
     * @param HookType['type'] $type
     * @param HookType['executeIf'] $executeIf
     * @param HookType['executeIfParams'] $executeIfParams
     * @param HookType['hookArgs'] $hookArgNames
     * @param HookType['once'] $once
     * @param HookType['onUnregisterDo'] $onUnregisterDo
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly WPHookPlanProvider $planProvider,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $class,
        public readonly string $method,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
        public readonly bool $once = false,
        private readonly ?\Closure $onUnregisterDo = null,
        private readonly array $onUnregisterDoParams = [],
        private readonly ?\Closure $debugDeferredRegistration = null,
        private readonly array $debugDeferredRegistrationParams = [],
        private readonly ?\Closure $debugLiveRegistration = null,
        private readonly array $debugLiveRegistrationParams = [],
        private readonly ?\Closure $debugLiveUnregistration = null,
        private readonly array $debugLiveUnregistrationParams = []
    ) {
        $this->label = $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $this->class . '->' . $this->method;
        $this->removal = new \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookRemovalController($this->label);
        $this->debugEmitter = new HookDebugEmitter(
            new HookDebugIdentity(
                label: $this->label,
                hook: $this->hookName,
                type: $this->type,
                priority: $this->priority,
                acceptedArgs: $this->acceptedArgs,
                once: $this->once,
                path: 'container',
                class: $this->class,
                member: $this->method,
                target: 'method',
                visibility: $this->visibility,
            ),
            new DebugProbes(
                deferred: $this->debugDeferredRegistration,
                deferredParams: $this->debugDeferredRegistrationParams,
                live: $this->debugLiveRegistration,
                liveParams: $this->debugLiveRegistrationParams,
                unregistration: $this->debugLiveUnregistration,
                unregistrationParams: $this->debugLiveUnregistrationParams,
            ),
            $this->planProvider,
            null,
        );
        $this->argumentBag = new \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookArgumentBag($this->hookArgNames, $this->type);

        if ($this->visibility !== 'public') {
            $this->invoker = self::$methodInvokerCache[$this->class] ??= \Closure::bind(
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

    /** @var (\Closure(object, string):mixed)|null */
    private ?\Closure $reader = null;
    /** @var array<class-string, (\Closure(object, property-string|property-hook-string):mixed)> */
    private static array $classPropertyClosureCache = [];

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param property-string $property
     * @param HookType['visibility'] $visibility
     * @param HookType['type'] $type
     * @param HookType['executeIf'] $executeIf
     * @param HookType['executeIfParams'] $executeIfParams
     * @param HookType['hookArgs'] $hookArgNames
     * @param HookType['once'] $once
     * @param HookType['onUnregisterDo'] $onUnregisterDo
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly WPHookPlanProvider $planProvider,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $class,
        public readonly string $property,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly array $executeIfParams = [],
        private readonly array $hookArgNames = [],
        public readonly bool $once = false,
        private readonly ?\Closure $onUnregisterDo = null,
        public readonly array $onUnregisterDoParams = [],
        private readonly ?\Closure $debugDeferredRegistration = null,
        private readonly array $debugDeferredRegistrationParams = [],
        private readonly ?\Closure $debugLiveRegistration = null,
        private readonly array $debugLiveRegistrationParams = [],
        private readonly ?\Closure $debugLiveUnregistration = null,
        private readonly array $debugLiveUnregistrationParams = []
    ) {
        $this->label = $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $this->class . '->' . $this->property;
        $this->removal = new \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookRemovalController($this->label);
        $this->debugEmitter = new HookDebugEmitter(
            new HookDebugIdentity(
                label: $this->label,
                hook: $this->hookName,
                type: $this->type,
                priority: $this->priority,
                acceptedArgs: $this->acceptedArgs,
                once: $this->once,
                path: 'container',
                class: $this->class,
                member: $this->property,
                target: 'property',
                visibility: $this->visibility,
            ),
            new DebugProbes(
                deferred: $this->debugDeferredRegistration,
                deferredParams: $this->debugDeferredRegistrationParams,
                live: $this->debugLiveRegistration,
                liveParams: $this->debugLiveRegistrationParams,
                unregistration: $this->debugLiveUnregistration,
                unregistrationParams: $this->debugLiveUnregistrationParams,
            ),
            $this->planProvider,
            null,
        );
        $this->argumentBag = new \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookArgumentBag($this->hookArgNames, $this->type);

        if ($this->visibility !== 'public') {
            $this->reader = self::$classPropertyClosureCache[$this->class] ??= \Closure::bind(
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
