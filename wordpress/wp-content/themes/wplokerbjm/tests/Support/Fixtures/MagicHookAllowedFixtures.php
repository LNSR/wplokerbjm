<?php

declare(strict_types=1);

/**
 * Fixture: hook attribute on __invoke (allowed by the scanner).
 *
 * Lives outside the WPLokerBJM namespace so the broad production-namespace
 * scans (e.g. ContainerDefinitionsTest) never pick it up.
 */

namespace MagicHookAllowed;

use WPLokerBJM\Core\Container\Attributes\Action;

class InvokeHookService
{
    #[Action('invoke_hook')]
    public function __invoke(string $value = 'default'): void
    {
    }
}
