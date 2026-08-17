<?php
declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract;

/**
 * Base metadata contract for anonymous child objects owned by a parent property.
 *
 * @template T of object|class-string
 */
abstract class AsChildClass
{
    /**
     * @param T $parentClass The class-string containing this child, or the
     *                       parent object to resolve via get_class().
     * @param string $identifier The property or method holding this instance.
     */
    public function __construct(
        private string|object $parentClass,
        public protected(set) string $identifier,
    ) {
    }

    /**
     * Resolve the parent class-string, normalizing an object parent via get_class().
     * @return class-string
     */
    public function getParentClass(): string
    {
        return is_object($this->parentClass) ? get_class($this->parentClass) : $this->parentClass;
    }
}
