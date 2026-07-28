<?php
declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Abstract;

/**
 * Opt-in interface for anonymous classes that need to register hooks.
 *
 * Extending this class captures the parent class and property name at
 * construction time so the hook registry can resolve the target without
 * walking the call stack or inspecting properties via reflection.
 *
 * **Usage in a property hook:**
 * ```php
 * #[Filter('nocache_headers', 9, defer: true)]
 * private $test = null {
 *     get => $this->test ??= new class (self::class, __PROPERTY__) extends AnonClassHookPropertyAbstract {
 *         public function __invoke(): bool {
 *             return false;
 *         }
 *     };
 * }
 * ```
 */
abstract class AnonClassHookPropertyAbstract
{
    /**
     * @param class-string $parentClass  The class containing this hook.
     * @param string       $parentProperty The property name holding this instance.
     */
    public function __construct(
        public readonly string $parentClass,
        public readonly string $parentProperty,
    ) {
    }
}
