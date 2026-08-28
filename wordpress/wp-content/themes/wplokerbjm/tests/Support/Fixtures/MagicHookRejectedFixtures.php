<?php

declare(strict_types=1);

/**
 * Fixture: hook attribute on a magic method (rejected by the scanner).
 *
 * Lives outside the WPLokerBJM namespace so the broad production-namespace
 * scans (e.g. ContainerDefinitionsTest) never pick it up.
 */

namespace MagicHookRejected;

use WPLokerBJM\Core\Container\Attributes\Action;

class MagicGetHookService
{
    #[Action('magic_get_hook')]
    public function __get(string $name): mixed
    {
        return null;
    }
}
