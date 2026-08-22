<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract;

use WPGraphQL;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Base metadata contract for anonymous child objects owned by a parent property.
 *
 * @template T of object|class-string
 */
abstract class AsChildClass
{
    private bool $___configurationAlreadyRun = false;

    /**
     * @param T $parentClass The class-string containing this child, or the
     *                       parent object to resolve via get_class().
     * @param string $identifier The property or method holding this instance.
     * @param ?\Closure(T $parentClass): void $configuration The initialization closure.
     * @param-closure-this self $configuration configure via closure instead constructor boilerplate
     */
    public function __construct(
        private string|object $parentClass,
        public protected(set) string $identifier,
    ) {}

    /**
     * Resolve the parent class-string, normalizing an object parent via get_class().
     * @return T
     */
    public function getParentClass(): string
    {
        return is_object($this->parentClass) ? get_class($this->parentClass) : $this->parentClass;
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
