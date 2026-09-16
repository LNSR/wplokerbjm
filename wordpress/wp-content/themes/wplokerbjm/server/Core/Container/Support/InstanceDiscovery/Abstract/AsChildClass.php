<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract;

use WPGraphQL;
use WPLokerBJM\Core\DependencyInjectorHookActions;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Base metadata contract for anonymous child objects owned by a parent property.
 * @see DependencyInjector
 * * Intended usage: Passing external deps without needing host class carrying constructor boilerplate.
 *
 * @template T
 */
abstract class AsChildClass
{
    /**
     * @param T $parentClass The class-string containing this child, or the
     *                       parent object to resolve via get_class().
     * @param string $identifier The property or method or any magic string holding this instance.
     */
    public function __construct(
        public string|object $parentClass,
        public private(set) readonly string $identifier,
    ) {
        if (defined('WPLOKERBJM_TEST_ENV')) return;
        do_action(DependencyInjectorHookActions::INJECT_ON, $this);
    }

    /**
     * Resolve the parent class-string, normalizing an object parent via get_class().
     * @return T
     */
    public function getParentClass(): string
    {
        return is_object($this->parentClass) ? get_class($this->parentClass) : $this->parentClass;
    }

    /**
     * For Recursive Anon Classes
     * @param property-hook-string<T> $currentClassPropertryIdentifier magic constant
     * @return array
     */
    protected function createIdentityClass(string $currentClassPropertryIdentifier): array
    {
        return [
            sprintf("%s->%s", $this->getParentClass(), $this->identifier),
            $currentClassPropertryIdentifier
        ];
    }
    
    public function constructCurrentClassIdentity(): string
    {
        return sprintf("%s->%s", $this->getParentClass(), $this->identifier);
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
            $bound = \Closure::bind($configuration, $this, static::class);
            $bound();
            return $this;
        } catch (\Throwable $th) {
            Logger::Error(static::class . $this->identifier . ': ', 'Failed to configure ' . $th->getMessage());
            throw $th;
        }
    }
}
