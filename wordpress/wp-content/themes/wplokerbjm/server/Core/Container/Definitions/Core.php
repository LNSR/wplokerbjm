<?php

namespace WPLokerBJM\Core\Container\Definitions;

/**
 *
 * ## Init Service Array Injection
 * The definition for \WPLokerBJM\Core\Container\Init::class automatically discovers and injects all autowirable
 * classes that implement HooksInterface. The AutowireScanner scans the codebase to find classes
 * implementing the interface, eliminating the need for manual service registration.
 *
 * The Init class (see {@link \WPLokerBJM\Core\Container\Init}) expects an array of services, each of which implements
 * HooksInterface. When Init::initialize() is called, it will iterate through this array and register
 * WordPress hooks for each service.
 *
 * This pattern allows automatic registration of hook services without manual maintenance,
 * keeping your theme's bootstrap logic organized and maintainable.
 *
 * * @see \WPLokerBJM\Core\Container\Init
 * * @see \WPLokerBJM\Contracts\HooksInterface
 * * @see \WPLokerBJM\Core\Container\AutowireScanner
 */
class Core
{
    public static function getDefinitions(): array
    {
        return [
            // The Init service receives an array of core service objects.
            // See Init.php for how this array is used to register hooks.
            \WPLokerBJM\Core\Container\Init::class => function ($c) {
                // Automatically find all autowirable classes that implement HooksInterface
                $scanner = new \WPLokerBJM\Core\Container\AutowireScanner(
                    get_stylesheet_directory() . '/server', // Points to the server/ directory
                    'WPLokerBJM'
                );

                $hooksImplementers = $scanner->getInterfaceImplementerClassNames(
                    \WPLokerBJM\Contracts\HooksInterface::class
                );

                // Resolve each service from the container
                $services = array_map(fn($className) => $c->get($className), $hooksImplementers);

                return new \WPLokerBJM\Core\Container\Init($services);
            },
        ];
    }
}
