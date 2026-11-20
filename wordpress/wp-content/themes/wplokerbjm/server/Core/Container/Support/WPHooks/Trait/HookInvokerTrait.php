<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredActivationResultDTO,
    DeferredDebugPhase,
    DeferredRegistrationInfoDTO,
    DeferredRegistrationSnapshotDTO,
    HookDebugContext,
    LiveDebugPhase,
    LiveExecutionResultDTO,
    LiveRegistrationInfoDTO,
    LiveRegistrationSnapshotDTO,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{
    ContainerLazyHookInvoker,
    ContainerLazyPropertyHookInvoker,
    RuntimeInstanceHookInvoker,
    RuntimeInstancePropertyHookInvoker,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};
use WPLokerBJM\Shared\Log\Logger;

/**
 * Shared invoker mechanics used by BOTH the container-side lazy handlers and
 * the runtime instance handlers: the once/removal callback plumbing and the
 * named hook-args builder.
 * @mixin (ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker)
 */
trait HookInvokerTrait
{
    /** @var string pathway to callback for observability purpose */
    public readonly string $label;
    /** @var 'action'|'filter' */
    public readonly string $type;
    public readonly bool $once;
    /** Current hook callback execution */
    public private(set) int $numberExecutions = 0;
    public private(set) bool $dispatching = false;

    /** @var \Closure(): void  */
    public private(set) ?\Closure $onUnregisterDoCallback = null;

    /** 
     * @var (\Closure(static, bool = false): void)|null Callback that nukes this registration once consumed (once-hook) or the owner dies (lifetime scoping). 
     * @see WPHooksContainerRegistry::$removeEntry
     * @see WPHooksInstanceRegistry::$removeRuntimeHook
     */
    private ?\Closure $removeCallback = null;


    /** Whether the once-hook has been consumed (first fire reached gate evaluation). */
    private bool $consumed = false;

    /** Whether the removal callback has fired — idempotency guard. */
    private bool $removed = false;

    /** destruction state */
    public private(set) bool $isDestructed = false;

    /** template closure for caching stateless closure */
    private static ?\Closure $templateClosure = null;

    /** Debug descriptor parts, filled by each invoker constructor. */
    private string $debugClass = '';
    private string $debugMember = '';
    /** @var 'method'|'property'|'property-hook' */
    private string $debugTarget = 'method';

