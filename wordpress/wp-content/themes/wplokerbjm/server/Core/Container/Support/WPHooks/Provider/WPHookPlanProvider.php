<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Provider;

use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use RuntimeException;

/**
 * Builds and resolves DI parameter plans for hook callables.
 *
 * Both the condition gate and the dynamic hook-name closure share one
 * resolution core: plans are pre-computed at scan time (via
 * {@see buildCallablePlan()}), exported to the hooks cache file, and resolved
 * from the container at hook-fire time — so the hot path never needs
 * reflection unless a plan is missing (stale cache / unexportable defaults).
 *
 * @phpstan-type CallableHookParams array{name: string, type: class-string|null, hasDefault: bool, default: mixed}
 * @phpstan-type CallablePlan array{isStatic: bool, scopeClass: class-string|null, params: array<int, CallableHookParams>}
 */
class WPHookPlanProvider
{
    /**
     * Scope-bound gate closures, memoized per (closure, target class) pair.
     *
     * Attribute-argument closures are always static (constant-expression
     * rule), so scope-only binds are immutable — each pair binds at most
     * once per provider lifetime. Entries are dropped automatically when
     * the source closure is garbage-collected.
     *
     * @var \WeakMap<\Closure, array<string, \Closure>>
     */
    private \WeakMap $boundClosureCache;

