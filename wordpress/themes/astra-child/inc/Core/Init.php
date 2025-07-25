<?php

namespace AstraChild\Core;

use AstraChild\Contracts\HooksInterface;

/**
 *
 * Responsible for initializing core services in the Astra Child theme.
 *
 * ## Constructor
 * The constructor accepts an array of service objects. These services are typically
 * responsible for registering WordPress hooks (actions and filters) and should implement
 * the HooksInterface contract.
 *
 * * The array of services is injected (usually via a DI container definition) and stored
 * * as a readonly property, ensuring immutability after construction.
 * @see \AstraChild\Core\Definitions\Core
 *
 * ## Usage
 * Call the `initialize()` in functions.php method to iterate through all injected services. For each service
 * that implements HooksInterface, it will call `registerActions()` and `registerFilters()`
 * to register the necessary WordPress hooks.
 *
 * This approach allows you to batch-register hooks for multiple services in a single place,
 * keeping your theme's bootstrap logic organized and maintainable.
 * * @see \AstraChild\Contracts\HooksInterface
 */
class Init
{
    /**
     * @var array<int,object> $services
     * Array of service objects to be initialized. Each should implement HooksInterface.
     * This property is readonly and set via constructor injection.
     */
    public function __construct(private readonly array $services = [])
    {
    }

    /**
     * Initialize all services by registering their WordPress hooks.
     *
     * Iterates through each service in the $services array. If a service implements
     * HooksInterface, it will have its registerActions() and registerFilters() methods called.
     *
     * @return void
     */
    public function initialize(): void
    {
        foreach ($this->services as $service) {
            if ($service instanceof HooksInterface) {
                $service->registerActions();
                $service->registerFilters();
            }
        }
    }
}