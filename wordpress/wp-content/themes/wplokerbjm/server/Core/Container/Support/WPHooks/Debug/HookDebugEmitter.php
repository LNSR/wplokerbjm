<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};

/**
 * Per-invoker debug emission collaborator.
 *
 * Owns the probe payload construction (context, registration snapshot and
 * outcome) while the invoker keeps its mutable execution state and passes an
 * immutable {@see HookDebugState} snapshot per emission. Emitters are no-ops
 * when the matching probe is null — callers can use the `has*()` guards to
 * avoid even building the state.
 */
final class HookDebugEmitter
{
    public function __construct(
        private readonly HookDebugIdentity $identity,
        private readonly DebugProbes $probes,
        private readonly ?WPHookPlanProvider $provider,
        private readonly ?WeakReference $ownerRef,
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {}

    public function hasLive(): bool
    {
        return $this->probes->live !== null && $this->provider !== null;
    }

    public function hasDeferred(): bool
    {
        return $this->probes->deferred !== null && $this->provider !== null;
    }

    public function hasUnregistration(): bool
    {
        return $this->probes->unregistration !== null && $this->provider !== null;
    }

    public function live(
        HookDebugState $state,
        LiveDebugPhase $phase,
        bool $registered = true,
        ?LiveExecutionResultDTO $after = null,
        ?object $owner = null,
        ?bool $ownerAlive = null,
    ): void {
        if (!$this->hasLive()) return;

        $owner = $this->resolveOwner($owner);

        $this->emit($this->probes->live, $this->probes->liveParams, new LiveRegistrationInfoDTO(
            phase: $phase,
            context: $this->context($owner),
            before: $this->snapshot($state, $registered, $ownerAlive),
            after: $after,
        ), $owner, ownerOptional: false);
    }

    public function deferred(
        HookDebugState $state,
        DeferredDebugPhase $phase,
        DeferredRegistrationSnapshotDTO $before,
        ?DeferredActivationResultDTO $after = null,
        ?object $owner = null,
        ?bool $ownerAlive = null,
        ?DeferredActivationSource $source = null,
    ): void {
        if (!$this->hasDeferred()) return;

        $owner = $this->resolveOwner($owner);

        $this->emit($this->probes->deferred, $this->probes->deferredParams, new DeferredRegistrationInfoDTO(
            phase: $phase,
            context: $this->context($owner),
            before: $before,
            source: $source,
            after: $after,
        ), $owner, ownerOptional: false);
    }

    public function unregistration(
        HookDebugState $state,
        LiveUnregistrationPhase $phase,
        bool $registered = true,
        ?LiveUnregistrationResultDTO $result = null,
    ): void {
        if (!$this->hasUnregistration()) return;

        $owner = $this->resolveOwner(null);

        $this->emit($this->probes->unregistration, $this->probes->unregistrationParams, new LiveUnregistrationInfoDTO(
            phase: $phase,
            context: $this->context($owner),
            before: $this->snapshot($state, $registered, $owner !== null || $this->ownerRef === null),
            result: $result,
        ), $owner, ownerOptional: true);
    }

    /**
     * Emit a terminal live outcome (completed/failed/skipped) with timing.
     */
    public function outcome(
        HookDebugState $state,
        LiveDebugPhase $phase,
        string $status,
        int $startedAt,
        mixed $result = null,
        ?\Throwable $error = null,
        ?object $owner = null,
        bool $ownerAlive = true,
    ): void {
        if (!$this->hasLive()) return;

        $this->live(
            $state,
            $phase,
            registered: true,
            after: new LiveExecutionResultDTO(
                status: $status,
                elapsedUs: self::elapsedUs($startedAt),
                returnType: $result !== null ? get_debug_type($result) : null,
                errorClass: $error !== null ? $error::class : null,
                errorMessage: $error?->getMessage(),
                executionsAfter: $state->executions,
            ),
            owner: $owner,
            ownerAlive: $ownerAlive,
        );
    }

    public static function elapsedUs(int $startedAt): int
    {
        return (int) ((\hrtime(true) - $startedAt) / 1000);
    }

    /**
     * @param DeferredRegistrationInfoDTO|LiveRegistrationInfoDTO|LiveUnregistrationInfoDTO $dto
     * @param null|object $owner
     * @param bool $ownerOptional
     * @return void
     */
    private function emit(
        ?\Closure $probe,
        array $plan,
        object $dto,
        ?object $owner,
        bool $ownerOptional,
    ): void {
        if ($probe === null || $this->provider === null) return;

        try {
            $this->provider->invokeHookDebug(
                $probe,
                $plan,
                $dto,
                $this->identity->label,
                $owner,
                ownerOptional: $ownerOptional,
            );
        } catch (\Throwable $e) {
            $this->logger->error(__METHOD__, 'Failed emitting debug payload for ' . $this->identity->label, [
                'dto' => $dto::class,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    private function resolveOwner(?object $owner): ?object
    {
        return $owner ?? $this->ownerRef?->get();
    }

    private function snapshot(HookDebugState $state, bool $registered, ?bool $ownerAlive = null): LiveRegistrationSnapshotDTO
    {
        return new LiveRegistrationSnapshotDTO(
            registered: $registered,
            consumed: $state->consumed,
            executions: $state->executions,
            ownerAlive: $ownerAlive ?? ($this->ownerRef === null || $this->ownerRef->get() !== null),
        );
    }

    private function context(?object $owner): HookDebugContext
    {
        return new HookDebugContext(
            path: $this->identity->path,
            hook: $this->identity->hook,
            type: $this->identity->type,
            priority: $this->identity->priority,
            acceptedArgs: $this->identity->acceptedArgs,
            once: $this->identity->once,
            label: $this->identity->label,
            class: $this->identity->class,
            member: $this->identity->member,
            target: $this->identity->target,
            visibility: $this->identity->visibility,
            ownerId: $owner !== null ? \spl_object_id($owner) : null,
        );
    }
}
