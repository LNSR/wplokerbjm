<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Inject
{
    /**
     * @param class-string $name
     */
    public function __construct(public string $name)
    {
    }
}