    /**
     * Emit one live-registration debug payload (registered/dispatching/...).
     *
     * No-op when the registration has no `debugLiveRegistration` probe.
     */
    public function emitLiveDebug(
        LiveDebugPhase $phase,
        bool $registered = true,
        ?LiveExecutionResultDTO $after = null,
        ?object $owner = null,
        bool $ownerAlive = true,
    ): void {
        $debug = $this->debugLiveRegistration ?? null;
        if ($debug === null) return;
        $provider = $this->debugProvider();
        if ($provider === null) return;

        try {
            $provider->invokeHookDebug(
                $debug,
                $this->debugLiveRegistrationParams,
                new LiveRegistrationInfoDTO(
                    phase: $phase,
                    context: $this->debugContext($owner),
                    before: new LiveRegistrationSnapshotDTO(
                        registered: $registered,
                        consumed: $this->consumed,
                        executions: $this->numberExecutions,
                        ownerAlive: $ownerAlive,
                    ),
                    after: $after,
                ),
                $this->label,
                $owner,
            );
        } catch (\Throwable $e) {
            Logger::error(__METHOD__, 'Failed emitting live debug payload for ' . $this->label, [
                'phase' => $phase->value,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Emit one deferred-lifecycle debug payload (queued/activation/...).
     *
     * No-op when the registration has no `debugDeferredRegistration` probe.
     */
    public function emitDeferredDebug(
        DeferredDebugPhase $phase,
        DeferredRegistrationSnapshotDTO $before,
        ?DeferredActivationResultDTO $after = null,
        ?object $owner = null,
        bool $ownerAlive = true,
    ): void {
        $debug = $this->debugDeferredRegistration ?? null;
        if ($debug === null) return;
        $provider = $this->debugProvider();
        if ($provider === null) return;

        try {
            $provider->invokeHookDebug(
                $debug,
                $this->debugDeferredRegistrationParams,
                new DeferredRegistrationInfoDTO(
                    phase: $phase,
                    context: $this->debugContext($owner),
                    before: $before,
                    after: $after,
                ),
                $this->label,
                $owner,
            );
        } catch (\Throwable $e) {
            Logger::error(__METHOD__, 'Failed emitting deferred debug payload for ' . $this->label, [
                'phase' => $phase->value,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Container invokers carry `$planProvider`; runtime invokers `$hookPlanProvider`.
     */
    private function debugProvider(): ?WPHookPlanProvider
    {
        if (property_exists($this, 'planProvider')) {
            /** @var WPHookPlanProvider $provider */
            $provider = $this->planProvider;
            return $provider;
        }

        /** @var WPHookPlanProvider|null $provider */
        $provider = $this->hookPlanProvider ?? null;
        return $provider;
    }

    /**
     * Emit a terminal live outcome (completed/failed/skipped) with timing.
     */
    private function emitLiveOutcome(
        LiveDebugPhase $phase,
        string $status,
        int $startedAt,
        mixed $result = null,
        ?\Throwable $error = null,
        ?object $owner = null,
        bool $ownerAlive = true,
    ): void {
        $this->emitLiveDebug(
            $phase,
            after: new LiveExecutionResultDTO(
                status: $status,
                elapsedUs: $this->elapsedUs($startedAt),
                returnType: $result !== null ? get_debug_type($result) : null,
                errorClass: $error !== null ? $error::class : null,
                errorMessage: $error?->getMessage(),
                executionsAfter: $this->numberExecutions,
            ),
            owner: $owner,
            ownerAlive: $ownerAlive,
        );
    }

    private function elapsedUs(int $startedAt): int
    {
        return (int) ((\hrtime(true) - $startedAt) / 1000);
    }

    /**
     * Build the stable identity block shared by every debug payload.
     */
    private function debugContext(?object $owner = null): HookDebugContext    {
        $isRuntime = property_exists($this, 'instanceRef');
        $owner ??= $isRuntime ? (($this->instanceRef)->get() ?? null) : null;

        $ownerClass = $this->debugClass;
        if ($ownerClass === '' && $owner !== null) {
            $ownerClass = $owner instanceof ModuleClassHookMetadata ? $owner->parentClassString : $owner::class;
        }

        return new HookDebugContext(
            path: $isRuntime ? 'runtime' : 'container',
            hook: $this->hookName,
            type: $this->type,
            priority: $this->priority,
            acceptedArgs: $this->acceptedArgs,
            once: $this->once,
            label: $this->label,
            class: $ownerClass !== '' ? $ownerClass : null,
            member: $this->debugMember !== '' ? $this->debugMember : null,
            target: $this->debugTarget,
            visibility: $this->visibility,
            ownerId: $owner !== null ? \spl_object_id($owner) : null,
        );
    }
    /**
     * Attach the scheduler removal callback (set by the owning registry) so the handler
     * can nuke its own registrations when consumed (once) or when the owner
     * instance dies (runtime lifetime scoping).
     *
     * @param (\Closure(static, bool = false): void) $callback Registry-side removal callback.
     */
    public function setCleanupCallback(?\Closure $callback): void
    {
        $this->removeCallback ??= $callback;
    }

    /**
     * @return void
     */
    abstract public function initOnUnregisterDoCallback(): void;

    public function __destruct()
    {
        $this->isDestructed = true;
        if (is_callable($this->onUnregisterDoCallback)) ($this->onUnregisterDoCallback)();
        if (is_callable($this->removeCallback)) ($this->removeCallback)($this, true);
        unset($this->onUnregisterDoCallback, $this->removeCallback);
    }

    /**
     * Consume the once-hook: fire the removal callback exactly once.
     * Idempotent — the removal callback may already have run.
     */
    private function consumeOnceAndClean(): void
    {
        if (!$this->once || $this->removed || $this->removeCallback === null) {
            return;
        }

        try {
            $this->removed = true;
            ($this->removeCallback)($this);
        } catch (\Throwable $e) {
            Logger::error(__METHOD__, 'Error removing once-hook ' . $this->label . ': ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Build named hook arguments for executeIf parameter resolution.
     *
     * @param array<int, mixed> $args Hook arguments received at fire time.
     *
     * @return array<string, mixed> Named args (empty when no names are known).
     */
    private function buildHookArgs(array $args): array
    {
        $names = $this->hookArgNames;
        if ($names === []) {
            return [];
        }

        if (is_string($names[0])) {
            return \array_combine(\array_slice($names, 0, \count($args)), $args) ?: [];
        }

        /** @var list<string> $paramNames */
        $paramNames = array_map(static fn(array $p): string => $p['name'], $names);

        $last = $names[\count($names) - 1];

        // Variadic is always the LAST parameter: forward the
        // whole remainder truthfully under its name — the caller decide what to do with it.
        if (($last['isVariadic'] ?? false)) {
            $headCount = \count($paramNames) - 1;
            $mapped = \array_combine(
                \array_slice($paramNames, 0, \min($headCount, \count($args))),
                \array_slice($args, 0, \min($headCount, \count($args)))
            ) ?: [];
            $mapped[$last['name']] = \array_slice($args, $headCount);

            return $mapped;
        }

        return \array_combine(\array_slice($paramNames, 0, \count($args)), $args) ?: [];
    }

    /**
     * The handler's variadic hook-argument name (plan-B descriptor form only).
     *
     * Null for the legacy `list<string>` form and for non-variadic handlers.
     * Used to splat the handler's variadic bucket into `...` executeIf/condition
     * parameters instead of injecting a single value.
     */
    private function variadicHookArgName(): ?string
    {
        $names = $this->hookArgNames;
        if ($names === [] || is_string($names[0])) {
            return null;
        }

        $last = $names[\count($names) - 1];
        if (!($last['isVariadic'] ?? false)) {
            return null;
        }

        return $last['name'];
    }

    /**
     * Value a filter must pass through (or null for actions) when the handler
     * or its gate fails.
     *
     * @param array<int, mixed> $args Hook arguments received at fire time.
     */
    private function filterPassthrough(array $args): mixed
    {
        return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
    }
}
