<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

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
 */
class WPHookPlanProvider
{
    /**
     * Build the parameter resolution plan for a callable closure.
     *
     * Each entry describes one parameter: its name, the class type to
     * resolve from the container (null for builtin/untyped params),
     * whether a default value exists, and the default value itself.
     *
     * Returns an empty plan for null callables or when a default value
     * cannot be safely exported to the cache (objects/resources) — in
     * that case the registry falls back to reflection at fire time.
     *
     * @return array<int, CallableHookParams>
     */
    public function buildCallablePlan(?\Closure $callable): array
    {
        if ($callable === null) {
            return [];
        }

        try {
            $reflect = new ReflectionFunction($callable);
            $plan = [];

            foreach ($reflect->getParameters() as $param) {
                $type = $param->getType();
                $hasDefault = $param->isDefaultValueAvailable();
                $default = $hasDefault ? $param->getDefaultValue() : null;

                // Non-exportable defaults would break the cache — defer to reflection.
                if ($hasDefault && (is_object($default) || is_resource($default))) {
                    return [];
                }

                $plan[] = [
                    'name' => $param->getName(),
                    'type' => ($type instanceof ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null,
                    'hasDefault' => $hasDefault,
                    'default' => $default,
                ];
            }

            return $plan;
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * Resolve a callable's parameters from the container, using the
     * pre-computed plan when available and reflection as a fallback.
     *
     * @param array<int, CallableHookParams> $plan
     *
     * @return array<int, mixed>
     *
     * @throws RuntimeException when a parameter cannot be resolved
     */
    public function resolveCallableParameters(\Closure $callable, array $plan, ContainerInterface $container, string $label): array
    {
        if ($plan !== []) {
            $values = [];
            foreach ($plan as $param) {
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
     * @param array<int, CallableHookParams> $executeIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateExecuteIf(?\Closure $executeIf, array $executeIfParams, ContainerInterface $container, string $label): bool
    {
        if ($executeIf === null) {
            return true;
        }

        // Fast path: zero-parameter gates are invoked directly,
        // without any reflection or DI resolution.
        if ($executeIfParams === []) {
            try {
                $allowed = $executeIf();
                if (is_bool($allowed)) {
                    return $allowed;
                }
            } catch (\ArgumentCountError) {
                // Closure actually has parameters — fall through to resolution.
            }
        }

        $values = $this->resolveCallableParameters($executeIf, $executeIfParams, $container, $label);
        $allowed = $executeIf(...$values);

        if (!is_bool($allowed)) {
            throw new RuntimeException(
                'executeIf for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }

    /**
     * Evaluate a registerIf registration gate for a hook.
     *
     * Evaluated ONCE at registration time (not per fire). A null gate always
     * allows registration; the closure must return a bool.
     *
     * @param array<int, CallableHookParams> $registerIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateRegistrationGate(?\Closure $registerIf, array $registerIfParams, ContainerInterface $container, string $label): bool
    {
        if ($registerIf === null) {
            return true;
        }

        // Fast path: zero-parameter gates are invoked directly.
        if ($registerIfParams === []) {
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
