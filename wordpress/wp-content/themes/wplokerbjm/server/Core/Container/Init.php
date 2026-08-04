<?php

namespace WPLokerBJM\Core\Container;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;

/**
 * Initializes core services in the wplokerbjm theme by registering WordPress hooks.
 *
 * Delegates to WPHooksContainerRegistry which stores hooks as identifiable
 * ContainerLazyHookHandler instances, enabling unregistration by class/method.
 *
 * Each hook defers container resolution to the moment WordPress fires it.
 * The underlying service is NOT instantiated during `initialize()` —
 * only when the hook runs. Combined with `->lazy()` autowire definitions,
 * a service is constructed at most once per request.
 *
 * ## Usage
 * Call `initialize()` to register all discovered hooks with WordPress.
 * ! initialized in MU_PLUGIN_DIR.'/wplokerbjm-bootstrap.php'
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 */
class Init
{
    private bool $initialized = false;

    public function __construct(
        private readonly WPHooksContainerRegistry $registry,
    ) {}

    /**
     * Register all WordPress hooks from attributes via the registry.
     *
     * @return void
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return; // prevent double registration of hooks
        }

        $this->registry->initialize();
        $this->initialized = true;
    }
}