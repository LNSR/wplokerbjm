<?php
declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Abstract;

use WPLokerBJM\Shared\Log\Logger;

/**
 * Opt-in interface for anonymous classes that need to register hooks.
 *
 * Extending this class captures the parent class and property name at
 * construction time so the hook registry can resolve the target without
 * walking the call stack or inspecting properties via reflection.
 *
 * @example Usage in a property hook:
 * ```php
 * #[Filter('nocache_headers', 9, deferRegister: true)]
 * private $test = null {
 *     get => $this->test ??= new class ($this, __PROPERTY__) extends AnonClassHookMetadata {
 *         public function __invoke(): bool {
 *             return false;
 *         }
 *     };
 * }
 * ```
 *
 * `$parentClass` accepts either a class-string or the parent object itself —
 * {@see getParentClass()} normalizes an object parent via get_class().
 * @template T of object|class-string
 */
abstract class AnonClassHookMetadata
{
     /**
     * @param T $parentClass    The class-string containing this hook, or the
     *                                            parent object to resolve via get_class().
     * @param string              $parentProperty The property name holding this instance.
     */
    public function __construct(
        protected private(set) readonly string|object $parentClass,
        public private(set) readonly string $parentProperty,
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
