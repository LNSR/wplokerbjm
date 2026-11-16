<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver;

use ReflectionFunction;
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HookProviderTrait, HookTargetResolverTrait};
use WPLokerBJM\Shared\Log\Logger;

/**
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @internal
 */
class InstanceRegistryRuntimeHookResolver
{
    use HookTargetResolverTrait;

    private \WeakMap $weakMap;
    public function __construct()
    {
        $this->weakMap = new \WeakMap();
    }

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
     * Resolve the parameter names of a hook-annotated method, used to build
     * named hook args for executeIf parameter resolution.
     *
     * @return list<string>
     */
    public function resolveHookArgNames(object $instance, string $method): array
    {
        if (!isset($this->weakMap[$instance])) {
            $this->weakMap[$instance] = [];
        };
        return $this->weakMap[$instance][$method] ??= array_map(
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
     * Normalise the public callable target into an owner instance + member name.
     *
     * Supported shapes, mirroring {@see HookTargetResolverTrait}:
     *
     *  - `[$liveInstance, 'member']` arrays (method or property name);
     *  - closures: first-class callables (`$service->method(...)`) and PHP 8.4
     *    property-hook accessor closures;
     *  - invokable objects, including {@see \WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata}
     *    hosts — the object is its own owner and the member is `__invoke`.
     *
     * `Class::method` strings cannot name a live instance and are rejected.
     *
     * @param array{0: object, 1: string}|\Closure|object|callable|string $target
     * @return array{0: object|null, 1: string|null}
     */
    public function resolveLiveCallableTarget(object|callable|array|string $target): array
    {

        $instance = null;
        $targetName = null;

        if (is_array($target)) {
            $instance = $target[0] ?? null;
            $targetName = $target[1] ?? null;
        } elseif ($target instanceof \Closure) {
            if (isset($this->weakMap[$target])) return $this->weakMap[$target];
            $reflection = new \ReflectionFunction($target);
            $instance = $reflection->getClosureThis();
            $targetName = $this->parsePropertyHookMemberName($reflection->getName())
                ?? $reflection->getName();
            $this->weakMap[$target] = [$instance, $targetName];
        } elseif (is_object($target) && is_callable($target)) {
            // Invokable object (anonymous class / ModuleClassHookMetadata host):
            // the callable member is __invoke and the object owns its own hooks.
            $instance = $target;
            $targetName = '__invoke';
        }

        if (!is_object($instance) || !is_string($targetName) || $targetName === '') {
            Logger::warning(
                self::class,
                'unregisterByCallableInLiveInstance() expects [$liveInstance, \'member\'], a first-class callable/property-hook closure bound to the live instance, or an invokable object.',
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
