<?php

namespace WPLokerBJM\Core\Container;

use WPLokerBJM\Bootstrap;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Shared\Log\Logger;

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
 * @see Bootstrap
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 */
class Init
{
    private bool $initialized = false;

    public function __construct(
        private readonly WPHooksContainerRegistry $registry,
    ) {}

    public function __destruct()
    {
        !defined('WPLOKERBJM_TEST_ENV') && Logger::flush();
    }

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
