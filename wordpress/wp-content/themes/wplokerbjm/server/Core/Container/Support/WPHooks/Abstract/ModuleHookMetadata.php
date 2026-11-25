<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Abstract;

use Override;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsInnerClass;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};

use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\Injectable;

/**
 * interface for anonymous classes and classes not found in container that need to register hooks.
 * * @see WPHooksInstanceRegistry primarily used for identity to register hooks found inside runtime instance class
 *
 * - Extending this class make hooks work as ownership boundary, zero refCount shall unregister all hooks in this module.
 * - Extending this class also make WPHooksInstanceRegistry see anon class that extend this class as cacheable as long as the shape class it's stable and $forceNoCache is disabled.
 *
 * @example Usage in a property hook:
 * ```php
 * #[Filter('nocache_headers', 9, deferRegister: true)]
 * private $test = null {
 *     get => $this->test ??= new class ($this, __PROPERTY__) extends ModuleHookMetadata {
 *         public function __invoke(): bool {
 *             return false;
 *         }
 *     };
 * }
 * ```
 *
 * `$outerClass` accepts either a class-string or the parent object itself
 * @template T
 * @extends parent<T>
 */
abstract class ModuleHookMetadata
{

    /**
     * @var ?T
     */
    protected private(set) string|object|null $outerClass {
        get => $this->outerClass instanceof \WeakReference
            ? $this->outerClass->get()
            : (\is_string($this->outerClass)
                ? $this->outerClass
                : $this->outerClass = null
            );
        set => $this->outerClass = \is_object($value) ? \WeakReference::create($value) : $value;
    }


    /** @var class-string<T>|string */
    public readonly string $outerClassString;

    /**
     * Logical ownership path identifying this object within its parent-child hierarchy.
     *
     * Primarily used for recursive identity tracking and dependency-injection plan caching.
     */
    public readonly string $ownershipPath;

    /**
     * @param T      $outerClass    The outer class-string or the outer class its object to resolve later via get_class().
     * @param string $identifier The identifier that hold this instance as slot, presumably: __PROPERTY__  from property-hook, __FUNCTION__ method.
     */
    public function __construct(
        string|object $outerClass,
        public readonly string $identifier,
    ) {
        $this->outerClass = $outerClass;

        if (is_object($this->outerClass) && !str_contains($this->outerClass::class, "@anonymous")) {
            $this->outerClassString = $this->outerClass::class;
        } elseif ($this->outerClass instanceof static && isset($this->outerClass->ownershipPath)) {
            $this->outerClassString = $this->outerClass->ownershipPath;
        } elseif (\is_string($this->outerClass)) {
            $this->outerClassString = $this->outerClass;
        } else {
            $this->outerClassString = 'Parent expired . ' . $this->identifier;
        }

        $this->ownershipPath = $this->outerClass instanceof static
            ? \sprintf("%s->%s", $this->outerClass->ownershipPath, $this->identifier)
            : \sprintf("%s->%s", $this->outerClassString, $this->identifier);
    }


    /**
     *! NOTE: If you relying on this: Be careful, even module class itself can retain its own strong reference like
     * @example:
     * ```php
     * <?php
     * #[Injectable(skip: true)]
     * class ReferenceTest extends ModuleHookMetadata 
     * {
     *      private \Closure $property {
     *           get { $this->property ??= fn() => ... };
     *      }
     * }
     * ```
     * this instance closure has `$this` reference from its own class
     * it will take ZEND Engine for a while to collect cycle or you can just explicitly flush via \gc_collect_cycles() fn.
     */
    public function __destruct()
    {
        \do_action(WPHooksInstanceRegistry::UNREGISTER_HOOKS, $this);

        if (!\is_callable($this)) return;
        \do_action(WPHooksContainerRegistry::UNREGISTER_DEFERRED_BY_CALLABLE, [$this->outerClass, $this->identifier]);
        \do_action(WPHooksContainerRegistry::UNREGISTER_BY_CALLABLE, [$this->outerClass, $this->identifier]);
    }

    /**
     * For Recursive Anon Classes
     * @param property-hook-string $currentClassPropertyIdentifier magic constant
     * @example
     * ```php
     * $instance = new class(parent: __CLASS__, identifier: __PROPERTY__) extends AsInnerClass {
     *  private AsInnerClass $depthClass1 {
     *      get => $this->depthClass1 ??= new class(...$this->createIdentityClass(__PROPERTY__)) extends AsInnerClass {};
     *  }
     * }
     * ```
     * @return array
     */
    protected function createIdentityClass(string $currentClassPropertyIdentifier): array
    {
        /**
         * ! Respect outer chain of self for recursive anon classes
         */
        return [$this->outerClass instanceof static ? $this : $this->ownershipPath, $currentClassPropertyIdentifier];
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
            error_log(static::class . $this->identifier . ': Failed to configure ' . $th->getMessage());
            throw $th;
        }
    }
}
