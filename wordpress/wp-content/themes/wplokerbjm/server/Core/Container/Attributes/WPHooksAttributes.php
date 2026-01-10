<?php

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;

/**
 * Attribute for WordPress actions.
 * Annotate a method to register it as an action hook.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Action
{
    public function __construct(
        public string $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1
    ) {}
}

/**
 * Attribute for WordPress filters.
 * Annotate a method to register it as a filter hook.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Filter
{
    public function __construct(
        public string $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1
    ) {}
}