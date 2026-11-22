<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Shared\Log\Logger;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\{Registrations\HookRegistration, Registrations\HookKey, Provider\WPHookPlanProvider, WPHooksScanner};
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\{HookPattern, HookTagUtilities};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\{ModuleClassHookMetadata};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\ContainerRegistryHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\{
    ContainerRegistryDeferredHookManager
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\ContainerRegistryHookTargetResolver;

/**
 * Registry for WordPress hooks discovered via #[Action] and #[Filter] attributes.
 *
 * Stores all hook registrations as identifiable HookInvoker instances,
 * enabling unregistration by hook name, class, or specific class::method.
 * Service resolution is deferred to hook-fire time (lazy loading).
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-import-type HandlerEntry from ContainerRegistryHandlerEntry
 * @phpstan-import-type HookTargetResolve from ContainerRegistryDeferredHookManager
 * @phpstan-type ActivateEntry \Closure(DeferredHookEntryHandler): bool
 * @template TKey of string
 */
class WPHooksContainerRegistry
{

    #region deferred handlers activation hooks
    public const ACTIVATE_DEFERRED_BY_HOOK = 'wphooks_container_activate_deferred_by_hook';
    public const ACTIVATE_DEFERRED_BY_CLASS = 'wphooks_container_activate_deferred_by_class';
    public const ACTIVATE_DEFERRED_BY_CALLABLE = 'wphooks_container_activate_deferred_by_callable';
    public const ACTIVATE_DEFERRED_BY_TAGS = 'wphooks_container_activate_deferred_by_tags';
    public const ACTIVATE_DEFERRED_BY_NAMESPACE = 'wphooks_container_activate_deferred_by_namespace';
    public const ACTIVATE_DEFERRED_BY_HOOK_PATTERN = 'wphooks_container_activate_deferred_by_hook_pattern';
    public const ACTIVATE_DEFERRED_BY_TAG_PATTERN = 'wphooks_container_activate_deferred_by_tag_pattern';
    #endregion

    #region deferred handlers unregistration hooks
    public const UNREGISTER_DEFERRED_BY_CALLABLE = 'wphooks_container_unregister_deferred_by_callable';
    public const UNREGISTER_DEFERRED_BY_HOOK = 'wphooks_container_unregister_deferred_by_hook';
    public const UNREGISTER_DEFERRED_BY_CLASS = 'wphooks_container_unregister_deferred_by_class';
    public const UNREGISTER_DEFERRED_BY_NAMESPACE = 'wphooks_container_unregister_deferred_by_namespace';
    public const UNREGISTER_DEFERRED_BY_TAGS = 'wphooks_container_unregister_deferred_by_tags';
    public const UNREGISTER_DEFERRED_BY_HOOK_PATTERN = 'wphooks_container_unregister_deferred_by_hook_pattern';
    public const UNREGISTER_DEFERRED_BY_TAG_PATTERN = 'wphooks_container_unregister_deferred_by_tag_pattern';
    #endregion

    #region active handlers unregistration hooks
    public const UNREGISTER_BY_CALLABLE = 'wphooks_container_unregister_by_callable';
    public const UNREGISTER_BY_HOOK = 'wphooks_container_unregister_by_hook';
    public const UNREGISTER_BY_CLASS = 'wphooks_container_unregister_by_class';
    public const UNREGISTER_BY_NAMESPACE = 'wphooks_container_unregister_by_namespace';
    public const UNREGISTER_BY_TAGS = 'wphooks_container_unregister_by_tags';
    public const UNREGISTER_BY_HOOK_PATTERN = 'wphooks_container_unregister_by_hook_pattern';
    public const UNREGISTER_BY_TAG_PATTERN = 'wphooks_container_unregister_by_tag_pattern';
    #endregion

    /**
     * Control-plane bridge: selector event name → registry method.
     *
     * @var array<string, non-empty-string>
     */
    public const EVENT_BRIDGE_HOOKS = [
        self::ACTIVATE_DEFERRED_BY_HOOK => 'activateDeferredByHook',
        self::ACTIVATE_DEFERRED_BY_CLASS => 'activateDeferredByClass',
        self::ACTIVATE_DEFERRED_BY_CALLABLE => 'activateDeferredByCallable',
        self::ACTIVATE_DEFERRED_BY_TAGS => 'activateDeferredByTags',
        self::ACTIVATE_DEFERRED_BY_NAMESPACE => 'activateDeferredByNamespace',
        self::ACTIVATE_DEFERRED_BY_HOOK_PATTERN => 'activateDeferredByHookPattern',
        self::ACTIVATE_DEFERRED_BY_TAG_PATTERN => 'activateDeferredByTagPattern',
        self::UNREGISTER_DEFERRED_BY_CALLABLE => 'unregisterDeferredByCallable',
        self::UNREGISTER_DEFERRED_BY_HOOK => 'unregisterDeferredByHook',
        self::UNREGISTER_DEFERRED_BY_CLASS => 'unregisterDeferredByClass',
        self::UNREGISTER_DEFERRED_BY_NAMESPACE => 'unregisterDeferredByNamespace',
        self::UNREGISTER_DEFERRED_BY_TAGS => 'unregisterDeferredByTags',
        self::UNREGISTER_DEFERRED_BY_HOOK_PATTERN => 'unregisterDeferredByHookPattern',
        self::UNREGISTER_DEFERRED_BY_TAG_PATTERN => 'unregisterDeferredByTagPattern',
        self::UNREGISTER_BY_CALLABLE => 'unregisterByCallable',
        self::UNREGISTER_BY_HOOK => 'unregisterByHook',
        self::UNREGISTER_BY_CLASS => 'unregisterByClass',
        self::UNREGISTER_BY_NAMESPACE => 'unregisterByNamespace',
        self::UNREGISTER_BY_TAGS => 'unregisterByTags',
        self::UNREGISTER_BY_HOOK_PATTERN => 'unregisterByHookPattern',
        self::UNREGISTER_BY_TAG_PATTERN => 'unregisterByTagPattern',
    ];

    /**
     * @var array<TKey, ContainerRegistryHandlerEntry>
     */
    protected array $handlers = [];

    /**
     * @var \WeakMap<HookInvoker, \WeakReference<ContainerRegistryHandlerEntry>>
     */
    protected \WeakMap $entriesByInvokers;

    public private(set) bool $initialized = false;
    private bool $bridgeInitialized = false;
    /**
     * @var HandlerEntry['removeEntryIndexCallback']
     */
    private ?\Closure $destroyIndexes = null;


    /**
     * @param ContainerInterface $container
     * @param WPHooksScanner $scanner Discovers hook registrations from the live container entries.
     * @param WPHookPlanProvider $planProvider Plan provider for condition/hook-name resolution.
     * @param ContainerRegistryDeferredHookManager $containerRegistryDeferredHookManager Deferred hook manager.
     * @param ContainerRegistryHookTargetResolver $resolverTarget Resolves callable targets to class/method pairs.
     * @param EntriesIndexer $entriesIndexer For fast lookup of handler indexes.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private WPHooksScanner $scanner,
        private WPHookPlanProvider $planProvider,
        private ContainerRegistryDeferredHookManager $containerRegistryDeferredHookManager,
        private ContainerRegistryHookTargetResolver $resolverTarget = new ContainerRegistryHookTargetResolver(),
        private QueuedHooksCleaner $queuedHooksCleaner = new QueuedHooksCleaner(),
        private EntriesIndexer $entriesIndexer = new EntriesIndexer(),
    ) {
        $this->entriesByInvokers = new \WeakMap();
        $this->planProvider ??= new WPHookPlanProvider($container);
        $this->destroyIndexes = $this->entriesIndexer->removeIndexes(...);
    }

    /**
     * Register the control-plane event bridge with raw WordPress actions.
     *
     * Idempotent; {@see removeEventBridge()} clears the flag so the bridge can
     * be re-registered. Array callables keep every listener removable.
     *
     * @internal Subject to change
     * @private
     */
    public function initEventBridge(): void
    {
        if ($this->bridgeInitialized) {
            return;
        }
        $this->bridgeInitialized = true;

        foreach (self::EVENT_BRIDGE_HOOKS as $hook => $method) {
            \add_action($hook, [$this, $method], 10, 1);
        }
    }

    /**
     * Remove the control-plane event bridge listeners.
     *
     * Flips `$bridgeInitialized` back to false so {@see initEventBridge()} can
     * boot the bridge again — the pair is a reversible toggle, not a one-way
     * switch.
     *
     * @internal Subject to change
     * @private
     */
    public function removeEventBridge(): void
    {
        if (!$this->bridgeInitialized) {
            return;
        }
        $this->bridgeInitialized = false;

        foreach (self::EVENT_BRIDGE_HOOKS as $hook => $method) {
            (void) \remove_action($hook, [$this, $method], 10);
        }
    }

    /**
     * Register all stored hooks with WordPress via add_action/add_filter.
     * @api
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initEventBridge();
        $this->registerAll($this->scanner->getHookRegistrations());
        foreach ($this->handlers as $key => $entry) {
            try {
                $weakRef = \WeakReference::create($entry);
                $this->queuedHooksCleaner->addContainerRegistryItsEntries([$weakRef]);
                $this->entriesIndexer->setIndexes($entry);
                $entry->setOnEntryRemovalRemoveIndexesCallback($this->destroyIndexes);
                $this->entriesByInvokers[$entry->handler] = $weakRef;
            } catch (\Exception $e) {
                Logger::error(
                    __METHOD__,
                    "Error registering {$entry->invokerLabel}",
                    [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]
                );
            }
        }

        $this->initialized = true;
    }

    #region deferred handlers activation methods

    /**
     * Activate all deferred handlers registered for a specific WordPress hook.
     *
     * Moves matching HookInvoker instances from $this->deferredHandlers
     * into $this->handlers and registers them with WordPress via add_action/add_filter.
     * Handlers that have already been activated are silently skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByHook('init');
     * ```
     ** types autocomplete DX generated by generate-meta-hooks.php
     * @api
     * @param string $hook WordPress hook name.
     */
    public function activateDeferredByHook(string $hook): void
    {
        $this->containerRegistryDeferredHookManager->activateDeferredByHook(
            $hook,
            $this->activateEntry
        );
    }

    /**
     * Activate all deferred handlers belonging to a specific service class.
     *
     * Scans all deferred hooks and activates any ContainerLazyHookInvoker whose owning
     * class matches the given FQCN. Already-active handlers are silently skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByClass(WPGraphQL::class);
     * ```
     * @api
     * @param class-string|object $class Fully qualified class name.
     */
    public function activateDeferredByClass(string|object $class): void
    {
        $this->containerRegistryDeferredHookManager->activateDeferredByClass(
            \is_object($class) ? \get_class($class) : $class,
            $this->activateEntry
        );
    }

    /**
     * Activate deferred hook handlers matching the specified target callable or identifier.
     *
     * Scans the deferred handlers pool and transfers matching entries to the active pool,
     * registering them immediately with WordPress via `add_action()` or `add_filter()`.
     * Works seamlessly across standard instance methods, first-class callables, PHP 8.4
     * property hooks, array targets, and class-string identifiers.
     *
     * Already-activated handlers are silently skipped to prevent duplicate registrations.
     *
     * **Usage Examples:**
     *
     ** 1. First-Class Callable (Recommended for External Orchestrators):
     * Provides full IDE autocompletion, type safety, and static refactoring support.
     * ```php
     *! $registry->activateDeferredByCallable($this->graphQL->setCacheHeader(...));
     * ```
     *
     ** 2. Self-Referencing Array (Recommended for Internal Method Self-Activation):
     * Fast-path resolution when a method manages its own hook status. DRY and refactor-proof via `__FUNCTION__` or `__PROPERTY__`.
     * ```php
     *! $registry->activateDeferredByCallable([$this, __FUNCTION__]);
     *! $registry->activateDeferredByCallable([$this->parentClassString, $this->parentProperty]);
     * ```
     *
     ** 3. String Identifier (Class::method):**
     * Useful for static activation or when an active instance reference is not readily available.
     * ```php
     *! $registry->activateDeferredByCallable(GraphQLService::class . '::setCacheHeader');
     * ```
     *
     ** 4. Array Lookup (`[$object, 'method']`):**
     * Traditional PHP callable array syntax for instance method target resolution.
     * ```php
     *! $registry->activateDeferredByCallable([$this->graphQL, 'setCacheHeader']);
     * ```
     *
     ** 5. PHP 8.4 Property Hooks & Dynamic Invokables & PHP 8.5 inline closure constant expression:**
     * Activates closures or invokable objects held within class properties.
     * On property: 
     * ```php
     * private $disableNoCacheHeaders = static function() { return false; };
     *
     *! $registry->activateDeferredByCallable($this->graphQL->disableNoCacheHeaders);
     * ```
     *
     * @param HookTargetResolve $target First-class callable, array `[$object, 'method']`,
     *                                  string `'Class::method'`, or invokable property reference.
     * ! active all attribute attached to property or method
     * @api
     * @return void
     * @throws \InvalidArgumentException If the target cannot be resolved to a valid class and member.
     */
    public function activateDeferredByCallable(callable|string|array $target): void
    {
        $this->containerRegistryDeferredHookManager->activateDeferredByCallable(
            $target,
            $this->activateEntry
        );
    }

    /**
     * Activate all deferred handlers carrying at least one of the given tags.
     *
     * Scans the deferred handlers pool and transfers matching entries to the
     * active pool, registering them immediately with WordPress. A hook matches
     * when any of its tags intersects the query set. Already-active handlers
     * are silently skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByTags(['cache', 'seo']);
     * $registry->activateDeferredByTags([HookTag::Cache]);
     * ```
     * @api
     * @param array<int, string|\BackedEnum> $tags Tag list to activate.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTags(array $tags): int
    {
        $tags = HookTagUtilities::normalizeTags($tags);

        return $this->containerRegistryDeferredHookManager->activateDeferredByTags(
            $tags,
            $this->activateEntry
        );
    }

    /**
     * Activate all deferred handlers belonging to a service namespace.
     *
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash, so 'App\Core' never matches 'App\CoreExtra\Foo'.
     * @api
     * @param string $namespace Fully qualified namespace prefix.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByNamespace(string $namespace): int
    {
        return $this->containerRegistryDeferredHookManager->activateDeferredByNamespace(
            $namespace,
            $this->activateEntry
        );
    }

    /**
     * Activate all deferred handlers whose hook name matches a wildcard pattern.
     *
     * The pattern must contain exactly one trailing asterisk with a literal
     * prefix of at least two characters, e.g. 'mail_*'. Already-active
     * handlers are silently skipped.
     * @api
     * @param string $pattern Wildcard hook-name pattern.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByHookPattern(string $pattern): int
    {
        return $this->containerRegistryDeferredHookManager->activateDeferredByHookPattern(
            $pattern,
            $this->activateEntry
        );
    }

    /**
     * Activate all deferred handlers carrying at least one tag matching any of
     * the given wildcard patterns.
     *
     * Each pattern is its own tag family to wipe: an entry matches when any of
     * its tags matches any pattern (union of families).
     * @api
     * @param array<string> $patterns Tag wildcard patterns.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTagPattern(array $patterns): int
    {
        return $this->containerRegistryDeferredHookManager->activateDeferredByTagPattern(
            $patterns,
            $this->activateEntry
        );
    }
    #endregion

    #region deferred handlers unregisteration methods
    /**
     * Unregister a deferred hook using a first-class callable, array lookup,
     * or class-string identifier.
     * ! sweep all attribute attached to property or method
     * @param HookTargetResolve $target
     * @api
     */
    public function unregisterDeferredByCallable(callable|string|array $target): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByCallable($target);
    }

    /**
     * Unregister all deferred handlers for a specific WordPress hook name.
     * @api
     * @param string $hook WordPress hook name.
     */
    public function unregisterDeferredByHook(string $hook): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByHook($hook);
    }

    /**
     * Unregister all deferred hooks belonging to a specific service class.
     * @api
     * @param class-string|object $class Fully qualified class name.
     */
    public function unregisterDeferredByClass(string|object $class): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByClass(\is_object($class) ? get_class($class) : $class);
    }

    /**
     * Unregister all deferred hooks belonging to a service namespace.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * @api
     * @param string $namespace Fully qualified namespace prefix.
     */
    public function unregisterDeferredByNamespace(string $namespace): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByNamespace($namespace);
    }

    /**
     * Unregister all deferred handlers carrying at least one of the given tags.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * A hook matches when any of its tags intersects the query set.
     * @api
     * @param array<int, string|\BackedEnum> $tags Tag list to unregister.
     */
    public function unregisterDeferredByTags(array $tags): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByTags(HookTagUtilities::normalizeTags($tags));
    }

    /**
     * Unregister all deferred handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * @example ```php
     * $registry->unregisterDeferredByHookPattern('graphql_*')
     * ```
     * @api
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterDeferredByHookPattern(string $pattern): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByHookPattern($pattern);
    }

    /**
     * Unregister all deferred handlers carrying at least one tag matching any
     * of the given wildcard patterns.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * @example ```php
     * $registry->unregisterDeferredByTagPattern(['graphql_*', 'theme_*', 'woocommerce_*'])
     * ```
     * @api
     * each entry in array is its own family to to unregister
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterDeferredByTagPattern(array $patterns): void
    {
        $this->containerRegistryDeferredHookManager->unregisterDeferredByTagPattern($patterns);
    }
    #endregion
    #region main handlers unregisteration methods
    /**
     * Unregister a registered hook via first-class callable, array lookup,
     * or class-string identifier.
     *
     * Sweeps every ACTIVE attribute attached to the resolved property or
     * method (all priorities/attributes of that member).
     *
     * ! Only touches the active pool — deferred entries are never swept and
     * ! will still activate when their trigger fires. Use
     * ! {@see ContainerRegistryDeferredHookManager::unregisterDeferredByCallable()}
     * ! (or unregisterDeferredByHook/ByClass/ByNamespace) for the deferred
     * ! pool.
     *
     * @api
     * @param HookTargetResolve $target
     */
    public function unregisterByCallable(callable|string|array $target): void
    {
        $keyCallable = $this->entriesIndexer->getCallable($this->resolverTarget->resolve($target));
        $entries = $this->entriesIndexer->byCallable[$keyCallable] ?? [];
        foreach ($entries as $uniqueKey) {
            $entry = $this->handlers[$uniqueKey] ?? null;
            if ($entry === null) {
                continue;
            }
            $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
            unset($this->handlers[$uniqueKey]);
        }
    }

    /**
     * Unregister all handlers for a specific WordPress hook name.
     * @api 
     * @param string $hook WordPress hook name.
     */
    public function unregisterByHook(string $hook): void
    {
        $entries = $this->entriesIndexer->byHook[$hook] ?? [];
        foreach ($entries as $uniqueKey) {
            $entry = $this->handlers[$uniqueKey] ?? null;
            if ($entry === null) {
                continue;
            }
            $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
            unset($this->handlers[$uniqueKey]);
        }
    }

    /**
     * Unregister all hooks belonging to a specific service class.
     * @api
     * @param class-string|object $class Fully qualified class name.
     */
    public function unregisterByClass(string|object $class): void
    {
        $className = \is_object($class) ? get_class($class) : $class;
        $entries = $this->entriesIndexer->byClass[$className] ?? [];
        foreach ($entries as $uniqueKey) {
            $entry = $this->handlers[$uniqueKey] ?? null;
            if ($entry === null) {
                continue;
            }
            $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
            unset($this->handlers[$uniqueKey]);
        }
    }

    /**
     * Unregister all active hooks belonging to a service namespace.
     *
     * Only touches the active pool — deferred handlers are never affected.
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash, so 'App\Core' never matches 'App\CoreExtra\Foo'.
     * @api 
     * @param string $namespace Fully qualified namespace prefix.
     */
    public function unregisterByNamespace(string $namespace): void
    {
        $entries = $this->entriesIndexer->byNamespace[$namespace] ?? [];
        foreach ($entries as $uniqueKey) {
            $entry = $this->handlers[$uniqueKey] ?? null;
            if ($entry === null) {
                continue;
            }
            $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
            unset($this->handlers[$uniqueKey]);
        }
    }

    /**
     * Unregister all active handlers carrying at least one of the given tags.
     *
     * Only touches the active pool — deferred handlers are never affected.
     * A hook matches when any of its tags intersects the query set.
     * @api
     * @param array<int, string|\BackedEnum> $tags Tag list to unregister.
     */
    public function unregisterByTags(array $tags): void
    {
        $tags = HookTagUtilities::normalizeTags($tags);

        if ($tags === []) {
            return;
        }

        foreach ($tags as $tag) {
            $entries = $this->entriesIndexer->byTag[$tag] ?? [];
            if (empty($entries)) {
                continue;
            }

            foreach ($entries as $key) {
                if (!isset($this->handlers[$key])) {
                    continue;
                }
                $entry = $this->handlers[$key];
                $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                unset($this->handlers[$key]);
            }
        }
    }

    /**
     * Unregister all active handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the active pool — deferred handlers are never affected.
     * @example ```php
     * $registry->unregisterByHookPattern('graphql_*')
     * ```
     * @api
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterByHookPattern(string $pattern): void
    {
        HookPattern::assertValid($pattern);
        $hooks = $this->entriesIndexer->byHook;
        foreach ($hooks as $hook => $uniqueKeys) {
            foreach ($uniqueKeys as $uniqueKey) {
                if (!HookPattern::matches($hook, $pattern)) {
                    continue;
                }
                $entry = $this->handlers[$uniqueKey] ?? null;
                if ($entry === null) {
                    continue;
                }
                $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                unset($this->handlers[$uniqueKey]);
            }
        }
    }

    /**
     * Unregister all active handlers carrying at least one tag matching any of
     * the given wildcard patterns.
     *
     * Only touches the active pool — deferred handlers are never affected.
     * @example ```php
     * $registry->unregisterByTagPattern(['graphql_*', 'theme_*', 'woocommerce_*'])
     * ```
     * @api
     * each entry in array is its own family to to unregister
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterByTagPattern(array $patterns): void
    {
        if ($patterns === []) {
            return;
        }
        HookPattern::assertValidAll($patterns);
        $entries = $this->entriesIndexer->byTag;
        foreach ($entries as $tag => $uniqueKeys) {
            foreach ($uniqueKeys as $uniqueKey) {
                $entry = $this->handlers[$uniqueKey] ?? null;
                if ($entry === null) {
                    continue;
                }
                if (!HookPattern::matchesAny($entry->tags, $patterns)) {
                    continue;
                }
                $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                unset($this->handlers[$uniqueKey]);
            }
        }
    }
    #endregion

    /**
     * Callback provided to the ContainerRegistryDeferredHookManager: moves a deferred entry into
     * the active pool and registers it with WordPress.
     * @var ActivateEntry
     */
    private \Closure $activateEntry {
        get {
            return $this->activateEntry ??= function (DeferredHookEntryHandler $data): bool {
                // Guard: skip if already activated
                $uniqueKey = $data->toUniqueKey();
                if (isset($this->handlers[$uniqueKey])) {
                    return false;
                }
                $entry = $this->handlers[$uniqueKey] = ContainerRegistryHandlerEntry::fromDeferredEntry($data);
                $weakRef = \WeakReference::create($entry);
                $this->entriesByInvokers[$data->handler] = $weakRef;
                $this->entriesIndexer->setIndexes($entry);
                $entry->setOnEntryRemovalRemoveIndexesCallback($this->destroyIndexes);
                $entry->register();
                $this->queuedHooksCleaner->addContainerRegistryItsEntries([$weakRef]);
                SharedUtils::isDevelopment() && Logger::debug(__METHOD__, 'Activated deferred hook ' . $data->handler->label);
                return true;
            };
        }
    }
    /**
     * @Internal remove a registration from the active pool after its
     * first fire (consume-on-any-evaluation). Idempotent.
     * OR
     * Invoker expired
     * @var (\Closure(HookInvoker $invoker, bool $invokerDied = false): void)
     */
    private \Closure $removeEntry {
        get {
            return $this->removeEntry ??= function (HookInvoker $invoker, bool $invokerDied = false): void {
                $entry = $this->queuedHooksCleaner->getEntry($invoker)
                    ?? isset($this->entriesByInvokers[$invoker])
                    ? $this->entriesByInvokers[$invoker]->get() : null;

                if ($entry === null) return;
                $key = $entry->toUniqueKey();

                if ($invokerDied) {
                    $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                    unset($this->handlers[$key]);
                    unset($this->entriesByInvokers[$invoker]);
                    SharedUtils::isDevelopment() && Logger::debug(__METHOD__, "Entry Died: {$invoker->label}");
                    return;
                };


                if (!isset($this->handlers[$key])) return;

                $entry = $this->handlers[$key];
                /**
                 * Skip unregister hook while still in the same callback stack
                 * 
                 * Skip the WordPress-side removal while the hook is being dispatched:
                 * self removal during dispatch corrupts WP_Hook's iteration
                 * (resort_active_iterations skips the immediately-following priority).
                 * The consumed flag already prevents re-firing, so the callback can
                 * safely linger in $wp_filter until the next invoker order or beginning hook(all).
                 * @link https://core.trac.wordpress.org/ticket/64653
                 */
                $this->queuedHooksCleaner->cleanUpCallback($invoker);
                unset($this->entriesByInvokers[$entry->handler]);
                unset($this->handlers[$key]);
            };
        }
    }

    /**
     * Register hook registrations from the scanner.
     * Pre-builds HookInvoker instances and validates container existence.
     *
     * @param list<HookRegistration> $registrations
     */
    private function registerAll(array $registrations): void
    {
        $normalizeTagValue = HookTagUtilities::normalizeTagValue(...);
        foreach ($registrations as $reg) {
            $registration = $reg instanceof HookRegistration ? $reg : HookRegistration::fromArray($reg);

            if (!$this->container->has($registration->class)) {
                Logger::info(
                    __METHOD__,
                    'Skipping hook ' . ($registration->hook instanceof \Closure ? '(closure)' : $registration->hook . '::' . (string) $registration->priority)
                        . ' — class not in container: ' . $registration->class
                );
                continue;
            }

            try {
                $hookName = $this->planProvider->resolveHookName(
                    $registration->hook,
                    $registration->hookParams,
                    $registration->class . '::' . $registration->method,
                    $registration->class
                );
            } catch (\RuntimeException $e) {
                Logger::error(
                    __METHOD__,
                    'Skipping hook for ' . $registration->class . '::' . $registration->method
                        . ' on ' . ($registration->hook instanceof \Closure ? '(closure)' : $registration->hook . '::' . (string) $registration->priority)
                        . ' — ' . $e->getMessage()
                );
                continue;
            }

            if ($registration->deferRegisterUntilHook === null && $registration->deferRegister === false) {
                try {
                    $allowed = $this->planProvider->evaluateRegistrationGate(
                        $registration->registerIf,
                        $registration->registerIfParams,
                        $registration->class . '::' . $registration->method,
                        $registration->class
                    );
                } catch (\Throwable $e) {
                    Logger::error(
                        __METHOD__,
                        'Skipping hook for ' . $registration->class . '->' . $registration->method . ' on ' . $hookName . '::' . (string) $registration->priority . ' — error ' . $e->getMessage()
                    );
                    continue;
                }
                if (!$allowed) {
                    Logger::info(
                        __METHOD__,
                        'Skipping hook ' . $registration->class . '->' . $registration->method . ' on ' . $hookName . '::' . (string) $registration->priority . ' — registerIf gate returned false.'
                    );
                    continue;
                }
            }

            // Resolve dynamic tags: the attribute accepts either a static tag
            // list or a single callable returning the full resolved list.
            // Entries are normalized to strings (string | string-backed enum).
            try {
                if ($registration->tagCallable !== null) {
                    $resolvedTags = $this->planProvider->resolveTagCallable(
                        $registration->tagCallable,
                        $registration->tagCallableParams ?? [],
                        $registration->class . '::' . $registration->method,
                        $registration->class
                    );
                } else {
                    $resolvedTags = $registration->tags;
                }

                $resolvedTags = array_map(
                    $normalizeTagValue,
                    $resolvedTags
                );
            } catch (\Throwable $e) {
                Logger::error(
                    __METHOD__,
                    'Skipping hook for ' . $registration->class . '->' . $registration->method . ' on ' . $hookName . '::' . (string) $registration->priority . ' — error ' . $e->getMessage()
                );
                continue;
            }

            [$handler, $entry] = $this->createHandlerAndEntry($registration, $hookName, $resolvedTags);

            if (!$registration->deferRegister && $registration->deferRegisterUntilHook === null && $entry instanceof ContainerRegistryHandlerEntry) {
                $this->handlers[$entry->toUniqueKey()] = $entry->register();
            } else {
                $this->containerRegistryDeferredHookManager->deferRegistration(
                    $registration,
                    $entry,
                    $this->activateEntry,
                );
            }
        }
    }

    /**
     * @return array{HookInvoker, ContainerRegistryHandlerEntry|DeferredHookEntryHandler}
     */
    private function createHandlerAndEntry(HookRegistration $registration, string $hookName, array $resolvedTags): array
    {

        $handler = $registration->target === 'method'
            ? HookInvoker::containerMethod(
                container: $this->container,
                planProvider: $this->planProvider,
                hookName: $hookName,
                priority: $registration->priority,
                class: $registration->class,
                method: $registration->method,
                visibility: $registration->visibility,
                type: $registration->type,
                acceptedArgs: $registration->acceptedArgs,
                executeIf: $registration->executeIf,
                executeIfParams: $registration->executeIfParams,
                hookArgNames: $registration->hookArgs,
                once: $registration->once,
                onUnregisterDo: $registration->onUnregisterDo,
                onUnregisterDoParams: $registration->onUnregisterDoParams,
                debugDeferredRegistration: $registration->debugDeferredRegistration,
                debugDeferredRegistrationParams: $registration->debugDeferredRegistrationParams,
                debugLiveRegistration: $registration->debugLiveRegistration,
                debugLiveRegistrationParams: $registration->debugLiveRegistrationParams,
                debugLiveUnregistration: $registration->debugLiveUnregistration,
                debugLiveUnregistrationParams: $registration->debugLiveUnregistrationParams
            )
            : HookInvoker::containerProperty(
                container: $this->container,
                planProvider: $this->planProvider,
                hookName: $hookName,
                priority: $registration->priority,
                class: $registration->class,
                property: $registration->method,
                visibility: $registration->visibility,
                type: $registration->type,
                acceptedArgs: $registration->acceptedArgs,
                executeIf: $registration->executeIf,
                executeIfParams: $registration->executeIfParams,
                hookArgNames: $registration->hookArgs,
                once: $registration->once,
                onUnregisterDo: $registration->onUnregisterDo,
                onUnregisterDoParams: $registration->onUnregisterDoParams,
                debugDeferredRegistration: $registration->debugDeferredRegistration,
                debugDeferredRegistrationParams: $registration->debugDeferredRegistrationParams,
                debugLiveRegistration: $registration->debugLiveRegistration,
                debugLiveRegistrationParams: $registration->debugLiveRegistrationParams,
                debugLiveUnregistration: $registration->debugLiveUnregistration,
                debugLiveUnregistrationParams: $registration->debugLiveUnregistrationParams
            );

        $entry = ($registration->deferRegister || $registration->deferRegisterUntilHook !== null) ?
            new DeferredHookEntryHandler(
                hook: $hookName,
                handler: $handler,
                type: $registration->type,
                priority: $registration->priority,
                acceptedArgs: $registration->acceptedArgs,
                tags: $resolvedTags,
                registerIf: $registration->registerIf,
                registerIfParams: $registration->registerIfParams,
                executeIf: $registration->executeIf,
                executeIfParams: $registration->executeIfParams,
                once: $registration->once,
                onUnregisterDo: $registration->onUnregisterDo,
                onUnregisterDoParams: $registration->onUnregisterDoParams,
                key: HookKey::fromRegistration($registration),
            ) : new ContainerRegistryHandlerEntry(
                hook: $hookName,
                key: HookKey::fromRegistration($registration),
                handler: $handler,
                type: $registration->type,
                priority: $registration->priority,
                acceptedArgs: $registration->acceptedArgs,
                tags: $resolvedTags,
                registerIf: $registration->registerIf,
                registerIfParams: $registration->registerIfParams,
                executeIf: $registration->executeIf,
                executeIfParams: $registration->executeIfParams,
                once: $registration->once,
                onUnregisterDo: $registration->onUnregisterDo,
                onUnregisterDoParams: $registration->onUnregisterDoParams
            );

        $handler->setCleanupCallback($this->removeEntry);

        return [$handler, $entry];
    }
}
