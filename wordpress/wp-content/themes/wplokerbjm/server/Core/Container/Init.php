<?php

namespace WPLokerBJM\Core\Container;

use WPLokerBJM\Contracts\HooksInterface;

/**
 * Initializes core services in the wplokerbjm theme by registering WordPress hooks.
 *
 * This class is responsible for bootstrapping the theme's services that implement
 * HooksInterface. It automatically discovers and initializes these services,
 * calling their hook registration methods in a centralized way.
 *
 * ## Constructor
 * Accepts an array of service objects injected via the DI container. These services
 * must implement HooksInterface to have their hooks registered.
 *
 * ## Usage
 * Call the `initialize()` method (typically in functions.php or a bootstrap file)
 * to register hooks for all injected services. This iterates through the services,
 * checks if they implement HooksInterface, and calls `registerActions()` and
 * `registerFilters()` on each.
 *
 * This pattern centralizes hook registration, making it easier to manage and debug
 * WordPress integrations across the theme.
 *
 * @see \WPLokerBJM\Core\Container\Definitions\Core
 * @see \WPLokerBJM\Contracts\HooksInterface
 */
class Init
{
    /**
     * @var array<int,object> $services
     * Array of service objects to initialize. Each should implement HooksInterface.
     * Injected via constructor and stored as readonly for immutability.
     */
    public function __construct(private readonly array $services = [])
    {
    }

    /**
     * Initialize all services by registering their WordPress hooks.
     *
     * Loops through the injected services array. For each service that implements
     * HooksInterface, calls `registerActions()` and `registerFilters()` to set up
     * WordPress hooks. Errors are logged but don't stop initialization of other services.
     *
     * @return void
     */
    public function initialize(): void
    {
        foreach ($this->services as $service) {
            try {
                if ($service instanceof HooksInterface) {
                    $service->registerActions();
                    $service->registerFilters();
                }
            } catch (\Exception $e) {
                error_log('Init::initialize error in service ' . get_class($service) . ': ' . $e->getMessage());
            }
        }
    }
}