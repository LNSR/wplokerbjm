<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use InstanceHookMetadataData;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{DebugProbes, HookDebugEmitter, HookDebugIdentity, LiveDebugPhase, LiveUnregistrationPhase, LiveUnregistrationResultDTO, UnregistrationSource};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{WPHookPlanProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookInvokerTrait;

/**
 * Shared lifecycle + gate evaluation for runtime instance handlers
 * (method-based and property-based hooks).
 *
 * Cross-domain members (once-removal plumbing, named hook-args builder,
 * filter passthrough) come from the shared {@see HookInvokerTrait}; the
 * actual invocation step is domain-specific via {@see invokeOn()}.
 * @mixin RuntimeInstanceHookInvoker&RuntimeInstancePropertyHookInvoker
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

        $debugStart = \hrtime(true);
        $this->emitLiveDebug(LiveDebugPhase::Dispatching, registered: $this->type === 'action'
            ? \has_action($this->hookName, $this, $this->priority)
            : \has_filter($this->hookName, $this, $this->priority));
        try {
            $this->dispatching = true;

            $instance = $this->instanceRef->get();
            if ($instance === null) {
                // Owner instance is gone — nuke the hook (instance-lifetime scoping).
                $this->consumeLifetimeAndClean();
                $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $debugStart, ownerAlive: false);
                return $this->filterPassthrough($args);
            }

            if ($this->once) {
                if ($this->consumed) {
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $debugStart, owner: $instance);
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
                $this->consumeOnceAndClean();
            }

            if ($this->executeIf !== null) {
                try {
                    $gatePassed = $this->hookPlanProvider->evaluateExecuteIf($this->executeIf, $this->executeIfParams, $this->label, $instance, $this->buildHookArgs($args), $this->variadicHookArgName());
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
                    $this->emitLiveOutcome(LiveDebugPhase::Failed, 'failed', $debugStart, error: $e, owner: $instance);
                    return $this->filterPassthrough($args);
                }

                if (!$gatePassed) {
                    Logger::info(__METHOD__, 'Skipping hook ' . $this->label . ' — executeIf gate returned false.');
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $debugStart, error: new \RuntimeException('executeIf returned false'), owner: $instance);
                    return $this->filterPassthrough($args);
                }
            }

            $result = $this->invokeOn($instance, ...$args);

            SharedUtils::isDevelopment() && Logger::debug(__METHOD__, "Hook invoke {$this->label}" . ' executed successfully ' . ++$this->numberExecutions . ' times');

            $this->emitLiveOutcome(LiveDebugPhase::Completed, 'completed', $debugStart, result: $result, owner: $instance);
            return $result;
        } catch (\Throwable $e) {
            Logger::error(
                __METHOD__,
                'Error invoking hook ' . $this->label . ': ',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
            $this->emitLiveOutcome(LiveDebugPhase::Failed, 'failed', $debugStart, error: $e);
            return $this->filterPassthrough($args);
        } finally {
            $this->consumeOnceAndClean();
            $this->dispatching = false;
        }
    }

    public function initOnUnregisterDoCallback(): void
    {
        if ($this->instanceRef->get() === null) return;
        $this->onUnregisterDoCallback ??= $this->hookPlanProvider->resolveOnUnregisterDo(
            $this->onUnregisterDo,
            $this->onUnregisterDoParams,
            $this->label,
            $this->instanceRef
        );
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

        $startedAt = \hrtime(true);

        try {
            $this->removed = true;
            ($this->removeCallback)($this);
        } catch (\Throwable $e) {
            Logger::error(
                __METHOD__,
                'Error removing lifetime hook ' . $this->label . ': ',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
        } finally {
            $this->emitLiveUnregistrationDebug(
                LiveUnregistrationPhase::OwnerCollected,
                registered: $this->type === 'action'
                    ? \has_action($this->hookName, $this, $this->priority)
                    : \has_filter($this->hookName, $this, $this->priority),
                result: new LiveUnregistrationResultDTO(
                    requestedBy: UnregistrationSource::Lifetime,
                    removed: $this->removed,
                    failureCount: 0,
                    stillDispatching: $this->dispatching,
                    elapsedUs: $this->elapsedUs($startedAt),
                ),
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

    /** @var (\Closure(object, method-string, mixed...):mixed)|null Closure to invoke the method on the stored instance (for non-public methods) */
    private ?\Closure $invoker = null;
    /** @var array<class-string, (\Closure(object, method-string, mixed...):mixed)> */
    private static array $methodInvokerCache = [];

    /**
     * @template TClass
     * @param method-string<TClass> $method   The method name.
     * @param InstanceHookMetadataData['visibility'] $visibility
     * @param InstanceHookMetadataData['type'] $type
     * @param InstanceHookMetadataData['executeIf'] $executeIf Optional gate: invoked directly, must return bool.
     * @param InstanceHookMetadataData['executeIfParams'] $executeIfParams
     * @param InstanceHookMetadataData['hookArgNames'] $hookArgNames
     * @param InstanceHookMetadataData['once'] $once
     * @param InstanceHookMetadataData['onUnregisterDo'] $onUnregisterDo
     * @param InstanceHookMetadataData['onUnregisterDoParams'] $onUnregisterDoParams
     */
    public function __construct(
        private readonly \WeakReference $instanceRef,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $method,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        private readonly ?WPHookPlanProvider $hookPlanProvider = null,
        public readonly array $executeIfParams = [],
        public readonly array $hookArgNames = [],
        public readonly bool $once = false,
        private readonly ?\Closure $onUnregisterDo = null,
        public readonly array $onUnregisterDoParams = [],
        private readonly ?\Closure $debugDeferredRegistration = null,
        private readonly array $debugDeferredRegistrationParams = [],
        private readonly ?\Closure $debugLiveRegistration = null,
        private readonly array $debugLiveRegistrationParams = [],
        private readonly ?\Closure $debugLiveUnregistration = null,
        private readonly array $debugLiveUnregistrationParams = [],
    ) {
        $instance = $this->instanceRef->get();
        $objectId = \spl_object_id($instance);

        $this->label = $instance instanceof ModuleClassHookMetadata
            ? $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance->parentClassString . '#' . $objectId . '->' . $instance->parentProperty . '->' . $this->method
            : $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance::class . '#' . $objectId . '->' . $this->method;
        $this->debugEmitter = new HookDebugEmitter(
            new HookDebugIdentity(
                label: $this->label,
                hook: $this->hookName,
                type: $this->type,
                priority: $this->priority,
                acceptedArgs: $this->acceptedArgs,
                once: $this->once,
                path: 'runtime',
                class: $instance instanceof ModuleClassHookMetadata ? $instance->parentClassString : $instance::class,
                member: $instance instanceof ModuleClassHookMetadata ? $instance->parentProperty . '->' . $this->method : $this->method,
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
            $this->hookPlanProvider,
            $this->instanceRef,
        );
        $this->argumentBag = new \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookArgumentBag($this->hookArgNames, $this->type);

        if ($this->visibility !== 'public') {

            // Bind inside the instance's class scope so
            // $instance->privateMethod(...) works natively.
            $this->invoker = self::$methodInvokerCache[$instance::class] ??= \Closure::bind(
                static::$templateClosure ??= static fn(object $target, string $methodName, mixed ...$args): mixed => $target->{$methodName}(...$args),
                null,
                $instance,
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

    /** @var (\Closure(object, string):mixed)|null */
    private ?\Closure $reader = null;
    /** @var array<class-string, (\Closure(object, property-string|property-hook-string):mixed)> */
    private static array $classPropertyClosureCache = [];


    /**
     * @template TClass
     * @param property-string<TClass> $property   The property name.
     * @param InstanceHookMetadataData['executeIfParams'] $executeIfParams
     * @param InstanceHookMetadataData['visibility'] $visibility
     * @param InstanceHookMetadataData['type'] $type
     * @param InstanceHookMetadataData['executeIf'] $executeIf Optional gate: invoked directly, must return bool.
     * @param InstanceHookMetadataData['hookArgNames'] $hookArgNames
     * @param InstanceHookMetadataData['once'] $once
     * @param InstanceHookMetadataData['onUnregisterDo'] $onUnregisterDo
     * @param InstanceHookMetadataData['onUnregisterDoParams'] $onUnregisterDoParams
     */
    public function __construct(
        private readonly \WeakReference $instanceRef,
        private readonly WPHookPlanProvider $hookPlanProvider,
        public readonly string $hookName,
        public readonly int $priority,
        public readonly string $property,
        public readonly string $visibility = 'public',
        public readonly string $type = 'action',
        public readonly int $acceptedArgs = 0,
        private readonly ?\Closure $executeIf = null,
        public readonly array $executeIfParams = [],
        public readonly array $hookArgNames = [],
        public readonly bool $once = false,
        private readonly ?\Closure $onUnregisterDo = null,
        public readonly array $onUnregisterDoParams = [],
        private readonly ?\Closure $debugDeferredRegistration = null,
        private readonly array $debugDeferredRegistrationParams = [],
        private readonly ?\Closure $debugLiveRegistration = null,
        private readonly array $debugLiveRegistrationParams = [],
        private readonly ?\Closure $debugLiveUnregistration = null,
        private readonly array $debugLiveUnregistrationParams = [],
    ) {
        $instance = $this->instanceRef->get() ?? null;
        $objectId = \spl_object_id($instance);

        $this->label = $instance instanceof ModuleClassHookMetadata
            ? $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance->parentClassString . '#' . $objectId . '->' . $instance->parentProperty . '->' . $this->property
            : $this->type . '::' . $this->hookName . '::' . (string) $this->priority . '::' . (string) $this->acceptedArgs . '::' . $instance::class . '#' . $objectId . '->' . $this->property;
        $this->debugEmitter = new HookDebugEmitter(
            new HookDebugIdentity(
                label: $this->label,
                hook: $this->hookName,
                type: $this->type,
                priority: $this->priority,
                acceptedArgs: $this->acceptedArgs,
                once: $this->once,
                path: 'runtime',
                class: $instance instanceof ModuleClassHookMetadata ? $instance->parentClassString : $instance::class,
                member: $instance instanceof ModuleClassHookMetadata ? $instance->parentProperty . '->' . $this->property : $this->property,
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
            $this->hookPlanProvider,
            $this->instanceRef,
        );
        $this->argumentBag = new \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookArgumentBag($this->hookArgNames, $this->type);

        if ($this->visibility !== 'public') {
            // Bind inside the instance's class scope so
            // $instance->privateProp works natively.
            $this->reader = self::$classPropertyClosureCache[$instance::class] ??= \Closure::bind(
                static::$templateClosure ??= static fn(object $target, string $propertyName): mixed => $target->{$propertyName},
                null,
                $instance,
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
