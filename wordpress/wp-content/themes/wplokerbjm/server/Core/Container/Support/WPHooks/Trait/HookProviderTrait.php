<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Shared DI parameter-plan resolution core for hook providers.
 *
 * Both the container provider (WPHookPlanProvider) and the runtime provider
 * resolve closure parameters through this trait: plans are pre-computed at
 * scan time (via {@see self::buildCallablePlan()}), exported to the hooks cache
 * file, and resolved from the container at hook-fire time — so the hot path
 * never needs reflection unless a plan is missing (stale cache / unexportable
 * defaults).
 * @template TClass
 * @phpstan-type CallableHookParams array{
 *      name: string, 
 *      type: string|class-string<TClass>|null, 
 *      hasDefault: bool, 
 *      default: mixed,
 *      isVariadic: bool
 * }
 * @phpstan-type CallablePlan array{
 *      isStatic: bool, 
 *      params: list<CallableHookParams>
 * }
 */
trait HookProviderTrait
{
    /**
     * Scope-bound gate closures, memoized per (closure, target class) pair.
     *
     * Attribute-argument closures are always static (constant-expression
     * rule), so scope-only binds are immutable — each pair binds at most
     * once per provider lifetime. Entries are dropped automatically when
     * the source closure is garbage-collected.
     * @var \WeakMap<\Closure, array<class-string<TClass>, \Closure>>
     */
    private \WeakMap $boundClosureCache { get => $this->boundClosureCache ??= new \WeakMap(); }

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
        /**
         * Strictly used for test scope discovery
         * @var CallablePlan $empty
         */
        $empty = ['isStatic' => true, 'params' => []];

        if ($callable === null) {
            return $empty;
        }

