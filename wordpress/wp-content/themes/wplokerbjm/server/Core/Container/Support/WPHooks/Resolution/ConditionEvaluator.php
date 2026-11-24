<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Resolution;

use RuntimeException;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};

/**
 * Hook-condition evaluation: dynamic hook names, executeIf/registerIf gates,
 * tag callables and onUnregisterDo cleanup closures.
 *
 * All methods are plan-driven and build on {@see ArgumentResolver}; no
 * reflection happens on the hot path unless a plan is missing.
 *
 * @phpstan-import-type CallablePlan from \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider
 */
final class ConditionEvaluator
{
    public function __construct(
        private readonly ArgumentResolver $resolver,
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {}

    /**
     * Resolve the final hook name for a registration.
     *
     * @throws RuntimeException when the closure result is not a string
     * @param object|class-string $targetClass
     */
    public function resolveHookName(
        string|\Closure $hook,
        array $hookParams = [],
        string $label = '',
        object|string|null $targetClass = null
    ): string {
        if (is_string($hook)) {
            return $hook;
        }
        $targetInstance = null;
        if (is_object($targetClass)) {
            $targetInstance = $targetClass;
            $targetClass = $targetClass::class;
        }

        $hook = $this->resolver->bindToTarget($hook, $hookParams, $targetClass);
        $values = $this->resolver->resolveCallableParameters($hook, $hookParams, $label, [], $targetInstance);
        $name = $hook(...$values);

        if (!is_string($name)) {
            throw new RuntimeException(
                'Hook name for ' . $label . ' must return string, got ' . get_debug_type($name)
            );
        }

        return $name;
    }

    /**
     * Evaluate an executeIf gate for a hook.
     *
     * @param CallablePlan $executeIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateExecuteIf(
        ?\Closure $executeIf,
        array $executeIfParams,
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

        $executeIf = $this->resolver->bindToTarget($executeIf, $executeIfParams, $targetClass);

        // Fast path: zero-parameter gates are invoked directly,
        // without any reflection or DI resolution.
        if (($executeIfParams['params'] ?? []) === []) {
            try {
                $allowed = $executeIf();
                if (is_bool($allowed)) {
                    return $allowed;
                }

                // Zero-parameter gate with a non-bool result: fail right away —
                // falling through would invoke the gate a second time.
                throw new RuntimeException(
                    'executeIf for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
                );
            } catch (\ArgumentCountError) {
                // Closure actually has parameters — fall through to resolution.
            }
        }

        $values = $this->resolver->resolveCallableParameters($executeIf, $executeIfParams, $label, $hookArgs, $targetInstance, $variadicHookArg);
        $allowed = $executeIf(...$values);

        if (!is_bool($allowed)) {
            throw new RuntimeException(
                'executeIf for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }

    /**
     * @param CallablePlan $onUnregisterDoParams
     * @return (callable:mixed)|null
     */
    public function resolveOnUnregisterDo(
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        string $label = '',
        \WeakReference|null|string $targetClass = null,
    ): \Closure|null {
        if ($onUnregisterDo === null) {
            return null;
        }

        $targetInstanceWeakRef = null;
        if ($targetClass instanceof \WeakReference) {
            $targetInstanceWeakRef = $targetClass;
            $instance = $targetClass->get();
            $targetClass = $instance !== null ? $instance::class : null;
        } elseif (is_object($targetClass)) {
            $targetInstanceWeakRef = \WeakReference::create($targetClass);
            $targetClass = $targetClass::class;
        }

        return function () use ($onUnregisterDo, $onUnregisterDoParams, $label, $targetClass, $targetInstanceWeakRef): mixed {
            static $alreadyRun = false;
            if ($alreadyRun) return null;
            $alreadyRun = true;
            if (did_action('shutdown') || doing_action('shutdown')) return null;

            try {
                $onUnregisterDo = $this->resolver->bindToTarget($onUnregisterDo, $onUnregisterDoParams, $targetClass);
                $values = $this->resolver->resolveCallableParameters($onUnregisterDo, $onUnregisterDoParams, $label, [], $targetInstanceWeakRef?->get() ?? null);

                return $onUnregisterDo(...$values);
            } catch (\Throwable $e) {
                $this->logger->error(__METHOD__, 'Failed to execute onUnregisterDo', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'invokerLabel' => $label
                ]);
                return null;
            }
        };
    }

    /**
     * @param CallablePlan $plan
     *
     * @return array<int, string>
     *
     * @throws RuntimeException when the callable does not return an array
     */
    public function resolveTagCallable(
        \Closure $tagCallable,
        array $plan,
        string $label = '',
        null|object|string $targetClass = null,
    ): array {
        $targetInstance = null;
        if (is_object($targetClass)) {
            $targetInstance = $targetClass;
            $targetClass = $targetClass::class;
        }

        $tagCallable = $this->resolver->bindToTarget($tagCallable, $plan, $targetClass);
        $values = $this->resolver->resolveCallableParameters($tagCallable, $plan, $label, [], $targetInstance);
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
     * @param CallablePlan $registerIfParams
     *
     * @throws RuntimeException when the gate does not return bool
     */
    public function evaluateRegistrationGate(
        ?\Closure $registerIf,
        array $registerIfParams,
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

        $registerIf = $this->resolver->bindToTarget($registerIf, $registerIfParams, $targetClass);

        // Fast path: zero-parameter gates are invoked directly.
        if (($registerIfParams['params'] ?? []) === []) {
            try {
                $allowed = $registerIf();
                if (is_bool($allowed)) {
                    return $allowed;
                }

                // Zero-parameter gate with a non-bool result: fail right away —
                // falling through would invoke the gate a second time.
                throw new RuntimeException(
                    'registerIf gate for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
                );
            } catch (\ArgumentCountError) {
                // Closure actually has parameters — fall through to resolution.
            }
        }

        $values = $this->resolver->resolveCallableParameters($registerIf, $registerIfParams, $label, [], $targetInstance);
        $allowed = $registerIf(...$values);

        if (!is_bool($allowed)) {
            throw new RuntimeException(
                'registerIf gate for ' . $label . ' must return bool, got ' . get_debug_type($allowed)
            );
        }

        return $allowed;
    }
}
