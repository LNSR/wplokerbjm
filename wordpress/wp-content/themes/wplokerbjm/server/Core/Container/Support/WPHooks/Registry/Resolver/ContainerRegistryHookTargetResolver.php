<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookTargetResolverTrait;

/**
 * Resolves any callable hook target to a stable `[class-string, member]` pair
 * for the container registry.
 *
 * All callable shapes (array, `Class::method` string, invokable objects,
 * anonymous classes, first-class callables and PHP 8.4 property-hook
 * closures) are handled by {@see HookTargetResolverTrait}; the runtime
 * registry shares the same rules.
 *
 * @internal not for use beyond \WPLokerBJM\Core\Container\Support\WPHooks\Registry\
 * @phpstan-import-type HookTargetResolve from \WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\ContainerRegistryDeferredHookManager
 */
class ContainerRegistryHookTargetResolver
{
    use HookTargetResolverTrait;
}
