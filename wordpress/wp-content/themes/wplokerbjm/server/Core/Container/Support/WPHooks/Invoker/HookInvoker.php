<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    HookDebugEmitter,
    HookDebugIdentity,
    LiveDebugPhase,
    LiveUnregistrationPhase,
    LiveUnregistrationResultDTO,
    UnregistrationSource,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\Target\{
    ContainerMethodTarget,
    ContainerPropertyTarget,
    InvocationTarget,
    RuntimeMethodTarget,
    RuntimePropertyTarget,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookInvokerTrait;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * Single hook invoker: lifecycle, gates, dispatch, debug and cleanup for every
 * registration type. The only axis that varies is the {@see InvocationTarget}
 * strategy (container/runtime × method/property).
 */
final class HookInvoker
{
    use HookInvokerTrait;

    public readonly HookLoggerInterface $logger;

    public function __construct(
        private readonly HookInvokerConfig $config,
        private readonly InvocationTarget $target,
    ) {
        $this->logger = $config->logger;
        $this->label = $config->label;
        $this->type = $config->type;
        $this->once = $config->once;

        $this->removal = new HookRemovalController($config->label, $this->logger);
        $this->debugEmitter = new HookDebugEmitter(
            new HookDebugIdentity(
                label: $config->label,
                hook: $config->hookName,
                type: $config->type,
                priority: $config->priority,
                acceptedArgs: $config->acceptedArgs,
                once: $config->once,
                path: $config->path,
                class: $config->class,
                member: $config->member,
                target: $config->target,
                visibility: $config->visibility,
            ),
            $config->probes(),
            $config->planProvider,
            $target->ownerRef(),
            $config->logger,
        );
        $this->argumentBag = new HookArgumentBag($config->hookArgNames, $config->type);
    }

    public function __invoke(mixed ...$args): mixed
    {
        $startedAt = \hrtime(true);
        $this->emitLiveDebug(LiveDebugPhase::Dispatching, registered: true);

        try {
            $this->dispatchDepth++;

            $instance = $this->target->isLifetimeScoped() ? $this->target->resolveOwner() : null;
            if ($this->target->isLifetimeScoped() && $instance === null) {
                $this->consumeLifetimeAndClean();
                $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $startedAt, ownerAlive: false);
                return $this->filterPassthrough($args);
            }

            if ($this->once) {
                if ($this->consumed) {
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $startedAt, owner: $instance);
                    return $this->filterPassthrough($args);
                }
                $this->consumed = true;
                $this->consumeOnceAndClean();
            }

            if ($this->config->executeIf !== null && $this->config->planProvider !== null) {
                try {
                    $gatePassed = $this->config->planProvider->evaluateExecuteIf(
                        $this->config->executeIf,
                        $this->config->executeIfParams,
                        $this->config->label,
                        $instance !== null ? $this->target->targetClass($instance) : $this->config->class,
                        $this->buildHookArgs($args),
                        $this->variadicHookArgName(),
                    );
                } catch (\Throwable $e) {
                    $this->logger->error(
                        __METHOD__,
                        'executeIf gate declined for hook ' . $this->config->label . ' — handler skipped.',
                        [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]
                    );
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $startedAt, error: $e, owner: $instance);
                    return $this->filterPassthrough($args);
                }

                if (!$gatePassed) {
                    $this->logger->info(__METHOD__, 'Skipping hook ' . $this->config->label . ' — executeIf gate returned false.');
                    $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $startedAt, error: new \RuntimeException('executeIf returned false'), owner: $instance);
                    return $this->filterPassthrough($args);
                }
            }

            $instance ??= $this->target->resolveOwner();
            if ($instance === null) {
                $this->emitLiveOutcome(LiveDebugPhase::Skipped, 'skipped', $startedAt, ownerAlive: false);
                return $this->filterPassthrough($args);
            }

            $result = $this->target->invoke($instance, $args);

            SharedUtils::isDevelopment() && $this->logger->debug(
                __METHOD__,
                "Hook invoke {$this->config->label}" . ' executed successfully ' . ++$this->numberExecutions . ' times'
            );

            $this->emitLiveOutcome(LiveDebugPhase::Completed, 'completed', $startedAt, result: $result, owner: $instance);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error(__METHOD__, 'Error invoking hook ' . $this->config->label . ': ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->emitLiveOutcome(LiveDebugPhase::Failed, 'failed', $startedAt, error: $e);
            return $this->filterPassthrough($args);
        } finally {
            try {
                $this->consumeOnceAndClean();
            } finally {
                $this->dispatchDepth--;
            }
        }
    }

    public function initOnUnregisterDoCallback(): void
    {
        if ($this->config->onUnregisterDo === null || $this->config->planProvider === null) {
            return;
        }

        $this->onUnregisterDoCallback ??= $this->config->planProvider->resolveOnUnregisterDo(
            $this->config->onUnregisterDo,
            $this->config->onUnregisterDoParams,
            $this->config->label,
            $this->target->ownerRef() ?? $this->config->class,
        );
    }

    /**
     * Nuke the registration when its owning instance has been garbage-collected.
     */
    private function consumeLifetimeAndClean(): void
    {
        $this->removal->consumeLifetime(
            $this,
            function (bool $removed, int $elapsedUs): void {
                $this->emitLiveUnregistrationDebug(
                    LiveUnregistrationPhase::OwnerCollected,
                    registered: match ($this->config->type) {
                        'action' => (bool) \has_action($this->config->hookName, $this, $this->config->priority),
                        'filter' => (bool) \has_filter($this->config->hookName, $this, $this->config->priority),
                        default => false,
                    },
                    result: new LiveUnregistrationResultDTO(
                        requestedBy: UnregistrationSource::Lifetime,
                        removed: $removed,
                        failureCount: 0,
                        stillDispatching: $this->dispatching,
                        elapsedUs: $elapsedUs,
                    ),
                );
            },
        );
    }

    /* ---------------------------------------------------------------------
     | Factories — named constructor parity with the four legacy invokers
     | ------------------------------------------------------------------- */

    public static function containerMethod(
        ContainerInterface $container,
        WPHookPlanProvider $planProvider,
        string $hookName,
        int $priority,
        string $class,
        string $method,
        string $visibility = 'public',
        string $type = 'action',
        int $acceptedArgs = 0,
        ?\Closure $executeIf = null,
        array $executeIfParams = [],
        array $hookArgNames = [],
        bool $once = false,
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        ?\Closure $debugDeferredRegistration = null,
        array $debugDeferredRegistrationParams = [],
        ?\Closure $debugLiveRegistration = null,
        array $debugLiveRegistrationParams = [],
        ?\Closure $debugLiveUnregistration = null,
        array $debugLiveUnregistrationParams = [],
        HookLoggerInterface $logger = new NullHookLogger(),
    ): self {
        $label = $type . '::' . $hookName . '::' . (string) $priority . '::' . (string) $acceptedArgs . '::' . $class . '->' . $method;

        return new self(
            new HookInvokerConfig(
                hookName: $hookName,
                type: $type,
                priority: $priority,
                acceptedArgs: $acceptedArgs,
                once: $once,
                class: $class,
                member: $method,
                target: 'method',
                visibility: $visibility,
                path: 'container',
                label: $label,
                executeIf: $executeIf,
                executeIfParams: $executeIfParams,
                hookArgNames: $hookArgNames,
                onUnregisterDo: $onUnregisterDo,
                onUnregisterDoParams: $onUnregisterDoParams,
                debugDeferredRegistration: $debugDeferredRegistration,
                debugDeferredRegistrationParams: $debugDeferredRegistrationParams,
                debugLiveRegistration: $debugLiveRegistration,
                debugLiveRegistrationParams: $debugLiveRegistrationParams,
                debugLiveUnregistration: $debugLiveUnregistration,
                debugLiveUnregistrationParams: $debugLiveUnregistrationParams,
                planProvider: $planProvider,
                logger: $logger,
            ),
            new ContainerMethodTarget($container, $class, $method, $visibility),
        );
    }

    public static function containerProperty(
        ContainerInterface $container,
        WPHookPlanProvider $planProvider,
        string $hookName,
        int $priority,
        string $class,
        string $property,
        string $visibility = 'public',
        string $type = 'action',
        int $acceptedArgs = 0,
        ?\Closure $executeIf = null,
        array $executeIfParams = [],
        array $hookArgNames = [],
        bool $once = false,
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        ?\Closure $debugDeferredRegistration = null,
        array $debugDeferredRegistrationParams = [],
        ?\Closure $debugLiveRegistration = null,
        array $debugLiveRegistrationParams = [],
        ?\Closure $debugLiveUnregistration = null,
        array $debugLiveUnregistrationParams = [],
        HookLoggerInterface $logger = new NullHookLogger(),
    ): self {
        $label = $type . '::' . $hookName . '::' . (string) $priority . '::' . (string) $acceptedArgs . '::' . $class . '->' . $property;

        return new self(
            new HookInvokerConfig(
                hookName: $hookName,
                type: $type,
                priority: $priority,
                acceptedArgs: $acceptedArgs,
                once: $once,
                class: $class,
                member: $property,
                target: 'property',
                visibility: $visibility,
                path: 'container',
                label: $label,
                executeIf: $executeIf,
                executeIfParams: $executeIfParams,
                hookArgNames: $hookArgNames,
                onUnregisterDo: $onUnregisterDo,
                onUnregisterDoParams: $onUnregisterDoParams,
                debugDeferredRegistration: $debugDeferredRegistration,
                debugDeferredRegistrationParams: $debugDeferredRegistrationParams,
                debugLiveRegistration: $debugLiveRegistration,
                debugLiveRegistrationParams: $debugLiveRegistrationParams,
                debugLiveUnregistration: $debugLiveUnregistration,
                debugLiveUnregistrationParams: $debugLiveUnregistrationParams,
                planProvider: $planProvider,
                logger: $logger,
            ),
            new ContainerPropertyTarget($container, $class, $property, $visibility),
        );
    }

    public static function runtimeMethod(
        \WeakReference $instanceRef,
        string $hookName,
        int $priority,
        string $method,
        string $visibility = 'public',
        string $type = 'action',
        int $acceptedArgs = 0,
        ?\Closure $executeIf = null,
        ?WPHookPlanProvider $hookPlanProvider = null,
        array $executeIfParams = [],
        array $hookArgNames = [],
        bool $once = false,
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        ?\Closure $debugDeferredRegistration = null,
        array $debugDeferredRegistrationParams = [],
        ?\Closure $debugLiveRegistration = null,
        array $debugLiveRegistrationParams = [],
        ?\Closure $debugLiveUnregistration = null,
        array $debugLiveUnregistrationParams = [],
        HookLoggerInterface $logger = new NullHookLogger(),
    ): self {
        $instance = $instanceRef->get();
        $objectId = \spl_object_id($instance);

        $class = $instance instanceof ModuleClassHookMetadata ? $instance->parentClassString : $instance::class;
        $member = $instance instanceof ModuleClassHookMetadata ? $instance->parentProperty . '->' . $method : $method;

        $label = $instance instanceof ModuleClassHookMetadata
            ? $type . '::' . $hookName . '::' . (string) $priority . '::' . (string) $acceptedArgs . '::' . $instance->parentClassString . '#' . $objectId . '->' . $instance->parentProperty . '->' . $method
            : $type . '::' . $hookName . '::' . (string) $priority . '::' . (string) $acceptedArgs . '::' . $instance::class . '#' . $objectId . '->' . $method;

        return new self(
            new HookInvokerConfig(
                hookName: $hookName,
                type: $type,
                priority: $priority,
                acceptedArgs: $acceptedArgs,
                once: $once,
                class: $class,
                member: $member,
                target: 'method',
                visibility: $visibility,
                path: 'runtime',
                label: $label,
                executeIf: $executeIf,
                executeIfParams: $executeIfParams,
                hookArgNames: $hookArgNames,
                onUnregisterDo: $onUnregisterDo,
                onUnregisterDoParams: $onUnregisterDoParams,
                debugDeferredRegistration: $debugDeferredRegistration,
                debugDeferredRegistrationParams: $debugDeferredRegistrationParams,
                debugLiveRegistration: $debugLiveRegistration,
                debugLiveRegistrationParams: $debugLiveRegistrationParams,
                debugLiveUnregistration: $debugLiveUnregistration,
                debugLiveUnregistrationParams: $debugLiveUnregistrationParams,
                planProvider: $hookPlanProvider,
                logger: $logger,
            ),
            new RuntimeMethodTarget($instanceRef, $method, $visibility),
        );
    }

    public static function runtimeProperty(
        \WeakReference $instanceRef,
        WPHookPlanProvider $hookPlanProvider,
        string $hookName,
        int $priority,
        string $property,
        string $visibility = 'public',
        string $type = 'action',
        int $acceptedArgs = 0,
        ?\Closure $executeIf = null,
        array $executeIfParams = [],
        array $hookArgNames = [],
        bool $once = false,
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        ?\Closure $debugDeferredRegistration = null,
        array $debugDeferredRegistrationParams = [],
        ?\Closure $debugLiveRegistration = null,
        array $debugLiveRegistrationParams = [],
        ?\Closure $debugLiveUnregistration = null,
        array $debugLiveUnregistrationParams = [],
        HookLoggerInterface $logger = new NullHookLogger(),
    ): self {
        $instance = $instanceRef->get();
        $objectId = \spl_object_id($instance);

        $class = $instance instanceof ModuleClassHookMetadata ? $instance->parentClassString : $instance::class;
        $member = $instance instanceof ModuleClassHookMetadata ? $instance->parentProperty . '->' . $property : $property;

        $label = $instance instanceof ModuleClassHookMetadata
            ? $type . '::' . $hookName . '::' . (string) $priority . '::' . (string) $acceptedArgs . '::' . $instance->parentClassString . '#' . $objectId . '->' . $instance->parentProperty . '->' . $property
            : $type . '::' . $hookName . '::' . (string) $priority . '::' . (string) $acceptedArgs . '::' . $instance::class . '#' . $objectId . '->' . $property;

        return new self(
            new HookInvokerConfig(
                hookName: $hookName,
                type: $type,
                priority: $priority,
                acceptedArgs: $acceptedArgs,
                once: $once,
                class: $class,
                member: $member,
                target: 'property',
                visibility: $visibility,
                path: 'runtime',
                label: $label,
                executeIf: $executeIf,
                executeIfParams: $executeIfParams,
                hookArgNames: $hookArgNames,
                onUnregisterDo: $onUnregisterDo,
                onUnregisterDoParams: $onUnregisterDoParams,
                debugDeferredRegistration: $debugDeferredRegistration,
                debugDeferredRegistrationParams: $debugDeferredRegistrationParams,
                debugLiveRegistration: $debugLiveRegistration,
                debugLiveRegistrationParams: $debugLiveRegistrationParams,
                debugLiveUnregistration: $debugLiveUnregistration,
                debugLiveUnregistrationParams: $debugLiveUnregistrationParams,
                planProvider: $hookPlanProvider,
                logger: $logger,
            ),
            new RuntimePropertyTarget($instanceRef, $property, $visibility),
        );
    }
}