        try {
            return $this->buildCallablePlanFromReflector(new ReflectionFunction($callable));
        } catch (ReflectionException) {
            return $empty;
        }
    }

    /**
     * Build the resolution plan for ANY reflected callable (closure or method).
     *
     * This is the single source of truth for parameter metadata used both by
     * gate closures (executeIf / registerIf / hook-name / tag callables, via
     * {@see buildCallablePlan()}) and by hook HANDLERS themselves — the
     * scanner exports a handler's own plan as `hookArgs`, so parameter names
     * AND the variadic flag derive from the same shape, with no parallel
     * `hookArgsVariadic` fields.
     *
     * @param \ReflectionFunctionAbstract $reflect
     * @return CallablePlan
     */
    public function buildCallablePlanFromReflector(\ReflectionFunctionAbstract $reflect): array
    {
        /**
         * @var list<CallableHookParams> $params
         */
        $params = [];

        foreach ($reflect->getParameters() as $param) {
            $type = $param->getType();
            $paramType = null;
            if ($type instanceof ReflectionNamedType && $type->getName() === 'self') {
                $reflectClass = $reflect->getClosureScopeClass();
                $parentClass = $reflectClass?->getParentClass();
                $isModuleClass = $parentClass ? $parentClass->getName() === ModuleClassHookMetadata::class : false;
                if ($isModuleClass) {
                    if ($reflectClass->isAnonymous()) {
                        $anonClassName = \rtrim($reflectClass->getName(), '$');
                        $position = \strpos($anonClassName, '$');
                        $paramType = \substr($anonClassName, 0, $position);
                    } else {
                        $paramType = $reflectClass->getName();
                    }
                } else if ($reflectClass->isAnonymous()) {
                    throw new RuntimeException(
                        'Bare Anonymous class cannot be used as a parameter type hint \'self\' for hook.'
                    );
                } else {
                    $paramType = $reflectClass->getName();
                }
            } else {
                $paramType = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
            }

            $hasDefault = $param->isDefaultValueAvailable();
            $default = $hasDefault ? $param->getDefaultValue() : null;

            /** @var CallableHookParams $planParam */
            $planParam = [
                'name' => $param->getName(),
                'type' => $paramType,
                'hasDefault' => $hasDefault,
                'default' => $default,
                'isVariadic' => $param->isVariadic(),
            ];

            // Non-exportable defaults (objects/resources) -> set to null.
            if ($hasDefault && (is_object($default) || is_resource($default))) {
                $planParam['hasDefault'] = false;
                $planParam['default'] = null;
            }

            $params[] = $planParam;
        }

        return [
            'isStatic' => $reflect->isStatic(),
            'params' => $params,
        ];
    }

    /**
     * Build the resolution plan for any callable VALUE (closure, invokable
     * object, array callable, 'Class::method' string) — used for property
     * hooks whose callable lives in a property default value or live instance.
     *
     * @param mixed $callable The callable value to introspect.
     * @return CallablePlan
     */
    public function callablePlan(mixed $callable): array
    {
        if (!is_callable($callable)) {
            return ['isStatic' => true, 'params' => []];
        }

        try {
            $reflect = match (true) {
                $callable instanceof \Closure => new \ReflectionFunction($callable),
                is_object($callable) => \ReflectionMethod::createFromMethodName($callable::class . '::__invoke'),
                is_array($callable) => \ReflectionMethod::createFromMethodName($callable[0] . '::' . $callable[1]),
                is_string($callable) && str_contains($callable, '::') => \ReflectionMethod::createFromMethodName(...array_slice(explode('::', $callable), 0, 2)),
                is_string($callable) => new \ReflectionFunction($callable),
                default => null,
            };

            if ($reflect === null) {
                return ['isStatic' => true, 'params' => []];
            }

            return $this->buildCallablePlanFromReflector($reflect);
        } catch (\ReflectionException) {
            return ['isStatic' => true, 'params' => []];
        }
    }

    /**
     * Build the resolution plan for a property's callable value or default value.
     *
     * Mirrors {@see extractPropertyCallableParamNames()} but returns the full
     * plan (names + variadic flags) instead of names only.
     *
     * @param \ReflectionProperty $property from attributes
     * @param object|null $instance Optional runtime instance to execute getters/hooks.
     *
     * @return CallablePlan
     */
    public function extractPropertyCallablePlan(\ReflectionProperty $property, ?object $instance = null): array
    {
        /** @suppress PHP0406 */
        if ($instance !== null && $property->hasHook(\PropertyHookType::Get)) {
            // For hooked properties, reading the property executes the `get` hook.
            $value = $property->getValue($instance);
            if (is_callable($value)) {
                return $this->callablePlan($value);
            }
        }

        if ($property->hasDefaultValue()) {
            return $this->callablePlan($property->getDefaultValue());
        }

        return ['isStatic' => true, 'params' => []];
    }

    /**
     * Resolve the final hook name for a registration.
     *
     * A plain string is used as-is; a closure is resolved through the DI
     * container (via the plan provider) and must return a string.
     *
     * @throws \RuntimeException when the closure result is not a string
     */
    public function resolveHookName(
        string|\Closure $hook,
        ?ContainerInterface $container = null,
        array $hookParams = [],
        string $label = '',
        ?object $instanceTarget = null
    ): string {
        if (is_string($hook)) {
            return $hook;
        }

        $values = $this->resolveCallableParameters($hook, $hookParams, $container, $label, [], $instanceTarget);
        $name = $hook(...$values);

        if (!is_string($name)) {
            throw new \RuntimeException(
                'Hook name for ' . $label . ' must return string, got ' . get_debug_type($name)
            );
        }

        return $name;
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
     * closure is scope-bound only.
     *
     * @param CallablePlan $executeIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateExecuteIf(
        ?\Closure $executeIf,
        array $executeIfParams,
        ?ContainerInterface $container = null,
        string $label = '',
        object|null|string $targetClass = null,
        array $hookArgs = [],
        ?string $variadicHookArg = null,
    ): bool {
        if ($executeIf === null) {
            return true;
        }

        $targetInstance = null;
        if (is_object($targetClass)) {
            $targetInstance = $targetClass;
            $targetClass = $targetClass::class;
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

        $values = $this->resolveCallableParameters($executeIf, $executeIfParams, $container, $label, $hookArgs, $targetInstance, $variadicHookArg);
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
        ?ContainerInterface $container = null,
        string $label = '',
        null|object|string $targetClass = null,
    ): array {
        $targetInstance = null;
        if (is_object($targetClass)) {
            $targetInstance = $targetClass;
            $targetClass = $targetClass::class;
        }

        $tagCallable = $this->bindToTarget($tagCallable, $plan, $targetClass);
        $values = $this->resolveCallableParameters($tagCallable, $plan, $container, $label, [], $targetInstance);
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
        ?ContainerInterface $container = null,
        string $label = '',
        null|object|string $targetClass = null,
    ): bool {
        if ($registerIf === null) {
            return true;
        }

        $targetInstance = null;
        if (is_object($targetClass)) {
            $targetInstance = $targetClass;
            $targetClass = $targetClass::class;
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

        $values = $this->resolveCallableParameters($registerIf, $registerIfParams, $container, $label, [], $targetInstance);
        $allowed = $registerIf(...$values);

        if (!is_bool($allowed)) {
            throw new RuntimeException(
                'registerIf gate for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }

    /**
     * Extract parameter names from a property's callable value or default value.
     *
     * Handles standard properties (default values), initialized property hooks,
     * and live object instances.
     *
     * @param \ReflectionProperty $property from Attribute #Action/#Filter
     * @param object|null $instance Optional runtime instance to execute getters/hooks.
     *
     * @return array<int, string>
     */
    public function extractPropertyCallableParamNames(\ReflectionProperty $property, ?object $instance = null): array
    {
        return array_map(
            static fn(array $param): string => $param['name'],
            $this->extractPropertyCallablePlan($property, $instance)['params'],
        );
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
    private function resolveCallableParam(array $param, ?ContainerInterface $container = null, string $label = ''): mixed
    {
        $type = $param['type'] ?? null;

        if (is_string($type) && $type !== '' && $container !== null && $container->has($type)) {
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
    private function resolveCallableFallback(
        \Closure $callable,
        ?ContainerInterface $container = null,
        string $label = ''
    ): array {
        $reflect = new ReflectionFunction($callable);
        $values = [];

        foreach ($reflect->getParameters() as $param) {
            $type = $param->getType();
            $resolved = false;

            if ($type instanceof ReflectionNamedType && $type->getName() === 'self') {
                $className = $reflect->getClosureScopeClass()->getName();
                if ($container !== null && $container->has($className)) {
                    $values[] = $container->get($className);
                    $resolved = true;
                }
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($container !== null && $container->has($className)) {
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

    /**
     * Resolve a callable's parameters from the container, using the
     * pre-computed plan when available and reflection as a fallback.
     *
     * @param CallablePlan $plan
     * @param list<CallableHookParams> $hookArgs
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    private function resolveCallableParameters(
        \Closure $callable,
        array $plan,
        ?ContainerInterface $container = null,
        string $label = '',
        array $hookArgs = [],
        ?object $instanceTarget = null,
        ?string $variadicHookArg = null
    ): array {
        $params = $plan['params'] ?? [];

        if ($params !== []) {
            $values = [];
            foreach ($params as $param) {
                // A variadic closure parameter mirrors the HANDLER's variadic
                // bucket (the name must match $variadicHookArg): splat the
                // whole remainder, letting the caller dissect it manually.
                // An unmatched variadic parameter captures nothing — it never
                // falls back to the container.
                if (($param['isVariadic'] ?? false)) {
                    if (
                        $variadicHookArg !== null
                        && $param['name'] === $variadicHookArg
                        && isset($hookArgs[$param['name']])
                        && is_array($hookArgs[$param['name']])
                    ) {
                        array_push($values, ...$hookArgs[$param['name']]);
                    }
                    continue;
                }

                // Exact parameter-name match wins — hook arguments are injected
                // by name (e.g. `string $search` receives the handler's $search
                // argument), removing any scalar-ambiguity.
                if (array_key_exists($param['name'], $hookArgs)) {
                    $values[] = $hookArgs[$param['name']];
                    continue;
                }

                if (
                    is_string($param['type']) && $instanceTarget instanceof ModuleClassHookMetadata
                ) {
                    $values[] = $instanceTarget;
                    continue;
                }

                $values[] = $this->resolveCallableParam($param, $container, $label);
            }

            return $values;
        }

        return $this->resolveCallableFallback($callable, $container, $label);
    }
}
