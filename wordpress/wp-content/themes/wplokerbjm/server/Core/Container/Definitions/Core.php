<?php

namespace WPLokerBJM\Core\Container\Definitions;

/**
 * Core container definitions for the wplokerbjm theme.
 *
 * ## Init Service Array Injection
 *
 * This class provides manual definitions for key services that require special handling,
 * such as the Init service. The Init service automatically discovers and injects all
 * autowirable classes that implement HooksInterface using the AutowireScanner.
 *
 * How it works:
 * 1. The AutowireScanner scans the server/ directory for classes implementing HooksInterface.
 * 2. These class names are retrieved and resolved into service instances via the container.
 * 3. The Init class receives an array of these services and registers their WordPress hooks.
 *
 * This eliminates manual service registration, keeping the bootstrap logic clean and automatic.
 *
 * @see \WPLokerBJM\Core\Container\Init
 * @see \WPLokerBJM\Contracts\HooksInterface
 * @see \WPLokerBJM\Core\Container\AutowireScanner
 */
class Core
{
    public static function getDefinitions(): array
    {
        return [
            // Define the Init service: It handles automatic hook registration for all services.
            \WPLokerBJM\Core\Container\Init::class => function ($c) {
                // Step 1: Create the scanner to find HooksInterface implementers in the server/ directory.
                $scanner = new \WPLokerBJM\Core\Container\AutowireScanner(
                    get_stylesheet_directory() . '/server', // Path to the server/ directory containing services.
                    'WPLokerBJM' // Base namespace for the theme.
                );

                // Step 2: Get the fully qualified class names of all classes implementing HooksInterface.
                $hooksImplementers = $scanner->getInterfaceImplementerClassNames(
                    \WPLokerBJM\Contracts\HooksInterface::class
                );

                // Step 3: Resolve each class name into an actual service instance using the container.
                $services = array_map(fn($className) => $c->get($className), $hooksImplementers);

                // Step 4: Return a new Init instance with the array of resolved services.
                // Init will call registerActions() and registerFilters() on each service.
                return new \WPLokerBJM\Core\Container\Init($services);
            },
        ];
    }
}
