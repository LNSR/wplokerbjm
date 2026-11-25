<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract;

use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleHookMetadata;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Base metadata contract for anonymous child objects owned by a outer class property-hook.
 * @see DependencyInjector
 * * Intended usage: Passing external deps without needing host class carrying constructor boilerplate.
 * @template T
 * @example passing phpdoc generic for autocomplete $outerClass(if passing ParentClass instance)
 * * phpdoc for anonymous class:
 * ```php
 * $instance = new
 *  \/**
 *   * @extends parent<NamedParentClass>
 *   *
 *   \/*
 *   class extends AsInnerClass {...};
 * ```
 * ! Must be inbetween 'new' and 'class', verified work on VSCode according
 * @link https://github.com/phpstan/phpstan/issues/8770#issuecomment-1402742332 code sample
 * @extends parent<T>
 */
abstract class AsInnerClass extends ModuleHookMetadata
{
    /**
     * @param T      $outerClass    The outer class-string or the outer class its object to resolve later via get_class().
     * @param string $identifier The identifier that hold this instance as slot, presumably: __PROPERTY__  from property-hook, __FUNCTION__ method.
     */
    public function __construct(
        string|object $outerClass,
        string $identifier,
    ) {
        parent::__construct($outerClass, $identifier);
        do_action(DependencyInjector::INJECT_ON, $this);
    }
}
