<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Attributes;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class WPHooksScannerPolicy
{
    public function __construct(
        public bool $skipContainerRegistryScan = false,
    ) {}
}
