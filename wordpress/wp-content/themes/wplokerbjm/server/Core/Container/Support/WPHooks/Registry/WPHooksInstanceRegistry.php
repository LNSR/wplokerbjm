<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use Brick\VarExporter\VarExporter;
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{RuntimeInstancePropertyHookInvoker, RuntimeInstanceHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\{InstanceRegistryDeferredHookManager, DeferredHookEntryHandler};

/**
 *  @example on Property Hook:
 * ```php
 *  class Service {
 *   public $service { get =>$this->service ??= new class(self::class, __PROPERTY__, $this->registry) extends ModuleClassHookMetadata {
 *       public function __construct(
 *           string $parentClass,
 *           string $propertyName,
 *           private WPHooksInstanceRegistry $registry
 *       ) {
 *           parent::__construct($parentClass,$propertyName); 
 *           $registry->registerHooksOn($this); // register
 *           $registry->unregisterHooksOn($this); // unregister (optional)
 *       }
 *       #[Action(hook: 'init')]
 *       public function onInit(): void { ... }
 *      }
 *  } 
 * ```
 *
 *! `deferRegister` holds the entry in the deferred pool: together with
 *! `deferRegisterUntilHook` it activates automatically when the trigger
 *! fires; on its own it waits for
 *! {@see activateDeferredByCallableInLiveInstance()}. Static methods are
 *! silently skipped.
 *! Hooks are instance-lifetime scoped: the registry keeps only a weak
 *! reference to the owner, so when the instance is garbage-collected the
 *! handler nukes itself from the pool and wp_filter (GC-aware auto-cleanup).
 *! Closures in attribute parameters (hook name, registerIf, executeIf) ARE
 *! supported: per PHP 8.1 RFC 'Closures in constant expressions' they must be
 *! static closures, scoped to the declaring class — private members resolve
 *! via self:: and the closure is invoked directly (no instance binding).
 *! The WPHookPlanProvider resolves closure parameters (hook args by name,
 *! then the container, then defaults) — identical semantics to the container
 *! registry.
 * @phpstan-type ActivateRuntimeEntry \Closure(DeferredHookEntryHandler): bool
 */
class WPHooksInstanceRegistry
{
    use HookScannerTrait;

    /**
     * Every registration — attribute-discovered — is owned by
     * exactly one object; unregisterHooksOn($owner) removes all of them at once.
     *
     * @var \WeakMap<object, list<RuntimeRegistryHandlerEntry>>
     */
    protected \WeakMap $weakRegistry;
    /** @var \WeakMap<RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker, RuntimeRegistryHandlerEntry> */
    protected \WeakMap $entriesByInvokers;

    /**
     * Ensures registerHooksOn() runs exactly once per object.
     *
     * @var \WeakMap<object, bool>
     */
    private \WeakMap $scanned;

    /**
     * @param WPHookPlanProvider $provider resolves closure parameters from the container
     */
    public function __construct(
        private InstanceRegistryDeferredHookManager $instanceRegistryDeferredHookManager,
        private WPHookPlanProvider $provider,
        private QueuedHooksCleaner $queuedHooksCleaner = new QueuedHooksCleaner(),
        private InstanceRegistryRuntimeHookResolver $runtimeResolver = new InstanceRegistryRuntimeHookResolver(),
        public readonly WPHooksInstanceObjectCache $cache = new WPHooksInstanceObjectCache(),

    ) {
        $this->weakRegistry = new \WeakMap();
        $this->scanned = new \WeakMap();
        $this->entriesByInvokers = new \WeakMap();
    }

    /**
     * Scan and register hooks on the given object instance.
     *
     * Non-static methods and properties annotated with #[Action] or #[Filter]
     * are registered via add_action() / add_filter() immediately. Calling
     * this on an already-registered instance is a no-op.
     *
     * @example
     * ```php
     * $service = new class(...$args) {
     *     #[Action(hook: 'init')]
     *     public function init(): void { ... }
     * };
     * $runtimeRegistry->registerHooksOn($service); // register hooks
     * ```
     * @api
     * @param object|ModuleClassHookMetadata $instance An instantiated object with hook-annotated methods/properties.
     * @param ?string $autoUnregisterAtHook automatic removal of whole hooks in object at certain hook lifecycle
     * @param bool $forceNoCache dynamic instance shape
     */
    public function registerHooksOn(object $instance, ?string $autoUnregisterAtHook = null, bool $forceNoCache = false): void
    {
        if (isset($this->scanned[$instance])) {
            return;
        }
        /** @var list<InstanceHookMetadata> $metadata */
        $metadata = [];
        /** @var RuntimeRegistryHandlerEntry[] $records */
        $records = [];
        /** @var array<string, int> $attributeOrdinals */
        $attributeOrdinals = [];
        if ($instance instanceof ModuleClassHookMetadata && !$forceNoCache) {
            $cached = $this->cache->get($instance->parentClassString, $instance->parentProperty);
            if ($cached !== null) {
                $this->scanned[$instance] = true;
                $this->weakRegistry[$instance] = $this->registerCachedEntries($cached, $instance);
                \is_string($autoUnregisterAtHook) && $this->scheduleAutoUnregister($instance, $autoUnregisterAtHook);
                return;
            }
        }

        $ref = new ReflectionClass($instance);

        $this->scanMethodHooks(
            $ref,
            function (\ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($instance, &$records, &$metadata, $forceNoCache, &$attributeOrdinals): void {
                $targetName = $method->getName();
                $ordinalKey = 'method:' . $targetName;
                $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;

                $entryMeta = new InstanceHookMetadata(
                    hook: $attr->hook instanceof \Closure
                        ? $this->provider->resolveHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $targetName, $instance)
                        : $attr->hook,
                    type: $type,
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    once: $attr->once,
                    deferRegister: $attr->deferRegister,
                    executeIf: $attr->executeIf,
                    executeIfParams: $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null),
                    registerIf: $attr->registerIf,
                    registerIfParams: $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null),
                    deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                    deferRegisterUntilHookParams: $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                    hookArgNames: $this->provider->buildCallablePlanFromReflector($method)['params'],
                    target: 'method',
                    targetName: $targetName,
                    visibility: $visibility,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null),
                );

                if ($instance instanceof ModuleClassHookMetadata && !$forceNoCache) {
                    $metadata[] = $entryMeta;
                }

                $this->processMetadataEntry($entryMeta, $instance, $attributeIndex, $records);
            }
        );

        $this->scanPropertyHooks(
            $ref,
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records, &$metadata, $forceNoCache, &$attributeOrdinals): void {
                $targetName = $property->getName();
                $ordinalKey = 'property:' . $targetName;
                $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;

                $entryMeta = new InstanceHookMetadata(
                    hook: $attr->hook instanceof \Closure
                        ? $this->provider->resolveHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $targetName, $instance)
                        : $attr->hook,
                    type: $type,
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    once: $attr->once,
                    deferRegister: $attr->deferRegister,
                    executeIf: $attr->executeIf,
                    executeIfParams: $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null),
                    registerIf: $attr->registerIf,
                    registerIfParams: $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null),
                    deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                    deferRegisterUntilHookParams: $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                    hookArgNames: $this->provider->extractPropertyCallablePlan($property, $instance)['params'],
                    target: $target,
                    targetName: $targetName,
                    visibility: $visibility,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null),
                );

                if ($instance instanceof ModuleClassHookMetadata && !$forceNoCache) {
                    $metadata[] = $entryMeta;
                }

                $this->processMetadataEntry($entryMeta, $instance, $attributeIndex, $records);
            }
        );

        $this->scanned[$instance] = true;
        $this->weakRegistry[$instance] = array_merge($this->weakRegistry[$instance] ?? [], $records);

        if ($instance instanceof ModuleClassHookMetadata && $metadata !== []) {
            $this->cache->set($instance->parentClassString, $instance->parentProperty, $metadata);
        }
        \is_string($autoUnregisterAtHook) && $this->scheduleAutoUnregister($instance, $autoUnregisterAtHook);
    }
    /**
     * Remove all hooks previously registered for the given instance.
     *
     * Calls remove_action() / remove_filter() for each registered hook and
     * clears internal tracking. Calling this on an instance that was never
     * registered is a no-op.
     * @api
     * @param object|ModuleClassHookMetadata $instance The instance whose hooks should be removed.
     */
    public function unregisterHooksOn(object $instance): void
    {
        $this->instanceRegistryDeferredHookManager->unregisterInstanceMatchingDeferredEntries($instance);

        if (!isset($this->weakRegistry[$instance])) {
            return;
        }

        foreach ($this->weakRegistry[$instance] as $record) {
            $this->queuedHooksCleaner->immediatelyScheduleRemove($record);
        }

        unset($this->weakRegistry[$instance]);
        unset($this->scanned[$instance]);
    }

    /**
     * Activate every deferred registration bound to one callable target of a
     * live instance (all priorities / attributes of that member).
     *
     * Mirrors {@see WPHooksContainerRegistry::activateDeferredByCallable()} on
     * the runtime path. Entries carrying `deferRegister` without a trigger sit
     * in the deferred pool until this call; entries with
     * `deferRegisterUntilHook` may also be activated early. The `registerIf`
     * gate is re-evaluated immediately before activation, so a rejecting gate
     * leaves the entry parked for a later attempt.
     *
     * @api
     * @param array{0: object, 1: string}|\Closure|object $target `[$instance, 'member']`,
     *        a first-class callable/property-hook closure bound to the live instance,
     *        or an invokable object (targets its `__invoke`).
     * @return int Number of newly activated registrations.
     */
    public function activateDeferredByCallableInLiveInstance(object|callable|array|string $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        return $this->instanceRegistryDeferredHookManager->activateInstanceCallableDeferredEntries(
            $instance,
            $targetName,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Remove every active hook registration bound to one callable target of a
     * live instance (all priorities / attributes of that member).
     *
     * @api
     * @param array{0: object, 1: string}|\Closure|object $target `[$instance, 'member']`,
     *        a first-class callable/property-hook closure bound to the live instance,
     *        or an invokable object (targets its `__invoke`).
     * @return int Number of removed active registrations.
     *
     * ! Only touches the active pool — deferred entries are never swept and
     * ! still activate when their trigger fires or when
     * ! {@see self::activateDeferredByCallableInLiveInstance()} is called. Use
     * ! {@see self::unregisterDeferredByCallableInLiveInstance()} for the deferred pool.
     */
    public function unregisterByCallableInLiveInstance(object|callable|array|string $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        $count = 0;

        $records = $this->weakRegistry[$instance] ?? [];
        if ($records === []) return 0;

        $remaining = [];
        foreach ($records as $record) {
            if (!$this->runtimeResolver->matchesCallableTarget($record->hookKey, $targetName)) {
                $remaining[] = $record;
                continue;
            }

            $this->queuedHooksCleaner->immediatelyScheduleRemove($record);
            $count++;
        }

        $this->weakRegistry[$instance] = $remaining;
        return $count;
    }

    /**
     * Remove every deferred registration bound to one callable target of a
     * live instance, without touching its active registrations.
     *
     * The counterpart of {@see self::activateDeferredByCallableInLiveInstance()}:
     * use it when the member must not activate later, e.g. after a runtime
     * disable, while still keeping any already-active priorities.
     *
     * @api
     * @param array{0: object, 1: string}|\Closure|object $target `[$instance, 'member']`,
     *        a first-class callable/property-hook closure bound to the live instance,
     *        or an invokable object (targets its `__invoke`).
     * @return int Number of removed deferred registrations.
     */
    public function unregisterDeferredByCallableInLiveInstance(object|callable|array|string $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        return $this->instanceRegistryDeferredHookManager->unregisterInstanceCallableDeferredEntries(
            $instance,
            $targetName,
        );
    }


    private function scheduleAutoUnregister(object $instance, string $hook): void
    {
        if (\did_action($hook) >= 1) {
            Logger::warning(self::class, 'Hook ' . $hook . ' has passed lifecycle execution, cannot schedule unregister there');
            return;
        }
        $owner = \WeakReference::create($instance);

        \add_action($hook, function () use ($owner, $hook): void {
            if (($instance = $owner->get()) !== null) {
                $this->unregisterHooksOn($instance);
            }
            \remove_action($hook, \Closure::getCurrent(), PHP_INT_MIN);
        }, PHP_INT_MIN, 0);
    }

    /**
     * Re-hydrate cached metadata entries into live handlers bound to
     * $instance, applying the registerIf gate per entry. Entries carrying
     * `deferRegisterUntilHook` are re-hydrated into the deferred pool
     * (trigger listener included) instead of being registered eagerly.
     *
     * @param list<InstanceHookMetadata> $entries Cached metadata entries.
     * @param object|ModuleClassHookMetadata $instance Owner instance to bind handlers to.
     *
     * @return list<RuntimeRegistryHandlerEntry>
     */
    private function registerCachedEntries(array $entries, object $instance): array
    {
        /** @var list<RuntimeRegistryHandlerEntry> $records */
        $records = [];

        foreach ($entries as $attributeIndex => $entry) {
            $this->processMetadataEntry($entry, $instance, $attributeIndex, $records);
        }

        return $records;
    }

    /**
     * Process an individual hook metadata entry: checks the registration gate, 
     * creates the handler and entry via createHandlerAndEntry, and registers 
     * or defers the hook.
     */
    private function processMetadataEntry(
        InstanceHookMetadata $entryMeta,
        object $instance,
        int $attributeIndex,
        array &$records
    ): void {
        $isDeferred = $entryMeta->deferRegister || $entryMeta->deferRegisterUntilHook !== null;

        if (!$isDeferred) {
            $gatePassed = $this->provider->evaluateRegistrationGate(
                $entryMeta->registerIf,
                $entryMeta->registerIfParams ?? $this->provider->buildCallablePlan($entryMeta->registerIf instanceof \Closure ? $entryMeta->registerIf : null),
                $entryMeta->targetName,
                $instance
            );
            if (!$gatePassed) {
                $ownerLabel = $instance instanceof ModuleClassHookMetadata
                    ? $instance->parentClassString . '->' . $instance->parentProperty
                    : $instance::class;
                Logger::info(
                    self::class,
                    'registerIf for ' . $ownerLabel . '->' . $entryMeta->targetName . ' with hook ' . $entryMeta->hook . ' and priority ' . (string) $entryMeta->priority . ' skipped'
                );
                return;
            }
        }

        [$handler, $record] = $this->createHandlerAndEntry($entryMeta, $instance, $attributeIndex, $isDeferred);

        if ($isDeferred) {
            $this->instanceRegistryDeferredHookManager->deferManualRegistration(
                entry: $record,
                deferRegisterUntilHook: $entryMeta->deferRegisterUntilHook,
                activateEntry: $this->activateRuntimeEntry
            );
        } else {
            $records[] = $record->register();
            $this->entriesByInvokers[$handler] = $record;
            $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$record]);
        }
    }

    /**
     * @param bool $isDeferred
     * @return array{
     *  RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker, 
     *  ($isDeferred is true ? DeferredHookEntryHandler : RuntimeRegistryHandlerEntry)
     * }
     */
    private function createHandlerAndEntry(
        InstanceHookMetadata $metadata,
        object $instance,
        int $attributeIndex,
        bool $isDeferred = false
    ): array {
        $weakRef = \WeakReference::create($instance);
        $hookKey = new HookKey(
            class: (string) \spl_object_id($instance),
            method: $metadata->targetName,
            target: $metadata->target,
            priority: $metadata->priority,
            type: $metadata->type,
            acceptedArgs: $metadata->acceptedArgs,
            attributeIndex: $attributeIndex,
        );
        $handler = match ($metadata->target) {
            'method' => new RuntimeInstanceHookInvoker(
                instanceRef: $weakRef,
                hookName: $metadata->hook,
                priority: $metadata->priority,
                method: $metadata->targetName,
                visibility: $metadata->visibility,
                type: $metadata->type,
                acceptedArgs: $metadata->acceptedArgs,
                executeIf: $metadata->executeIf,
                hookPlanProvider: $this->provider,
                executeIfParams: $metadata->executeIfParams,
                hookArgNames: $metadata->hookArgNames,
                once: $metadata->once,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
            ),
            'property', 'property-hook' => new RuntimeInstancePropertyHookInvoker(
                instanceRef: $weakRef,
                hookName: $metadata->hook,
                priority: $metadata->priority,
                property: $metadata->targetName,
                visibility: $metadata->visibility,
                type: $metadata->type,
                acceptedArgs: $metadata->acceptedArgs,
                executeIf: $metadata->executeIf,
                hookPlanProvider: $this->provider,
                executeIfParams: $metadata->executeIfParams,
                hookArgNames: $metadata->hookArgNames,
                once: $metadata->once,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
            ),
        };
        $entry = match ($isDeferred) {
            true => new DeferredHookEntryHandler(
                hook: $metadata->hook,
                handler: $handler,
                type: $metadata->type,
                priority: $metadata->priority,
                acceptedArgs: $metadata->acceptedArgs,
                tags: [],
                registerIf: $metadata->registerIf,
                registerIfParams: $metadata->registerIfParams,
                executeIf: $metadata->executeIf,
                executeIfParams: $metadata->executeIfParams,
                once: $metadata->once,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
                key: $hookKey,
                instance: $weakRef,
            ),
            default => new RuntimeRegistryHandlerEntry(
                hook: $metadata->hook,
                handler: $handler,
                type: $metadata->type,
                priority: $metadata->priority,
                acceptedArgs: $metadata->acceptedArgs,
                registerIf: $metadata->registerIf,
                registerIfParams: $metadata->registerIfParams,
                once: $metadata->once,
                executeIf: $metadata->executeIf,
                executeIfParams: $metadata->executeIfParams,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
                hookKey: $hookKey,
                owner: $weakRef,
            ),
        };
        $handler->setCleanupCallback($this->removeRuntimeHook);
        return [$handler, $entry];
    }

    /**
     * Activate a deferred runtime entry: register the handler with WordPress
     * and record it under the owner instance.
     * @var ActivateRuntimeEntry
     * @return bool True when the entry was activated.
     */
    private \Closure $activateRuntimeEntry {
        get => $this->activateRuntimeEntry ??= function (DeferredHookEntryHandler $data): bool {
            $owner = $data->instance?->get();
            if ($owner === null) {
                return false;
            }

            $record = RuntimeRegistryHandlerEntry::fromDeferredEntryDTO($data);

            $records = $this->weakRegistry[$owner] ?? [];
            $records[] = $record->register();
            $this->weakRegistry[$owner] = $records;
            $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$record]);
            $this->entriesByInvokers[$record->handler] = $record;
            SharedUtils::isDevelopment() && Logger::debug(self::class, 'Activated deferred hook ' . $data->handler->label);

            return true;
        };
    }

    /**
     * Detach a runtime handler from WordPress (once-consume or
     * instance-lifetime cleanup). The owner is passed as a WeakReference so
     * the callback never keeps the instance alive — only the record drop
     * touches it, and only when it still exists.
     * @internal
     * @var (\Closure(RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker, bool = false): void) $removeRuntimeHook
     */
    private \Closure $removeRuntimeHook {
        get {
            return $this->removeRuntimeHook ??= function (
                RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $invoker,
                bool $invokerDied = false,
            ): void {
                if ($invokerDied) {
                    $entry = $this->entriesByInvokers[$invoker] ?? null;
                    if ($entry === null) return;
                    unset($this->entriesByInvokers[$invoker]);
                    $owner = $entry->owner?->get();
                    if ($owner !== null) $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                    $this->cleanUpInstance($entry);
                    SharedUtils::isDevelopment() && Logger::debug(__CLASS__, "Entry Died: {$entry->invokerLabel}");
                    return;
                }

                $entry = $this->queuedHooksCleaner->getEntry($invoker);
                if ($entry === null) return;

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
                $this->cleanUpInstance($entry);
            };
        }
    }

    private function cleanUpInstance(RuntimeRegistryHandlerEntry $entry): void
    {
        $owner = $entry->owner?->get();
        if ($owner === null || !isset($this->weakRegistry[$owner])) return;
        $this->weakRegistry[$owner] = array_filter(
            $this->weakRegistry[$owner],
            static fn(RuntimeRegistryHandlerEntry $record): bool => $record !== $entry,
        );
    }
}

