<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support\Fixtures;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Attributes\Filter;

/**
 * Test fixture: a service with an instance-method action hook.
 *
 * Tracks how many times the constructor runs and the ID of the most recent
 * instance so tests can assert lazy-resolution semantics:
 *  - The constructor must NOT run during `Init::initialize()`.
 *  - It must run exactly once when the hook first fires.
 *  - Subsequent fires must reuse the same instance.
 */
class LazyHookService
{
    public static int $instantiationCount = 0;
    public static array $capturedValues = [];

    public int $id;

    public function __construct()
    {
        self::$instantiationCount++;
        $this->id = self::$instantiationCount;
    }

    #[Action(hook: 'lazy_action_hook', priority: 10, acceptedArgs: 1)]
    public function onAction(string $value = 'default'): void
    {
        self::$capturedValues[] = ['id' => $this->id, 'value' => $value];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with an instance-method filter hook.
 *
 * Verifies lazy resolution works for filters too, and that variadic arguments
 * are forwarded to the target method.
 */
class FilterService
{
    public static int $instantiationCount = 0;
    public static array $capturedArgs = [];

    public int $id;

    public function __construct()
    {
        self::$instantiationCount++;
        $this->id = self::$instantiationCount;
    }

    #[Filter(hook: 'lazy_filter_hook', priority: 10, acceptedArgs: 2)]
    public function onFilter(string $value, string $extra = ''): string
    {
        self::$capturedArgs[] = ['id' => $this->id, 'value' => $value, 'extra' => $extra];
        return $value . $extra;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedArgs = [];
    }
}

/**
 * Test fixture: a service with a #[Filter] on a public property closure.
 *
 * The closure modifies the filtered value — used to verify that
 * ContainerLazyPropertyHookHandler reads the property and invokes the closure
 * at hook-fire time.
 */
class PropertyFilterService
{
    public static array $capturedValues = [];

    #[Filter(hook: 'property_filter', priority: 10, acceptedArgs: 1)]
    public $appendSuffix = static function (string $value): string {
        self::$capturedValues[] = $value;
        return $value . '_suffixed';
    };

    public static function reset(): void
    {
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with a #[Action] on a public property closure.
 *
 * The closure logs a side effect only — used to verify action hooks
 * are properly invoked through ContainerLazyPropertyHookHandler.
 */
class PropertyActionService
{
    public static array $capturedValues = [];

    #[Action(hook: 'property_action', priority: 10, acceptedArgs: 1)]
    public $logAction = static function (string $value): void {
        self::$capturedValues[] = $value;
    };

    public static function reset(): void
    {
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with multiple #[Filter] at different priorities
 * on the same public property closure.
 *
 * Used to verify IS_REPEATABLE works for property attributes and that
 * the key prefix matching in *ByMethod activates all of them.
 */
class PropertyMultiPriorityService
{
    public static array $capturedValues = [];

    #[Filter(hook: 'multi_priority_filter', priority: 10, acceptedArgs: 1)]
    #[Filter(hook: 'multi_priority_filter', priority: 20, acceptedArgs: 1)]
    public $multiFilter = static function (string $value): string {
        self::$capturedValues[] = $value;
        return $value . '_processed';
    };

    public static function reset(): void
    {
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with a deferred #[Filter] on a property closure.
 *
 * Used to verify that deferred property hooks are NOT registered during
 * initialize() and are activated on demand via activateDeferredByMethod().
 */
class PropertyDeferredService
{
    public static array $capturedValues = [];

    #[Filter(hook: 'deferred_property_filter', priority: 10, acceptedArgs: 1, defer: true)]
    public $deferredFilter = static function (string $value): string {
        self::$capturedValues[] = $value;
        return $value . '_deferred';
    };

    public static function reset(): void
    {
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with a public property that is NOT a Closure.
 *
 * Used to verify graceful failure — ContainerLazyPropertyHookHandler should log
 * an error and return the fallback value.
 */
class PropertyNonClosureService
{
    #[Filter(hook: 'non_closure_filter', priority: 10, acceptedArgs: 1)]
    public string $notAClosure = 'i_am_not_a_closure';
}

// ── Method-based hook fixtures for ContainerLazyHookHandlerTest ──────────────

/**
 * Test fixture: a service with a deferred #[Action] on an instance method.
 *
 * Used to verify that deferred method hooks are NOT registered during
 * initialize() and are activated on demand via activateDeferredByMethod().
 */
class MethodDeferredService
{
    public static array $captured = [];

    #[Action(hook: 'deferred_method_action', priority: 10, acceptedArgs: 1, defer: true)]
    public function onDeferredAction(string $value): void
    {
        self::$captured[] = $value;
    }

    public static function reset(): void
    {
        self::$captured = [];
    }
}

/**
 * Test fixture: a service with multiple #[Filter] at different priorities
 * on the same instance method.
 *
 * Used to verify IS_REPEATABLE works for method attributes and that
 * WordPress fires multiple filter callbacks at different priorities.
 */
class MethodMultiPriorityService
{
    public static array $captured = [];

    #[Filter(hook: 'multi_priority_method_filter', priority: 10, acceptedArgs: 1)]
    #[Filter(hook: 'multi_priority_method_filter', priority: 20, acceptedArgs: 1)]
    public function onFilter(string $value): string
    {
        self::$captured[] = $value;
        return $value . '_processed';
    }

    public static function reset(): void
    {
        self::$captured = [];
    }
}

/**
 * Test fixture: a service whose action method always throws.
 *
 * Used to verify that ContainerLazyHookHandler's catch block returns null
 * for actions (fire-and-forget, no passthrough semantics).
 */
class ThrowingActionService
{
    #[Action(hook: 'throwing_action_hook', priority: 10, acceptedArgs: 1)]
    public function onAction(string $_value): void
    {
        throw new \RuntimeException('Simulated action failure');
    }
}

/**
 * Test fixture: a service whose filter method always throws.
 *
 * Used to verify that ContainerLazyHookHandler's catch block returns the
 * first argument as a passthrough for filters.
 */
class ThrowingFilterService
{
    #[Filter(hook: 'throwing_filter_hook', priority: 10, acceptedArgs: 1)]
    public function onFilter(string $_value): string
    {
        throw new \RuntimeException('Simulated filter failure');
    }
}

/**
 * Test fixture: a service with a protected instance method annotated
 * with #[Action].
 *
 * Used to verify that non-public methods are invocable via
 * ContainerLazyHookHandler's Closure::bind-based invoker.
 */
class ProtectedMethodService
{
    public static bool $called = false;

    #[Action(hook: 'protected_method_action', priority: 10, acceptedArgs: 0)]
    protected function onProtectedAction(): void
    {
        self::$called = true;
    }

    public static function reset(): void
    {
        self::$called = false;
    }
}

/**
 * Test fixture: a service with a private instance method annotated
 * with #[Filter].
 *
 * Used to verify that private methods are invocable via
 * ContainerLazyHookHandler's Closure::bind-based invoker and that filter
 * return values are passed through correctly.
 */
class PrivateMethodService
{
    #[Filter(hook: 'private_method_filter', priority: 10, acceptedArgs: 1)]
    private function onPrivateFilter(string $value): string
    {
        return $value . '_private';
    }
}

// ── Condition-gate hook fixtures ─────────────────────────────────────

/**
 * Test fixture: a service with an instance-method action hook gated by a
 * condition closure.
 *
 * The condition closure is supplied via the registration array in
 * ConditionHookTest (mirroring what WPHooksScanner emits from the
 * attribute's `condition` argument). The instantiation counter lets tests
 * assert that a false condition prevents the service from ever being
 * resolved from the container.
 */
class ConditionActionService
{
    public static int $instantiationCount = 0;
    public static array $capturedValues = [];

    public function __construct()
    {
        self::$instantiationCount++;
    }

    public function onConditionAction(string $value = 'default'): void
    {
        self::$capturedValues[] = $value;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a service with an instance-method filter hook gated by a
 * condition closure.
 *
 * Used to verify filter passthrough semantics when the condition fails
 * or throws — the first argument must flow through untouched.
 */
class ConditionFilterService
{
    public static int $instantiationCount = 0;
    public static array $capturedArgs = [];

    public function __construct()
    {
        self::$instantiationCount++;
    }

    public function onConditionFilter(string $value, string $extra = ''): string
    {
        self::$capturedArgs[] = ['value' => $value, 'extra' => $extra];
        return $value . $extra;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedArgs = [];
    }
}

/**
 * Test fixture: a runtime-registered instance whose #[Action] carries a
 * condition closure.
 *
 * WPHooksRuntimeRegistry has no container access, so such hooks must be
 * skipped with a warning during registerHooksOn() instead of being
 * registered unconditionally.
 */
class RuntimeConditionService
{
    public static array $captured = [];

    #[Action(hook: 'runtime_condition_action', priority: 10, acceptedArgs: 1, condition: static function (\Psr\Container\ContainerInterface $c): bool {
        return true;
    })]
    public function onRuntimeConditionAction(string $value): void
    {
        self::$captured[] = $value;
    }

    public static function reset(): void
    {
        self::$captured = [];
    }
}

// ── Dynamic hook-name fixtures ───

/**
 * Test fixture: a service whose hook name is resolved from a closure at
 * registration time via the DI container (WPHookPlanProvider).
 */
class DynamicHookService
{
    public static int $instantiationCount = 0;

    public static array $capturedValues = [];

    public function __construct()
    {
        self::$instantiationCount++;
    }

    public function onDynamicAction(string $value = 'default'): void
    {
        self::$capturedValues[] = $value;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a runtime-registered instance whose #[Action] carries a
 * closure hook name.
 *
 * WPHooksRuntimeRegistry has no container access, so such hooks must be
 * skipped with a warning during registerHooksOn() instead of being
 * registered unconditionally.
 */
class RuntimeDynamicService
{
    public static array $captured = [];

    #[Action(hook: static function (): string {
        return 'runtime_dynamic_action';
    }, priority: 10, acceptedArgs: 1)]
    public function onRuntimeDynamicAction(string $value): void
    {
        self::$captured[] = $value;
    }

    public static function reset(): void
    {
        self::$captured = [];
    }
}

// ── Inheritance hook fixtures ───

/**
 * Test fixture: a base service declaring a hook on its own method.
 *
 * Hook registration is declared-only — subclasses must re-declare the
 * method (with their own attribute and `parent::` call) to opt in.
 */
class ParentHookService
{
    public static int $instantiationCount = 0;

    public static array $capturedValues = [];

    public function __construct()
    {
        self::$instantiationCount++;
    }

    #[Action(hook: 'parent_hook', priority: 10, acceptedArgs: 1)]
    public function onParentHook(string $value): void
    {
        self::$capturedValues[] = $value;
    }

    public static function reset(): void
    {
        self::$instantiationCount = 0;
        self::$capturedValues = [];
    }
}

/**
 * Test fixture: a subclass that does NOT re-declare the parent hook.
 *
 * With declared-only scanning it must NOT receive a registration — the
 * parent's own registration is the only one.
 */
class ChildNoRedeclareService extends ParentHookService
{
}

/**
 * Test fixture: a subclass that re-declares the parent hook explicitly.
 *
 * Re-declaring the method with its own #[Action] and a `parent::` call
 * opts the child in — both the parent and the child register + fire.
 */
class ChildRedeclareService extends ParentHookService
{
    #[Action(hook: 'parent_hook', priority: 10, acceptedArgs: 1)]
    public function onParentHook(string $value): void
    {
        parent::onParentHook($value);
    }
}
