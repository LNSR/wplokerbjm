<?php

namespace WPLokerBJM\Core\Container\Definitions;

/**
 * Core container definitions for the wplokerbjm theme.
 *
 * ## Init Service Array Injection
 *
 * This class provides manual definitions for key services that require special handling,
 * such as the Init service. The Init service automatically discovers and registers hooks
 * from #[Action] and #[Filter] attributes on methods across all autowirable classes.
 *
 * How it works:
 * 1. The WPhooksScanner scans the server/ directory for all hook attributes.
 * 2. Hook registrations are scanned from #[Action] and #[Filter] attributes on methods.
 * 3. The Init class receives hook registrations and a container reference, registering
 *    hooks automatically by resolving services from the container as needed.
 *
 * This eliminates manual hook registration, keeping the bootstrap logic clean and declarative.
 *
 * @see \WPLokerBJM\Core\Container\Init
 * @see \WPLokerBJM\Core\Container\Support\WPhooksScanner
 * @see \WPLokerBJM\Core\Container\Attributes\Action
 * @see \WPLokerBJM\Core\Container\Attributes\Filter
 */
class Core implements DefinitionProviderInterface
{
    public static function getDefinitions(): array
    {
        return [
            // Define the Init service: It handles automatic hook registration for all services.
            \WPLokerBJM\Core\Container\Init::class => function ($c) {
                // Step 1: Create the scanner to find hook registrations from attributes.
                $scanner = new \WPLokerBJM\Core\Container\Support\WPhooksScanner(
                    get_stylesheet_directory() . '/server', // Path to the server/ directory containing services.
                    'WPLokerBJM' // Base namespace for the theme.
                );

                // Step 2: Get hook registrations from attributes.
                $hookRegistrations = $scanner->getHookRegistrations();

                // Init will register hooks from attributes automatically.
                // Instance-method hooks are wrapped in lazy closures that defer
                // container resolution to the moment the hook actually fires.
                return new \WPLokerBJM\Core\Container\Init($hookRegistrations, $c);
            },
        ];
    }
}
