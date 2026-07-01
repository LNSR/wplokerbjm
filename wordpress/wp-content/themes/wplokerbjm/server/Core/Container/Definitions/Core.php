<?php

namespace WPLokerBJM\Core\Container\Definitions;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPhooksScanner;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Init;

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
            Init::class => static function (ContainerInterface $c) {
                /** @var WPhooksScanner $scanner */
                $scanner = $c->get(WPhooksScanner::class);
                $hookRegistrations = $scanner->getHookRegistrations();
                return new Init($hookRegistrations, $c);
            },
        ];
    }
}
