<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use Brick\VarExporter\VarExporter;
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{RuntimeInstancePropertyHookInvoker, RuntimeInstanceHookInvoker, RuntimeCallableHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;
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
 *   public $service { get => $this->service ??= new class(self::class, __PROPERTY__, $this->registry) extends ModuleClassHookMetadata {
 *       public function __construct(
 *           string $parentClass,
 *           string $propertyName,
 *           private WPHooksInstanceRegistry $registry
 *       ) {
 *           parent::__construct($parentClass, $propertyName); 
 *           $registry->registerHooksOn($this); // register
 *           $registry->unregisterHooksOn($this); // unregister (optional)
 *       }
 *       #[Action(hook: 'init')]
 *       public function onInit(): void { ... }
 *      }
 *  } 
 * ```
 *
 *! All attribute hooks are registered eagerly — the plain `deferRegister`
 *! flag is still ignored on the attribute path (no trigger means nothing
 *! would ever activate it), and static methods are silently skipped.
 *! `deferRegisterUntilHook` (registerUnderHook) IS supported: the entry is
 *! held in the deferred pool and auto-activates when the trigger hook fires
 *! — no manual activation API on the runtime path.
 *! Hooks are instance-lifetime scoped: the registry keeps only a weak
 *! reference to the owner, so when the instance is garbage-collected the
 *! handler nukes itself from the pool and wp_filter (GC-aware auto-cleanup).
 *! Closures in attribute parameters (hook name, registerIf, executeIf) ARE
 *! supported: per PHP 8.1 RFC 'Closures in constant expressions' they must be
 *! static closures, scoped to the declaring class — private members resolve
 *! via self:: and the closure is invoked directly (no instance binding).
 *! When a RuntimeWPHookProvider is injected it resolves closure parameters
 *! (hook args by name, then the container, then defaults); without one,
 *! closures are invoked with no arguments (only zero-parameter or
 *! defaulted-parameter closures work).
 *! Manual registration (registerAction() / registerFilter()) remains the
 *! escape hatch for runtime state: hook names, callbacks and condition
 *! closures may capture the surrounding scope directly, and `condition`
 *! closures are invoked directly there too (must return bool).
 * @phpstan-type ActivateRuntimeEntry \Closure(DeferredHookEntryHandler): bool
 */
class WPHooksInstanceRegistry
{
    use HookScannerTrait;

    /**
     * Every registration — attribute-discovered or manual — is owned by
     * exactly one object; unregisterHooksOn($owner) removes all of them at once.
     *
     * @var \WeakMap<object, list<RuntimeRegistryHandlerEntry>>
     */
    private \WeakMap $weakRegistry;
    /** @var \WeakMap<RuntimeInstanceHookInvoker|RuntimeCallableHookInvoker|RuntimeInstancePropertyHookInvoker, RuntimeRegistryHandlerEntry> */
    private \WeakMap $entriesByInvokers;

    /**
     * Ensures registerHooksOn() runs exactly once per object regardless of
     * the order of manual registerAction()/registerFilter() calls.
     *
     * @var \WeakMap<object, bool>
     */
    private \WeakMap $scanned;

    /**
     * @param ?RuntimeWPHookProvider $provider may have ContainerInterface
     */
    public function __construct(
        private InstanceRegistryDeferredHookManager $instanceRegistryDeferredHookManager,
        private QueuedHooksCleaner $queuedHooksCleaner = new QueuedHooksCleaner(),
        private InstanceRegistryRuntimeHookResolver $runtimeResolver = new InstanceRegistryRuntimeHookResolver(),
        public ?WPHooksInstanceObjectCache $cache = null,
        private ?RuntimeWPHookProvider $provider = null,

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
            $cached = $this->cache?->get($instance->parentClassString, $instance->parentProperty);
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
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $method->getName())
                    : $this->runtimeResolver->resolveClosureHook($attr->hook, $instance, $method->getName());
                if ($hook === null) {
                    return;
                }
                $ordinalKey = 'method:' . $method->getName();
                $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;
                $hookKey = new HookKey(
                    class: (string) \spl_object_id($instance),
                    method: $method->getName(),
                    target: 'method',
                    type: $attr instanceof Action ? 'action' : 'filter',
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    attributeIndex: $attributeIndex,
                );

                if ($instance instanceof ModuleClassHookMetadata && !$forceNoCache) {
                    $metadata[] = new InstanceHookMetadata(
                        hook: $hook,
                        type: $type,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        once: $attr->once,
                        executeIf: $attr->executeIf,
                        executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null) : [],
                        registerIf: $attr->registerIf,
                        registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null) : [],
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null) : [],
                        hookArgNames: $this->runtimeResolver->resolveHookArgNames($instance, $method->getName()),
                        target: 'method',
                        targetName: $method->getName(),
                        visibility: $visibility,
                        onUnregisterDo: $attr->onUnregisterDo,
                        onUnregisterDoParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null) : [],
                    );
                }


                if ($attr->deferRegisterUntilHook !== null) {
                    // Deferred entries are gated at activation time (and
                    // re-gated on every activation attempt), never at scan time.
                } elseif ($this->provider !== null) {
                    $gatePassed = $this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $method->getName(), $instance);
                    if (!$gatePassed) {
                        Logger::info(self::class, 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->parentClassString . '->' . $instance->parentProperty : $instance::class) . '->' . $method->getName() . ' with hook ' . $hook . ' and ' . 'priority ' . (string) $attr->priority . ' skipped');
                        return;
                    }
                } elseif (!$this->runtimeResolver->evaluateRegisterIf($attr->registerIf, $instance, $method->getName())) {
                    Logger::info(self::class, 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->parentClassString . '->' . $instance->parentProperty : $instance::class) . '->' . $method->getName() . ' with hook ' . $hook . ' and ' . 'priority ' . (string) $attr->priority . ' skipped');
                    return;
                }
                $weakRef = \WeakReference::create($instance);
                $handler = new RuntimeInstanceHookInvoker(
                    instanceRef: $weakRef,
                    hookName: $hook,
                    priority: $attr->priority,
                    method: $method->getName(),
                    visibility: $visibility,
                    type: $type,
                    acceptedArgs: $attr->acceptedArgs,
                    executeIf: $attr->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null) : [],
                    hookArgNames: $this->runtimeResolver->resolveHookArgNames($instance, $method->getName()),
                    once: $attr->once,
                );

                $record = new RuntimeRegistryHandlerEntry(
                    hook: $hook,
                    handler: $handler,
                    type: $type,
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    registerIf: $attr->registerIf,
                    registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null) : [],
                    executeIf: $attr->executeIf,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null) : [],
                    once: $attr->once,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null) : [],
                    hookKey: $hookKey,
                    owner: $weakRef,
                    callback: null,
                );
                $handler->setCleanupCallback($this->removeRuntimeHook);
                if ($attr->deferRegisterUntilHook === null) {
                    $records[] = $record->register();
                    $this->entriesByInvokers[$handler] = $record;
                    $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$record]);
                    return;
                }

                $this->instanceRegistryDeferredHookManager->deferManualUntilHook(
                    deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                    entry: $record->toDeferredEntryDTO(),
                    activateEntry: $this->activateRuntimeEntry
                );
            }
        );

        $this->scanPropertyHooks(
            $ref,
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records, &$metadata, &$hookKey, $forceNoCache, &$attributeOrdinals): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $property->getName(), $instance)
                    : $this->runtimeResolver->resolveClosureHook($attr->hook, $instance, $property->getName());
                if ($hook === null) {
                    return;
                }

                $ordinalKey = 'property:' . $property->getName();
                $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;
                $hookKey = new HookKey(
                    class: (string) \spl_object_id($instance),
                    method: $target,
                    target: $property->getName(),
                    type: $attr instanceof Action ? 'action' : 'filter',
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    attributeIndex: $attributeIndex,
                );

                if ($instance instanceof ModuleClassHookMetadata && !$forceNoCache) {
                    $metadata[] = new InstanceHookMetadata(
                        hook: $hook,
                        type: $type,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        once: $attr->once,
                        executeIf: $attr->executeIf,
                        executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null) : [],
                        registerIf: $attr->registerIf,
                        registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null) : [],
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null) : [],
                        hookArgNames: $this->provider !== null ? $this->provider->extractPropertyCallableParamNames($property, $instance) : [],
                        target: $target,
                        targetName: $property->getName(),
                        visibility: $visibility,
                        onUnregisterDo: $attr->onUnregisterDo,
                        onUnregisterDoParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null) : [],
                    );
                }

                if ($attr->deferRegisterUntilHook !== null) {
                    // Deferred entries are gated at activation time.
                } elseif ($this->provider !== null) {
                    $gatePassed = $this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null), $property->getName(), $instance);
                    if (!$gatePassed) {
                        Logger::info(self::class, 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->parentClassString . '->' . $instance->parentProperty : $instance::class) . '->' . $property->getName() . ' with hook ' . $hook . ' and ' . 'priority' . (string) $attr->priority . ' skipped');
                        return;
                    }
                } elseif (!$this->runtimeResolver->evaluateRegisterIf($attr->registerIf, $instance, $property->getName())) {
                    Logger::info(self::class, 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->parentClassString . '->' . $instance->parentProperty : $instance::class) . '->' . $property->getName() . ' with hook ' . $hook . ' and ' . 'priority' . (string) $attr->priority . ' skipped');
                    return;
                }

                $weakRef = \WeakReference::create($instance);

                $handler = new RuntimeInstancePropertyHookInvoker(
                    instanceRef: $weakRef,
                    hookName: $hook,
                    priority: $attr->priority,
                    property: $property->getName(),
                    visibility: $visibility,
                    type: $type,
                    acceptedArgs: $attr->acceptedArgs,
                    executeIf: $attr->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null) : [],
                    hookArgNames: $this->provider !== null ? $this->provider->extractPropertyCallableParamNames($property, $instance) : [],
                    once: $attr->once,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null) : []
                );

                $record = new RuntimeRegistryHandlerEntry(
                    hook: $hook,
                    handler: $handler,
                    type: $type,
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    registerIf: $attr->registerIf,
                    registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null) : [],
                    executeIf: $attr->executeIf,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null) : [],
                    once: $attr->once,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null) : [],
                    hookKey: $hookKey,
                    owner: $weakRef,
                    callback: null,
                );

                $handler->setCleanupCallback($this->removeRuntimeHook);

                if ($attr->deferRegisterUntilHook === null) {
                    $records[] = $record->register();
                    $this->entriesByInvokers[$handler] = $record;
                    $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$record]);
                } else {
                    $this->instanceRegistryDeferredHookManager->deferManualUntilHook(
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        entry: $record->toDeferredEntryDTO(),
                        activateEntry: $this->activateRuntimeEntry
                    );
                }
            }
        );

        $this->scanned[$instance] = true;
        $this->weakRegistry[$instance] = array_merge($this->weakRegistry[$instance] ?? [], $records);

        if ($instance instanceof ModuleClassHookMetadata && $metadata !== []) {
            $this->cache?->set($instance->parentClassString, $instance->parentProperty, $metadata);
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
     * Remove every active hook registration bound to one callable target of a
     * live instance (all priorities / attributes of that member).
     *
     * @api
     * @param array{0: object, 1: string}|\Closure $target `[$instance, 'member']`
     *        or a first-class callable produced from the live instance.
     * @return int Number of removed registrations.
     *
     * ! Deferred entries for the same member are dropped too, so they cannot
     * ! resurrect the hook when their trigger fires.
     */
    public function unregisterByCallableInLiveInstance(array|\Closure $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        $count = $this->instanceRegistryDeferredHookManager->unregisterInstanceCallableDeferredEntries($instance, $targetName);

        $records = $this->weakRegistry[$instance] ?? [];
        if ($records === []) return $count;

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
            if ($entry->deferRegisterUntilHook !== null) {
                // Cached deferred entries are gated at activation time only.
            } elseif ($this->provider !== null) {
                $gatePassed = $this->provider->evaluateRuntimeRegisterIf($entry->registerIf, $this->provider->buildCallablePlan($entry->registerIf instanceof \Closure ? $entry->registerIf : null), $entry->targetName, $instance);
                if (!$gatePassed) {
                    Logger::info(self::class, 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->parentClassString . '->' . $instance->parentProperty : $instance::class) . '->' . $entry->targetName . ' with hook ' . $entry->hook . ' and ' . 'priority ' . (string) $entry->priority . ' skipped');
                    continue;
                }
            } elseif (!$this->runtimeResolver->evaluateRegisterIf($entry->registerIf, $instance, $entry->targetName)) {
                Logger::info(self::class, 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->parentClassString . '->' . $instance->parentProperty : $instance::class) . '->' . $entry->targetName . ' with hook ' . $entry->hook . ' and ' . 'priority ' . (string) $entry->priority . ' skipped');
                continue;
            }
            $weakRef = \WeakReference::create($instance);
            $handler = match ($entry->target) {
                'method' => new RuntimeInstanceHookInvoker(
                    instanceRef: $weakRef,
                    hookName: $entry->hook,
                    priority: $entry->priority,
                    method: $entry->targetName,
                    visibility: $entry->visibility,
                    type: $entry->type,
                    acceptedArgs: $entry->acceptedArgs,
                    executeIf: $entry->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $entry->executeIfParams,
                    hookArgNames: $entry->hookArgNames,
                    once: $entry->once,
                    onUnregisterDo: $entry->onUnregisterDo,
                    onUnregisterDoParams: $entry->onUnregisterDoParams,
                ),
                'property', 'property-hook' => new RuntimeInstancePropertyHookInvoker(
                    instanceRef: $weakRef,
                    hookName: $entry->hook,
                    priority: $entry->priority,
                    property: $entry->targetName,
                    visibility: $entry->visibility,
                    type: $entry->type,
                    acceptedArgs: $entry->acceptedArgs,
                    executeIf: $entry->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $entry->executeIfParams,
                    hookArgNames: $entry->hookArgNames,
                    once: $entry->once,
                    onUnregisterDo: $entry->onUnregisterDo,
                    onUnregisterDoParams: $entry->onUnregisterDoParams,
                ),
            };
            $hookKey = new HookKey(
                class: (string) \spl_object_id($instance),
                method: $entry->targetName,
                target: $entry->target,
                priority: $entry->priority,
                type: $entry->type,
                acceptedArgs: $entry->acceptedArgs,
                attributeIndex: $attributeIndex,
            );
            $record = new RuntimeRegistryHandlerEntry(
                hook: $entry->hook,
                handler: $handler,
                type: $entry->type,
                priority: $entry->priority,
                acceptedArgs: $entry->acceptedArgs,
                registerIf: $entry->registerIf,
                registerIfParams: $entry->registerIfParams,
                once: $entry->once,
                executeIf: $entry->executeIf,
                executeIfParams: $entry->executeIfParams,
                onUnregisterDo: $entry->onUnregisterDo,
                onUnregisterDoParams: $entry->onUnregisterDoParams,
                hookKey: $hookKey,
                owner: $weakRef,
                callback: null,
            );
            $handler->setCleanupCallback($this->removeRuntimeHook);
            
            if ($entry->deferRegisterUntilHook === null) {
                $records[] = $record->register();
                $this->entriesByInvokers[$handler] = $record;
                $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$record]);
                continue;
            }

            $this->instanceRegistryDeferredHookManager->deferManualUntilHook(
                deferRegisterUntilHook: $entry->deferRegisterUntilHook,
                entry: $record->toDeferredEntryDTO(),
                activateEntry: $this->activateRuntimeEntry
            );
        }

        return $records;
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
     * @var (\Closure(RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker, bool): void) $removeRuntimeHook
     */
    private \Closure $removeRuntimeHook {
        get {
            return $this->removeRuntimeHook ??= function (
                RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $invoker,
                bool $invokerDied = false,
            ): void {
                if ($invokerDied) {
                    $entry = $this->entriesByInvokers[$invoker] ?? null;
                    if ($entry === null) return;
                    unset($this->entriesByInvokers[$invoker]);
                    $owner = $entry->owner?->get();
                    if ($owner !== null) $this->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                    $this->cleanUpInstance($entry);
                    // SharedUtils::isDevelopment() && Logger::warning(__CLASS__, "{$entry->invokerLabel} died, removing entry");
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

    /**
     * Register an action hook manually for an owner object.
     *
     * Owner inference (when $owner is omitted):
     *   - `[$object, 'method']` array callables → the object;
     *   - first-class callables / bound closures → the bound `$this`;
     *   - invokable objects → the object itself.
     *
     * The hook name, callback and executeIf closure may capture the
     * surrounding scope directly — no container is involved on the runtime
     * registry.
     *
     * @template T
     * @template O
     * @param string $hook Hook name.
     * @param T $callback Callable invoked when the hook fires.
     * @param int $priority Hook priority.
     * @param int $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param bool $once remove self after any executeIf eval fire.
     * @param string|\Closure|null $deferRegisterUntilHook Defer hook registration until certain hook fire
     * @param O|null $owner Owning object (defaults to inference).
     * @internal Manual registration, subject to change; prone to error and not compatible with weak reference
     * @private
     * @throws \RuntimeException when the owner cannot be inferred or the callback is not callable.
     */
    public function registerAction(
        string $hook,
        callable|array $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
        bool $once = false,
        ?\Closure $onUnregisterDo = null,
        string|\Closure|null $deferRegisterUntilHook = null,
        ?object $owner = null,
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register action hook ' . $hook . ' with ' . 'priority' . (string) $priority . ' and ' . 'acceptedArgs' . (string) $acceptedArgs . ' — callback is not callable.';
            Logger::error(self::class, $error);
            throw new \RuntimeException($error);
        }

        $this->register(
            type: 'action',
            hook: $hook,
            callback: $callback,
            priority: $priority,
            acceptedArgs: $acceptedArgs,
            executeIf: $executeIf,
            once: $once,
            onUnregisterDo: $onUnregisterDo,
            deferRegisterUntilHook: $deferRegisterUntilHook,
            owner: $this->runtimeResolver->resolveOwner($callback, $owner),
        );
    }

    /**
     * Register a filter hook manually for an owner object.
     *
     * Semantics identical to {@see registerAction()} — the callback result is
     * returned to the filter pipeline, and the original value passes through
     * untouched when the handler (or its executeIf) fails.
     *
     * @template T
     * @template O
     * @param string $hook Hook name
     * @param T $callback Callable invoked when the hook fires.
     * @param int $priority Hook priority
     * @param int $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param bool $once remove self after any executeIf eval fire.
     * @param string|\Closure|null $deferRegisterUntilHook Defer hook registration until certain hook fire
     * @param O $owner Owning object (defaults to inference).
     * @internal Manual registration, subject to change; prone to error and not compatible with weak reference
     * @private
     * @throws \RuntimeException when the owner cannot be inferred or the callback is not callable.
     */
    public function registerFilter(
        string $hook,
        callable|array $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
        ?\Closure $onUnregisterDo = null,
        bool $once = false,
        string|\Closure|null $deferRegisterUntilHook = null,
        ?object $owner = null,
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register filter hook ' . $hook . ' with ' . 'priority' . (string) $priority . ' and ' . 'acceptedArgs' . (string) $acceptedArgs . ' — callback is not callable.';
            Logger::error(self::class, $error);
            throw new \RuntimeException($error);
        }

        $this->register(
            type: 'filter',
            hook: $hook,
            callback: $callback,
            priority: $priority,
            acceptedArgs: $acceptedArgs,
            executeIf: $executeIf,
            once: $once,
            onUnregisterDo: $onUnregisterDo,
            deferRegisterUntilHook: $deferRegisterUntilHook,
            owner: $this->runtimeResolver->resolveOwner($callback, $owner),
        );
    }

    /**
     * Shared core for manual registration: wraps the callback in a
     * RuntimeCallableHookInvoker, registers it with WordPress immediately
     * and records it under the owner for lifetime-scoped unregistration.
     *
     * @template T
     * @template O
     * @param 'action'|'filter' $type 'action' or 'filter'.
     * @param string $hook Hook name.
     * @param T $callback Callable invoked when the hook fires.
     * @param int $priority Hook priority.
     * @param int $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param bool $once remove self after any executeIf eval fire.
     * @param string|\Closure|null $deferRegisterUntilHook Defer hook registration until certain hook fire
     * @param O $owner Owning object (resolved by the caller).
     *
     * @throws \RuntimeException when the callback is not callable.
     */
    private function register(
        string $type,
        string $hook,
        callable $callback,
        int $priority,
        int $acceptedArgs,
        ?\Closure $executeIf,
        ?\Closure $onUnregisterDo,
        object $owner,
        bool $once = false,
        string|\Closure|null $deferRegisterUntilHook = null,
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register ' . $type . ' hook ' . $hook . ' with ' . 'priority' . (string) $priority . ' and ' . 'acceptedArgs' . (string) $acceptedArgs . ' — callback is not callable.';
            Logger::error(self::class, $error);
            throw new \RuntimeException($error);
        }

        $existing = $this->weakRegistry[$owner] ?? [];
        foreach ($existing as $record) {
            if (
                $record->hook === $hook
                && ($record->callback ?? null) === $callback
                && $record->priority === $priority
            ) {
                return;
            }
        }
        $hookKey = new HookKey(
            class: (string) \spl_object_id($owner),
            method: '#manual-registration:' . $this->manualRegistrationIdentity($callback),
            target: '#manual',
            type: $type,
            priority: $priority,
            acceptedArgs: $acceptedArgs,
        );

        $handler = new RuntimeCallableHookInvoker(
            callback: $callback,
            hookName: $hook,
            priority: $priority,
            executeIf: $executeIf,
            type: $type,
            acceptedArgs: $acceptedArgs,
            once: $once,
            onUnregisterDo: $onUnregisterDo,
        );
        $record = new RuntimeRegistryHandlerEntry(
            hook: $hook,
            handler: $handler,
            type: $type,
            priority: $priority,
            acceptedArgs: $acceptedArgs,
            registerIf: null,
            registerIfParams: [],
            once: $once,
            executeIf: $executeIf,
            executeIfParams: [],
            onUnregisterDo: $onUnregisterDo,
            onUnregisterDoParams: [],
            hookKey: $hookKey,
            owner: \WeakReference::create($owner),
            callback: $callback,
        );
        $this->entriesByInvokers[$handler] = $record;
        $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$record]);
        $handler->setCleanupCallback($this->removeRuntimeHook);

        if ($deferRegisterUntilHook !== null) {
            $this->instanceRegistryDeferredHookManager->deferManualUntilHook(
                deferRegisterUntilHook: $deferRegisterUntilHook,
                entry: $record->toDeferredEntryDTO(),
                activateEntry: $this->activateRuntimeEntry
            );
            return;
        }

        $existing[] = $record->register();
        $this->weakRegistry[$owner] = $existing;
    }

    /**
     * Stable per-callback identity used to disambiguate manual registrations.
     *
     * Manual registrations share the same hook/type/priority/acceptedArgs and
     * carry no class/method pair, so their HookKey needs a callback-derived
     * fingerprint to stay unique inside the deferred pool.
     *
     * @param callable|array $callback Registered callback.
     */
    private function manualRegistrationIdentity(callable $callback): string
    {
        return match (true) {
            is_array($callback) => (is_object($callback[0])
                ? $callback[0]::class . '#' . (string) \spl_object_id($callback[0])
                : (string) $callback[0]) . '::' . $callback[1],
            is_string($callback) => $callback,
            is_object($callback) => $callback::class . '#' . (string) \spl_object_id($callback),
            default => 'callable',
        };
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

    /**
     * @param string|null $file file path cache to configure
     */
    public function __construct(private ?string $file = null)
    {
        $file !== null && $this->cacheState->loadCache();
    }

    public function __destruct()
    {
        $this->flush();
    }

    /** 
     * @var class@anonymous::class
     */
    private ?object $cacheState {
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
