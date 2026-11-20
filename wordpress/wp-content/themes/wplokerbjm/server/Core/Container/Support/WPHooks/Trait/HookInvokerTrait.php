<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredActivationResultDTO,
    DeferredDebugPhase,
    DeferredRegistrationSnapshotDTO,
    LiveDebugPhase,
    LiveExecutionResultDTO,
    LiveUnregistrationPhase,
    LiveUnregistrationResultDTO,
    UnregistrationSource,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{
    ContainerLazyHookInvoker,
    ContainerLazyPropertyHookInvoker,
    RuntimeInstanceHookInvoker,
    RuntimeInstancePropertyHookInvoker,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};

/**
 * Shared invoker mechanics used by BOTH the container-side lazy handlers and
 * the runtime instance handlers: the once/removal callback plumbing and the
 * named hook-args builder.
 * @mixin (\WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker|ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker)
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
     * Removal/cleanup lifecycle collaborator, initialized by each invoker constructor.
     */
    private readonly \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookRemovalController $removal;

    /** Whether the once-hook has been consumed (first fire reached gate evaluation). */
    private bool $consumed = false;

    /** destruction state */
    public private(set) bool $isDestructed = false;

    /** template closure for caching stateless closure */
    private static ?\Closure $templateClosure = null;

    /**
     * Per-invoker debug collaborator, initialized by each invoker constructor.
     */
    private readonly \WPLokerBJM\Core\Container\Support\WPHooks\Debug\HookDebugEmitter $debugEmitter;

    /**
     * Emit one live-registration debug payload (registered/dispatching/...).
     */
    public function emitLiveDebug(
        LiveDebugPhase $phase,
        bool $registered = true,
        ?LiveExecutionResultDTO $after = null,
        ?object $owner = null,
        bool $ownerAlive = true,
    ): void {
        if (!$this->debugEmitter->hasLive()) return;
        $this->debugEmitter->live($this->debugState(), $phase, $registered, $after, $owner, $ownerAlive);
    }

    /**
     * Emit one deferred-lifecycle debug payload (queued/activation/...).
     */
    public function emitDeferredDebug(
        DeferredDebugPhase $phase,
        DeferredRegistrationSnapshotDTO $before,
        ?DeferredActivationResultDTO $after = null,
        ?object $owner = null,
        bool $ownerAlive = true,
    ): void {
        if (!$this->debugEmitter->hasDeferred()) return;
        $this->debugEmitter->deferred($this->debugState(), $phase, $before, $after, $owner, $ownerAlive);
    }

    /**
     * Emit one unregistration/lifecycle debug payload.
     */
    public function emitLiveUnregistrationDebug(
        LiveUnregistrationPhase $phase,
        bool $registered = true,
        ?LiveUnregistrationResultDTO $result = null,
    ): void {
        if (!$this->debugEmitter->hasUnregistration()) return;
        $this->debugEmitter->unregistration($this->debugState(), $phase, $registered, $result);
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
        if (!$this->debugEmitter->hasLive()) return;
        $this->debugEmitter->outcome($this->debugState(), $phase, $status, $startedAt, $result, $error, $owner, $ownerAlive);
    }

    private function debugState(): \WPLokerBJM\Core\Container\Support\WPHooks\Debug\HookDebugState
    {
        return new \WPLokerBJM\Core\Container\Support\WPHooks\Debug\HookDebugState(
            consumed: $this->consumed,
            executions: $this->numberExecutions,
            dispatching: $this->dispatching,
        );
    }

    private function elapsedUs(int $startedAt): int
    {
        return \WPLokerBJM\Core\Container\Support\WPHooks\Debug\HookDebugEmitter::elapsedUs($startedAt);
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
        $this->removal->setCleanupCallback($callback);
    }

    /**
     * @return void
     */
    abstract public function initOnUnregisterDoCallback(): void;

    public function __destruct()
    {
        if ($this->isDestructed) {
            return;
        }
        $this->isDestructed = true;

        $this->removal->release(
            $this,
            $this->onUnregisterDoCallback,
            function (bool $removed, int $elapsedUs): void {
                $this->emitLiveUnregistrationDebug(
                    LiveUnregistrationPhase::ProxyReleased,
                    registered: true,
                    result: new LiveUnregistrationResultDTO(
                        requestedBy: UnregistrationSource::Destructor,
                        removed: $removed,
                        failureCount: 0,
                        stillDispatching: $this->dispatching,
                        elapsedUs: $elapsedUs,
                    ),
                );
            },
        );

        unset($this->onUnregisterDoCallback);
    }

    /**
     * Consume the once-hook: fire the removal callback exactly once.
     * Idempotent — the removal callback may already have run.
     */
    private function consumeOnceAndClean(): void
    {
        $this->removal->consumeOnce(
            $this->once,
            $this,
            function (bool $removed, int $elapsedUs): void {
                $this->emitLiveUnregistrationDebug(
                    LiveUnregistrationPhase::OnceConsumed,
                    registered: true,
                    result: new LiveUnregistrationResultDTO(
                        requestedBy: UnregistrationSource::Once,
                        removed: $removed,
                        failureCount: 0,
                        stillDispatching: $this->dispatching,
                        elapsedUs: $elapsedUs,
                    ),
                );
            },
        );
    }

    /**
     * Build named hook arguments for executeIf parameter resolution.
     *
     * @param array<int, mixed> $args Hook arguments received at fire time.
     *
     * @return array<string, mixed> Named args (empty when no names are known).
     */
    /**
     * Per-invoker argument shaping, initialized by each invoker constructor.
     */
    private readonly \WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookArgumentBag $argumentBag;

    private function buildHookArgs(array $args): array
    {
        return $this->argumentBag->named($args);
    }

    private function variadicHookArgName(): ?string
    {
        return $this->argumentBag->variadicName();
    }

    private function filterPassthrough(array $args): mixed
    {
        return $this->argumentBag->passthrough($args);
    }
}
