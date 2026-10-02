<?php
declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Abstract;

use Override;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;
use WPLokerBJM\Core\Wordpress\InstanceRuntimeRegistryEvent;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Opt-in interface for anonymous classes and classes not found in container that need to register hooks.
 *
 * Extending this class captures the parent class and property name at
 * construction time so the hook registry can resolve the target without
 * walking the call stack or inspecting properties via reflection.
 *
 * @example Usage in a property hook:
 * ```php
 * #[Filter('nocache_headers', 9, deferRegister: true)]
 * private $test = null {
 *     get => $this->test ??= new class ($this, __PROPERTY__) extends ModuleClassHookMetadata {
 *         public function __invoke(): bool {
 *             return false;
 *         }
 *     };
 * }
 * ```
 *
 * `$parentClass` accepts either a class-string or the parent object itself —
 * {@see getParentClass()} normalizes an object parent via get_class().
 * @template TClass
 * @template T of class-string<TClass>|object<TClass>
 */
abstract class ModuleClassHookMetadata extends AsChildClass
{

    /**
     * @param T $parentClass    The class-string containing this hook, or the
     *                                            parent object to resolve via get_class().
     * @param string              $parentProperty The property name holding this instance.
     */
    public function __construct(
        #[Override]
        protected string|object $parentClass,
        public private(set) readonly string $parentProperty,
    ) {
        parent::__construct($parentClass, $parentProperty);
    }

    public function __destruct()
    {
        if (\defined('WPLOKERBJM_TEST_ENV') && WPLOKERBJM_TEST_ENV) return;
        \do_action(InstanceRuntimeRegistryEvent::UNREGISTER_HOOKS, $this);
    }
}