/**
 * File-backed metadata cache for runtime-registered hook sites.
 *
 * Anonymous hook classes extending ModuleClassHookMetadata carry a
 * stable (parentClass, parentProperty) pair that uniquely identifies the
 * property-hook site. The reflected metadata (resolved hook names, plans,
 * hook-arg names) for that site is accumulated in an in-memory buffer during
 * the request and flushed atomically to WPHooksInstanceObjectCache.php, so repeated
 * registerHooksOn() calls skip all reflection.
 *
 * Per-instance state (owner instance, WeakReference, remove callbacks) is
 * intentionally NOT cached — only scan-derived metadata.
 * 
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 * @template TClass of class-string
 * @phpstan-type TCache array<TClass, array<property-string<TClass>&property-hook-string<TClass>, list<InstanceHookMetadata>&list<InstanceHookMetadataData>>>
 * @internal
 */
class WPHooksInstanceObjectCache
{

    public private(set) bool $isDirty = false;

    /**
     * @param string|null $file file path cache to configure
     */
    public function __construct(private ?string $file = null)
    {
        $file !== null && $this->cacheState->loadCache();
    }

    public function __destruct()
    {
        if (!$this->isDirty) return;
        $this->flush();
    }

    /** 
     * @var class@anonymous::class
     */
    protected ?object $cacheState {
        get {
            return $this->cacheState ??= new class($this->file) {
                public private(set) bool $alreadyLoaded = false;

                /** @var TCache */
                public array $bufferRuntime = [];
                /** @var TCache */
                public array $loadedCache = [];

                public function __construct(private readonly ?string $file) {}

                public function loadCache(): void
                {
                    if ($this->file === null || !is_readable($this->file)) {
                        return;
                    }
                    $this->loadedCache = $this->mapCache(require $this->file, 'fromArray');
                    $this->alreadyLoaded = true;
                }

                /**
                 * @param TCache $cache
                 * @return TCache
                 */
                public function extractCacheToArray(array $cache): array
                {
                    return $this->mapCache($cache, 'toArray');
                }

                /**
                 * @template T
                 * @param T $c
                 * @param 'toArray'|'fromArray' $operation
                 * @return TCache
                 */
                private function mapCache(array $c, string $operation): array
                {
                    return \array_map(
                        // lvl1: parentClass
                        static fn(array $sites): array => \array_map(
                            // lvl2: parentProperty
                            static fn(array $entries): array => \array_map(
                                // lvl3: InstanceHookMetadata
                                match ($operation) {
                                    'toArray' => static fn(
                                        /** @param InstanceHookMetadata|InstanceHookMetadataData $entry */
                                        InstanceHookMetadata|array $entry
                                    ): array => $entry instanceof InstanceHookMetadata ? $entry->toArray() : $entry,
                                    'fromArray' => static fn(
                                        /** @param InstanceHookMetadata|InstanceHookMetadataData $entry */
                                        InstanceHookMetadata|array $entry
                                    ): InstanceHookMetadata => $entry instanceof InstanceHookMetadata ? $entry : InstanceHookMetadata::fromArray($entry),
                                },
                                $entries,
                            ),
                            $sites,
                        ),
                        $c,
                    );
                }
            };
        }
    }

