<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;
use WPLokerBJM\Shared\Log\Logger;

/**
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @internal
 */
class InstanceRegistryRuntimeHookResolver
{

    /**
     * Resolve the owner of a manual registration.
     *
     * @param callable    $callback Registration callback.
     * @param object|null $owner    Explicit owner — always wins when provided.
     *
     * @throws \RuntimeException when no owner can be inferred.
     */
    public function resolveOwner(callable $callback, ?object $owner): object
    {
        if ($owner !== null) {
            return $owner;
        }

        if (is_array($callback) && is_object($callback[0] ?? null)) {
            return $callback[0];
        }

        if ($callback instanceof \Closure) {
            $bound = (new ReflectionFunction($callback))->getClosureThis();
            if ($bound !== null) {
                return $bound;
            }

            // Static / unbound closure — nothing to infer the owner from.
            throw new \RuntimeException(
                'Cannot infer owner for hook registration — pass owner: explicitly.',
            );
        }

        if (is_object($callback)) {
            return $callback;
        }

        throw new \RuntimeException(
            'Cannot infer owner for hook registration — pass owner: explicitly.',
        );
    }

    /**
     * Resolve a hook name from a static string or an attribute-parameter closure.
     *
     * Closures declared in attribute parameters are static and already scoped
     * to the declaring class (PHP 8.1 RFC 'Closures in constant expressions'),
     * so private members resolve via self:: and the closure is invoked directly.
     *
     * @param string|\Closure $hook       Static hook name or closure resolving to one.
     * @param object          $instance   Owner instance.
     * @param string          $targetName Method/property name (for log messages).
     *
     * @return string|null The resolved hook name, or null when it could not be resolved.
     */
    public function resolveClosureHook(string|\Closure $hook, object $instance, string $targetName): ?string
    {
        if (\is_string($hook)) {
            return $hook;
        }

        try {
            $resolved = $hook();
        } catch (\Throwable $e) {
            Logger::warning(
                self::class,
                'Skipping hook on ' . $targetName . ' — hook closure failed: ' . $e->getMessage()
            );
            return null;
        }

        if (!\is_string($resolved) || $resolved === '') {
            Logger::warning(
                self::class,
                'Skipping hook on ' . $targetName . ' — hook closure did not resolve to a non-empty string'
            );
            return null;
        }

        return $resolved;
    }

    /**
     * Resolve the parameter names of a hook-annotated method, used to build
     * named hook args for executeIf parameter resolution.
     *
     * @return list<string>
     */
    public function resolveHookArgNames(object $instance, string $method): array
    {
        return array_map(
            static fn(\ReflectionParameter $param): string => $param->getName(),
            (new \ReflectionMethod($instance, $method))->getParameters(),
        );
    }

    /**
     * Whether a registration's HookKey identifies the given callable/member.
     *
     * Method hooks store the method name in the `method` slot and 'method' in
     * `target`; property hooks invert it (kind in `method`, property name in
     * `target`), so both slots are checked.
     */
    public function matchesCallableTarget(HookKey $key, string $targetName): bool
    {
        if (\strcasecmp($key->method, $targetName) === 0) {
            return true;
        }

        return \in_array($key->method, ['property', 'property-hook'], true)
            && \strcasecmp($key->target, $targetName) === 0;
    }

    /**
     * Normalise the public `array|callable` target into an owner + member name.
     *
     * @param array{0: object, 1: string}|\Closure $target
     * @return array{0: object|null, 1: string|null}
     */
    public function resolveLiveCallableTarget(array|\Closure $target): array
    {
        $instance = null;
        $targetName = null;

        if (is_array($target)) {
            $instance = $target[0] ?? null;
            $targetName = $target[1] ?? null;
        } elseif ($target instanceof \Closure) {
            $reflection = new \ReflectionFunction($target);
            $instance = $reflection->getClosureThis();
            $targetName = $reflection->getName();
        }

        if (!is_object($instance) || !is_string($targetName) || $targetName === '') {
            Logger::warning(
                self::class,
                'unregisterByCallableInLiveInstance() expects [$liveInstance, \'member\'] or a first-class callable bound to the live instance.',
            );

            return [null, null];
        }

        return [$instance, $targetName];
    }

    /**
     * Evaluate an attribute-parameter registerIf gate (static closure
     * invoked directly).
     *
     * @param \Closure|null $registerIf Gate closure (null = no gate).
     * @param object $instance Owner instance.
     * @param string $targetName Method/property name (for log messages).
     */
    public function evaluateRegisterIf(?\Closure $registerIf, object $instance, string $targetName): bool
    {
        if ($registerIf === null) {
            return true;
        }

        try {
            $allowed = $registerIf();
        } catch (\Throwable $e) {
            Logger::error(self::class, 'Skipping hook on ' . $targetName . ' — registerIf closure failed: ' . $e->getMessage());
            return false;
        }

        if (!\is_bool($allowed)) {
            Logger::error(self::class, 'Skipping hook on ' . $targetName . ' — registerIf must return bool, got ' . get_debug_type($allowed));
            return false;
        }

        if ($allowed === false) {
            Logger::error(self::class, 'Skipping hook on ' . $targetName . ' — registerIf gate returned false.');
            return false;
        }

        return true;
    }
}