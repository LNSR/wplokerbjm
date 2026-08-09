<?php

declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Shared\Log\Logger;

/**
 * Shared invoker mechanics used by BOTH the container-side lazy handlers and
 * the runtime instance handlers: the once/removal callback plumbing and the
 * named hook-args builder.
 *
 * @internal consumed via the container's AbstractLazyHookHandlerTrait (in-file)
 * and the runtime's RuntimeInstanceInvokerTrait (in-file) — the gate evaluation
 * and instance resolution stay domain-specific in each invoker file.
 */
trait HookInvokerTrait
{
    /** @var \Closure|null Callback that nukes this registration once consumed (once-hook) or the owner dies (lifetime scoping). */
    private ?\Closure $removeCallback = null;

    /** Whether the once-hook has been consumed (first fire reached gate evaluation). */
    private bool $consumed = false;

    /** Whether the removal callback has fired — idempotency guard. */
    private bool $removed = false;

    /**
     * Attach the removal callback (set by the owning registry) so the handler
     * can nuke its own registration when consumed (once) or when the owner
     * instance dies (runtime lifetime scoping).
     *
     * @param \Closure|null $callback Registry-side removal callback.
     */
    public function setRemoveCallback(?\Closure $callback): void
    {
        $this->removeCallback = $callback;
    }

    /**
     * Consume the once-hook: fire the removal callback exactly once.
     * Idempotent — the removal callback may already have run.
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
            Logger::error(static::class, 'Error removing once-hook ' . $this->label . ': ' . $e->getMessage());
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
        if ($this->hookArgNames === []) {
            return [];
        }

        return \array_combine(\array_slice($this->hookArgNames, 0, \count($args)), $args) ?: [];
    }

    /**
     * Value a filter must pass through (or null for actions) when the handler
     * or its gate fails.
     *
     * @param array<int, mixed> $args Hook arguments received at fire time.
     */
    private function filterPassthrough(array $args): mixed
    {
        return $this->type === 'filter' && array_key_exists(0, $args) ? $args[0] : null;
    }
}
