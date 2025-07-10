<?php

namespace AstraChild\Core;

/**
 * 
 *
 * Handles the initialization of core services for the Astra Child theme.
 * Iterates through the provided services and calls their 'register' method if available.
 *
 * 
 */
class Init {
    public function __construct(private readonly array $services = []) {}

    public function initialize(): void {
        foreach ($this->services as $service) {
            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }
}