    /**
     * Clear all runtime hooks cache.
     */
    public function clearCacheFile(): void
    {
        if (!empty($this->file) && file_exists($this->file)) {
            try {
                unlink($this->file);
            } catch (\Throwable $th) {
                Logger::Error(static::class, 'Failed to clear cache file: ' . $th->getMessage());
            }
        }
    }

    /**
     * @param class-string<TClass> $parentClass
     * @param property-string<TClass>&property-hook-string<TClass> $parentProperty
     *
     * @return list<InstanceHookMetadata>|null
     */
    public function get(string $parentClass, string $parentProperty): ?array
    {
        return $this->cacheState->loadedCache[$parentClass][$parentProperty]
            ?? $this->cacheState->bufferRuntime[$parentClass][$parentProperty]
            ?? null;
    }

    /**
     * @param class-string<TClass> $parentClass
     * @param property-string<TClass>&property-hook-string<TClass> $parentProperty
     * @param list<InstanceHookMetadata> $metadata
     */
    public function set(string $parentClass, string $parentProperty, array $metadata): void
    {
        $this->cacheState->bufferRuntime[$parentClass][$parentProperty] = $metadata;
        $this->isDirty = true;
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        if ($this->file === null || $this->cacheState->bufferRuntime === []) {
            return;
        }

        $allCache = $this->cacheState->bufferRuntime;
        if (is_file($this->file)) {
            $allCache = array_replace_recursive($this->cacheState->loadedCache, $this->cacheState->bufferRuntime);
        }

        $directory = dirname($this->file);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $exported = VarExporter::export(
            $allCache,
            VarExporter::CLOSURE_SNAPSHOT_USES | VarExporter::ADD_RETURN | VarExporter::ADD_TYPE_HINTS
        );

        $content = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated WP Hooks Runtime Cache\n * Generated at: " . date('Y-m-d H:i:s') . "\n */\n\n" . $exported;
        $content = \str_replace('__CLASS__', 'self::class', $content);
        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
            rename($tmp, $this->file);
        }
    }
}
