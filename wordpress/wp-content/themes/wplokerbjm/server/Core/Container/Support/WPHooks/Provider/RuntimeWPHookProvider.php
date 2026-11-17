<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Provider;

use CallableHookParams;
use CallablePlan;
use Closure;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;

/**
 * Runtime hook provider — resolves dependencies for attribute-parameter
 * closures (hook name, registerIf, executeIf) on the runtime registry path.
 *
 * The provider is allowed to resolve dependencies (optionally from the
 * container when one is available); the runtime registry remains responsible
 * for runtime ownership and lifetime. When no container is provided, closures
 * without parameters (or with defaulted parameters) still work; typed
 * parameters become unresolvable and the caller decides how to handle that
 * (warn + skip).
 *
 * Attribute-argument closures are static and already scoped to the declaring
 * class resolve via self:: and no instance binding is needed.
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @phpstan-import-type CallablePlan from HookProviderTrait
 */
class RuntimeWPHookProvider
{
    use HookProviderTrait;
}
