<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Immutable snapshot of the invoker state included in debug payloads.
 */
final readonly class HookDebugState
{
    public function __construct(
        public bool $consumed,
        public int $executions,
        public bool $dispatching,
    ) {}
}
