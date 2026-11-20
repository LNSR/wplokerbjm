<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

/**
 * Immutable identity block shared by every debug payload for one invoker.
 */
final readonly class HookDebugIdentity
{
    /**
     * @param 'container'|'runtime' $path
     * @param 'method'|'property'|'property-hook'|null $target
     */
    public function __construct(
        public string $label,
        public string $hook,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public bool $once,
        public string $path,
        public ?string $class = null,
        public ?string $member = null,
        public ?string $target = null,
        public ?string $visibility = null,
    ) {}
}
