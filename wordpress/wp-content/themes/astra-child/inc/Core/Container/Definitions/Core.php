<?php

namespace AstraChild\Core\Container\Definitions;

/**
 *
 * ## Init Service Array Injection
 * The definition for \AstraChild\Core\Container\Init::class automatically discovers and injects all autowirable
 * classes that implement HooksInterface. The AutowireScanner scans the codebase to find classes
 * implementing the interface, eliminating the need for manual service registration.
 *
 * The Init class (see {@link \AstraChild\Core\Container\Init}) expects an array of services, each of which implements
 * HooksInterface. When Init::initialize() is called, it will iterate through this array and register
 * WordPress hooks for each service.
 *
 * This pattern allows automatic registration of hook services without manual maintenance,
 * keeping your theme's bootstrap logic organized and maintainable.
 *
 * * @see \AstraChild\Core\Container\Init
 * * @see \AstraChild\Contracts\HooksInterface
 * * @see \AstraChild\Core\Container\AutowireScanner
 */
class Core
{
    public static function getDefinitions(): array
    {
        return [
            // The Init service receives an array of core service objects.
            // See Init.php for how this array is used to register hooks.
            \AstraChild\Core\Container\Init::class => function ($c) {
                // Automatically find all autowirable classes that implement HooksInterface
                $scanner = new \AstraChild\Core\Container\AutowireScanner(
                    dirname(__DIR__, 3), // Points to the inc/ directory
                    'AstraChild'
                );

                $hooksImplementers = $scanner->getInterfaceImplementerClassNames(
                    \AstraChild\Contracts\HooksInterface::class
                );

                // Resolve each service from the container
                $services = array_map(fn($className) => $c->get($className), $hooksImplementers);

                return new \AstraChild\Core\Container\Init($services);
            },
        ];
    }
}
