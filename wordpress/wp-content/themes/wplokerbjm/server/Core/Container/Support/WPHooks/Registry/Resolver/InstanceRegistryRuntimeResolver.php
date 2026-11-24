<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver;

use ReflectionFunction;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookTargetResolverTrait;

/**
 * @internal
 */
class InstanceRegistryRuntimeHookResolver
{
    use HookTargetResolverTrait;

    private \WeakMap $weakMap;
    public function __construct(HookLoggerInterface $logger = new NullHookLogger())
    {
        $this->logger = $logger;
        $this->weakMap = new \WeakMap();
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
            $this->logger->warning(
                self::class,
                'unregisterByCallableInLiveInstance() expects [$liveInstance, \'member\'], a first-class callable/property-hook closure bound to the live instance, or an invokable object.',
            );

            return [null, null];
        }

        return [$instance, $targetName];
    }
}
