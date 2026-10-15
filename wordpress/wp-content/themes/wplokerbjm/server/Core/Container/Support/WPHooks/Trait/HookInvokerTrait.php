<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{
    ContainerLazyHookInvoker,
    ContainerLazyPropertyHookInvoker,
    RuntimeInstanceHookInvoker,
    RuntimeInstancePropertyHookInvoker,
    RuntimeCallableHookInvoker
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};
use WPLokerBJM\Shared\Log\Logger;

/**
 * Shared invoker mechanics used by BOTH the container-side lazy handlers and
 * the runtime instance handlers: the once/removal callback plumbing and the
 * named hook-args builder.
 * @mixin (ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker)
 * @internal consumed via the container's AbstractLazyHookHandlerTrait (in-file)
 * and the runtime's RuntimeInstanceInvokerTrait (in-file) — the gate evaluation
 * and instance resolution stay domain-specific in each invoker file.
 */
trait HookInvokerTrait
{
    /** @var string pathway to callback for observability purpose */
    public readonly string $label;
    /** @var 'action'|'filter' */
    public readonly string $type;
    public readonly bool $once;
    public private(set) int $numberExecutions = 0;
    /** Current hook callback execution */
    public private(set) bool $dispatching = false;

    /** 
     * @var (\Closure(static): void)|null Callback that nukes this registration once consumed (once-hook) or the owner dies (lifetime scoping). 
     * @see WPHooksContainerRegistry::$removeOnceEntry
     * @see WPHooksInstanceRegistry::$removeRuntimeHook
     */
    private ?\Closure $removeCallback = null;

    /** Whether the once-hook has been consumed (first fire reached gate evaluation). */
    private bool $consumed = false;

    /** Whether the removal callback has fired — idempotency guard. */
    private bool $removed = false;
    /** template closure for caching stateless closure */
    private static ?\Closure $templateClosure = null;

    /**
     * Attach the scheduler removal callback (set by the owning registry) so the handler
     * can nuke its own registrations when consumed (once) or when the owner
     * instance dies (runtime lifetime scoping).
     *
     * @param \Closure(static): void $callback Registry-side removal callback.
     */
    public function setCleanupCallback(?\Closure $callback): void
    {
        $this->removeCallback ??= $callback;
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
            ($this->removeCallback)($this);
            $this->removed = true;
        } catch (\Throwable $e) {
            Logger::error(static::class, 'Error removing once-hook ' . $this->label . ': ', [
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
