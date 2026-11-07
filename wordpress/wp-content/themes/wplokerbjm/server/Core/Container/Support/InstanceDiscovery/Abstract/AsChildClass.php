<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract;

use WPLokerBJM\Core\Wordpress\DependencyInjectorEvent;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Base metadata contract for anonymous child objects owned by a parent property.
 * @see DependencyInjector
 * * Intended usage: Passing external deps without needing host class carrying constructor boilerplate.
 * @template T
 * @example passing phpdoc generic for autocomplete $parentClass(if passing ParentClass instance)
 * * phpdoc for anonymous class:
 * ```php
 * $instance = new
 *  \/**
 *   * @extends parent<NamedParentClass>
 *   *
 *   \/*
 *   class extends AsChildClass {...};
 * ```
 * ! Must be inbetween 'new' and 'class', verified work on VSCode according
 * @link https://github.com/phpstan/phpstan/issues/8770#issuecomment-1402742332 code sample
 */
abstract class AsChildClass

{
    /**
     * @var ?T
     */
    protected private(set) string|object|null $parentClass {
        get => $this->parentClass instanceof \WeakReference ? $this->parentClass->get() : $this->parentClass;
        set => $this->parentClass = \is_object($value) ? \WeakReference::create($value) : $value;
    }

    public readonly string $constructCurrentClassIdentity;

    /**
     * @param T $parentClass The class-string containing this child, or the
     *                       parent object to resolve via get_class().
     * @param string $identifier The property or method or any magic string holding this instance.
     */
    public function __construct(
        string|object $parentClass,
        public private(set) readonly string $identifier,
    ) {
        $this->parentClass = $parentClass;

        $this->constructCurrentClassIdentity = $this->parentClass instanceof static
            ? \sprintf("%s->%s", $this->parentClass->constructCurrentClassIdentity, $this->identifier)
            : \sprintf("%s->%s", $this->getParentClassString(), $this->identifier);

        if (\defined('WPLOKERBJM_TEST_ENV')) return;

        do_action(DependencyInjectorEvent::INJECT_ON, $this);
    }

    /**
     * Resolve the parent class-string, normalizing an object parent via get_class().
     * @return class-string<T>
     */
    public function getParentClassString(): string
    {
        return \is_object($this->parentClass) ? \get_class($this->parentClass) : $this->parentClass;
    }

    /**
     * For Recursive Anon Classes
     * @param property-hook-string $currentClassPropertryIdentifier magic constant
     * @example
     * ```php
     * $instance = new class(parent: __CLASS__, identifier: __PROPERTY__) extends AsChildClass {
     *  private AsChildClass $depthClass1 {
     *      get => $this->depthClass1 ??= new class(...$this->createIdentityClass(__PROPERTY__)) extends AsChildClass {};
     *  }
     * }
     * ```
     * @return array
     */
    protected function createIdentityClass(string $currentClassPropertryIdentifier): array
    {
        /**
         * ! Respect outer chain of self for recursive anon classes
         */
        return [$this->parentClass instanceof static ? $this : $this->constructCurrentClassIdentity, $currentClassPropertryIdentifier];
    }

    /**
     * ! Must be an instance Closure
     * Binds an initialization closure directly into current context anon class and configures it.
     * @param \Closure(): void $configuration
     * @param-closure-this static $configuration
     * @return static
     */
    public function configure(\Closure $configuration): static
    {
        try {
            $configuration = $configuration->bindTo($this, static::class);
            $configuration();
            return $this;
        } catch (\Throwable $th) {
            Logger::Error(static::class . $this->identifier . ': ', 'Failed to configure ' . $th->getMessage());
            throw $th;
        }
    }
}