    public function __construct()
    {
        $this->boundClosureCache = new \WeakMap();
    }
    /**
     * Build the resolution plan for a callable closure.
     *
     * The plan carries BOTH parameter metadata (name, container class type,
     * default availability/value) AND closure-level metadata (isStatic,
     * scopeClass) captured once at scan time — so the hook-fire hot path
     * needs no reflection at all.
     *
     * Returns an empty-shaped plan for null callables or when a default value
     * cannot be safely exported to the cache (objects/resources) — in that
     * case the registry falls back to reflection at fire time.
     *
     * @return CallablePlan
     */
    public function buildCallablePlan(?\Closure $callable): array
    {
        $empty = ['isStatic' => true, 'scopeClass' => null, 'params' => []];

        if ($callable === null) {
            return $empty;
        }

        try {
            $reflect = new ReflectionFunction($callable);
            $params = [];

            foreach ($reflect->getParameters() as $param) {
                $type = $param->getType();
                $hasDefault = $param->isDefaultValueAvailable();
                $default = $hasDefault ? $param->getDefaultValue() : null;

                // Non-exportable defaults would break the cache — defer to reflection.
                if ($hasDefault && (is_object($default) || is_resource($default))) {
                    return $empty;
                }

                $params[] = [
                    'name' => $param->getName(),
                    'type' => ($type instanceof ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null,
                    'hasDefault' => $hasDefault,
                    'default' => $default,
                ];
            }

            return [
                'isStatic' => $reflect->isStatic(),
                'scopeClass' => $reflect->getClosureScopeClass()?->getName(),
                'params' => $params,
            ];
        } catch (ReflectionException) {
            return $empty;
        }
    }

    /**
     * Resolve the final hook name for a registration.
     *
     * A plain string is used as-is; a closure is resolved through the DI
     * container (via the plan provider) and must return a string.
     *
     * @throws \RuntimeException when the closure result is not a string
     */
    public function resolveHookName(string|\Closure $hook, ContainerInterface $container, array $hookParams, string $label): string
    {
        if (is_string($hook)) {
            return $hook;
        }

        $values = $this->resolveCallableParameters($hook, $hookParams, $container, $label);
        $name = $hook(...$values);

        if (!is_string($name)) {
            throw new \RuntimeException(
                'Hook name for ' . $label . ' must return string, got ' . get_debug_type($name)
            );
        }

        return $name;
    }

    /**
     * Resolve a callable's parameters from the container, using the
     * pre-computed plan when available and reflection as a fallback.
     *
     * @param CallablePlan $plan
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    public function resolveCallableParameters(\Closure $callable, array $plan, ContainerInterface $container, string $label, array $hookArgs = []): array
    {
        $params = $plan['params'] ?? [];

        if ($params !== []) {
            $values = [];
            foreach ($params as $param) {
                // Exact parameter-name match wins — hook arguments are injected
                // by name (e.g. `string $search` receives the handler's $search
                // argument), removing any scalar-ambiguity.
                if (array_key_exists($param['name'], $hookArgs)) {
                    $values[] = $hookArgs[$param['name']];
                    continue;
                }

                $values[] = $this->resolveCallableParam($param, $container, $label);
            }

            return $values;
        }

        return $this->resolveCallableFallback($callable, $container, $label);
    }

    /**
     * Evaluate an executeIf gate for a hook.
     *
     * A null gate always allows the hook. The closure must return a bool;
     * anything else raises a RuntimeException (callers catch and log it,
     * keeping the hook pipeline intact).
     *
     * When a target class is given, the closure is bound to that scope
     * before invocation: a non-static closure receives the resolved
     * service instance as `$this` (private/protected access), a static
     * closure is scope-bound only. `self` type-hints stay unresolvable —
     * use the direct class hint instead.
     *
     * @param CallablePlan $executeIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateExecuteIf(
        ?\Closure $executeIf,
        array $executeIfParams,
        ContainerInterface $container,
        string $label,
        ?string $targetClass = null,
        array $hookArgs = [],
    ): bool {
        if ($executeIf === null) {
            return true;
        }

        $executeIf = $this->bindToTarget($executeIf, $executeIfParams, $targetClass);

        // Fast path: zero-parameter gates are invoked directly,
        // without any reflection or DI resolution.
        if (($executeIfParams['params'] ?? []) === []) {
            try {
                $allowed = $executeIf();
                if (is_bool($allowed)) {
                    return $allowed;
                }
            } catch (\ArgumentCountError) {
                // Closure actually has parameters — fall through to resolution.
            }
        }

        $values = $this->resolveCallableParameters($executeIf, $executeIfParams, $container, $label, $hookArgs);
        $allowed = $executeIf(...$values);

        if (!is_bool($allowed)) {
            throw new RuntimeException(
                'executeIf for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }

    /**
     * Resolve a dynamic tag callable at registration time.
     *
     * The callable receives the target instance (typed params resolve from
     * the container; bindToTarget scope-binds for private access) and must
     * return the full resolved tag list.
     *
     * @param CallablePlan $plan
     *
     * @return array<int, string>
     *
     * @throws RuntimeException when the callable does not return an array
     */
    public function resolveTagCallable(
        \Closure $tagCallable,
        array $plan,
        ContainerInterface $container,
        string $label,
        ?string $targetClass = null,
    ): array {
        $tagCallable = $this->bindToTarget($tagCallable, $plan, $targetClass);
        $values = $this->resolveCallableParameters($tagCallable, $plan, $container, $label);
        $result = $tagCallable(...$values);

        if (!is_array($result)) {
            throw new RuntimeException(
                'Tag callable for ' . $label . ' must return array, got ' . get_debug_type($result)
            );
        }

        return $result;
    }

    /**
     * Evaluate a registerIf registration gate for a hook.
     *
     * Evaluated ONCE at registration time (not per fire). A null gate always
     * allows registration; the closure must return a bool.
     *
     * Scope binding behaves exactly like {@see evaluateExecuteIf()}: a
     * non-static gate closure receives the resolved service instance as
     * `$this`, a static one is scope-bound only.
     *
     * @param CallablePlan $registerIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateRegistrationGate(
        ?\Closure $registerIf,
        array $registerIfParams,
        ContainerInterface $container,
        string $label,
        ?string $targetClass = null,
    ): bool {
        if ($registerIf === null) {
            return true;
        }

        $registerIf = $this->bindToTarget($registerIf, $registerIfParams, $targetClass);

        // Fast path: zero-parameter gates are invoked directly.
        if (($registerIfParams['params'] ?? []) === []) {
            try {
                $allowed = $registerIf();
                if (is_bool($allowed)) {
                    return $allowed;
                }
            } catch (\ArgumentCountError) {
                // Closure actually has parameters — fall through to resolution.
            }
        }

        $values = $this->resolveCallableParameters($registerIf, $registerIfParams, $container, $label);
        $allowed = $registerIf(...$values);

        if (!is_bool($allowed)) {
            throw new RuntimeException(
                'registerIf gate for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }

    /**
     * Bind a gate closure to the target class scope before invocation.
     *
     * Plan-driven: uses the closure metadata captured at scan time, so no
     * reflection happens on the hot path.
     *
     * The scopeClass captured at scan time is deliberately NOT trusted as a
     * no-op signal: when the exported hooks cache is `require`d from inside
     * the scanner, `scopeClass` only used for unit test. Binding is ALWAYS applied
     * when a target class is given — the WeakMap memoizes one bound closure
     * per (closure, target) pair, so repeated evaluations (per fire, per
     * deferred activation) reuse it instead of re-allocating.
     *
     * Attribute-argument closures are static by rule, so only a scope-only
     * bind is needed (no instantiation).
     *
     * @param CallablePlan $plan
     */
    private function bindToTarget(\Closure $closure, array $plan, ?string $targetClass): \Closure
    {
        if ($targetClass === null) {
            return $closure;
        }

        if (!isset($this->boundClosureCache[$closure])) {
            $this->boundClosureCache[$closure] = [];
        }

        return $this->boundClosureCache[$closure][$targetClass]
            ??= \Closure::bind($closure, null, $targetClass);
    }

    /**
     * @param CallableHookParams $param
     *
     * @throws RuntimeException when the parameter cannot be resolved
     */
    private function resolveCallableParam(array $param, ContainerInterface $container, string $label): mixed
    {
        $type = $param['type'] ?? null;

        if (is_string($type) && $type !== '' && $container->has($type)) {
            return $container->get($type);
        }

        if (!empty($param['hasDefault'])) {
            return array_key_exists('default', $param) ? $param['default'] : null;
        }

        throw new RuntimeException(sprintf(
            'Cannot resolve parameter $%s for condition closure in %s: Class not in container and no default value set.',
            $param['name'] ?? 'unknown',
            $label
        ));
    }

    /**
     * Reflection-based parameter resolution for callables without a plan.
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    private function resolveCallableFallback(\Closure $callable, ContainerInterface $container, string $label): array
    {
        $reflect = new ReflectionFunction($callable);
        $values = [];

        foreach ($reflect->getParameters() as $param) {
            $type = $param->getType();
            $resolved = false;

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($container->has($className)) {
                    $values[] = $container->get($className);
                    $resolved = true;
                }
            }

            if (!$resolved) {
                if ($param->isDefaultValueAvailable()) {
                    $values[] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException(sprintf(
                        'Cannot resolve parameter $%s for condition closure in %s: Class not in container and no default value set.',
                        $param->getName(),
                        $label
                    ));
                }
            }
        }

        return $values;
    }
}
