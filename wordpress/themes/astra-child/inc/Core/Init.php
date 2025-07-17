<?php

namespace AstraChild\Core;

use AstraChild\Contracts\HooksInterface;

/**
 *
 *
 * Responsible for initializing core services in the Astra Child theme.
 * Accepts an array of service objects via the constructor. During initialization,
 * it iterates through each service and, if the service implements HooksInterface,
 * calls its registerActions() and registerFilters() methods to register WordPress hooks.
 *
 * This approach ensures that all hookable services are properly registered
 * without requiring manual calls for each one.
 */
class Init
{
    public function __construct(private readonly array $services = [])
    {
    }

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