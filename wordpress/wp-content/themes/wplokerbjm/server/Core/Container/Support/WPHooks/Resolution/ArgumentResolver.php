<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Resolution;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\CallableHookParams;
use ReflectionFunction;
use ReflectionNamedType;
use RuntimeException;

/**
 * Runtime argument resolution for hook callables.
 *
 * Given a pre-computed plan (or, for stale/missing plans, reflection) resolves
 * parameters from hook arguments by name, the live instance for `self`, the DI
 * container by type, then the declared default. Also memoizes scope-bound
 * closures per (closure, target class).
 *
 * @phpstan-import-type CallablePlan from \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider
 */
final class ArgumentResolver
{
    /**
     * Scope-bound closures, memoized per (closure, target class) pair.
     *
     * @var \WeakMap<\Closure, array<class-string, \Closure>>
     */
    private \WeakMap $boundClosureCache;

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->boundClosureCache = new \WeakMap();
    }

    /**
     * Bind a closure to the target class scope before invocation.
     *
     * @param CallablePlan $plan
     */
    public function bindToTarget(\Closure $closure, array $plan, ?string $targetClass): \Closure
    {
        if ($targetClass === null) {
            return $closure;
        }

        if (!isset($this->boundClosureCache[$closure])) $this->boundClosureCache[$closure] = [];

        return $this->boundClosureCache[$closure][$targetClass]
            ??= \Closure::bind($closure, null, $targetClass);
    }

    /**
     * Resolve a callable's parameters from hook args → instance → container → default.
     *
     * @param CallablePlan $plan
     * @return array<int, mixed>
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    public function resolveCallableParameters(
        \Closure $callable,
        array $plan,
        string $label = '',
        array $hookArgs = [],
        ?object $instanceTarget = null,
        ?string $variadicHookArg = null
    ): array {
        $params = $plan['params'] ?? [];

        if ($params === []) return $this->resolveCallableFallback($callable, $label, $instanceTarget);

        $values = [];
        foreach ($params as $param) {
            // A variadic closure parameter mirrors the HANDLER's variadic
            // bucket (the name must match $variadicHookArg): splat the
            // whole remainder, letting the caller dissect it manually.
            // An unmatched variadic parameter captures nothing — it never
            // falls back to the container.
            if (($param->isVariadic ?? false)) {
                if (
                    $variadicHookArg !== null
                    && $param->name === $variadicHookArg
                    && isset($hookArgs[$param->name])
                    && is_array($hookArgs[$param->name])
                ) {
                    array_push($values, ...$hookArgs[$param->name]);
                }
                continue;
            }

            // Exact parameter-name match wins — hook arguments are injected
            // by name (e.g. `string $search` receives the handler's $search
            // argument), removing any scalar-ambiguity.
            if (isset($param->name) && array_key_exists($param->name, $hookArgs)) {
                $values[] = $hookArgs[$param->name];
                continue;
            }

            /**
             * `self` and `static` parameters are the hook owner, not a
             * container lookup: the supplied live instance must win even when
             * the service is transient. `?self` without an owner resolves to
             * null instead of constructing a replacement service.
             */
            if (($param->isSelf ?? false) && $instanceTarget !== null) {
                $values[] = $instanceTarget;
                continue;
            }

            if (($param->isSelf ?? false) && ($param->allowsNull ?? false)) {
                $values[] = null;
                continue;
            }

            $values[] = $this->resolveCallableParam($param, $label);
        }

        return $values;
    }

    /**
     * Resolve a single plan parameter from the container or its default.
     *
     * @param CallableHookParams $param
     *
     * @throws RuntimeException when the parameter cannot be resolved
     */
    public function resolveCallableParam(CallableHookParams $param, string $label = ''): mixed
    {
        $type = $param->type;

        if (is_string($type) && $type !== '' && $this->container->has($type)) {
            return $this->container->get($type);
        }

        if (!empty($param->hasDefault)) {
            return $param->default ?? null;
        }

        throw new RuntimeException(sprintf(
            'Cannot resolve parameter $%s for condition closure in %s: Class not in container and no default value set.',
            $param->name ?? 'unknown',
            $label
        ));
    }

    /**
     * Reflection-based fallback for callables without a plan.
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    private function resolveCallableFallback(\Closure $callable, string $label = '', ?object $instanceTarget = null): array
    {
        $reflect = new ReflectionFunction($callable);
        $values = [];

        foreach ($reflect->getParameters() as $param) {
            $type = $param->getType();
            $isSelf = $type instanceof ReflectionNamedType
                && \in_array($type->getName(), ['self', 'static'], true);

            // The live owner wins for self/static, even when the scope class
            // is registered as a transient container service.
            if ($isSelf && $instanceTarget !== null) {
                $values[] = $instanceTarget;
                continue;
            }

            // A nullable self/static parameter with no owner must stay null;
            // constructing a replacement service would silently change the
            // hook's owner.
            if ($isSelf && $type->allowsNull()) {
                $values[] = null;
                continue;
            }

            $className = null;
            if ($isSelf) {
                $className = $reflect->getClosureScopeClass()?->getName();
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
            }

            if ($className !== null && $this->container->has($className)) {
                $values[] = $this->container->get($className);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $values[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot resolve parameter $%s for condition closure in %s: Class not in container and no default value set.',
                $param->getName(),
                $label
            ));
        }

        return $values;
    }
}
