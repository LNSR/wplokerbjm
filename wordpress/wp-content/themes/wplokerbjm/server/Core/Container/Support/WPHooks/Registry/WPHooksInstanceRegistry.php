<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use Brick\VarExporter\VarExporter;
use RuntimeHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{RuntimeInstancePropertyHookInvoker, RuntimeInstanceHookInvoker, RuntimeCallableHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Core\Container\Support\WPHooks\DeferredHookEntryDTO;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\InstanceHookMetadata;

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
 * @phpstan-import-type DeferredHookEntry from DeferredHooksTrait
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @template THookName of string
 */
class WPHooksInstanceRegistry
{
    use HookScannerTrait;
    use DeferredHooksTrait;

    /**
     * Every registration — attribute-discovered or manual — is owned by
     * exactly one object; unregisterHooksOn($owner) removes all of them at once.
     *
     * @var \WeakMap<object, list<RuntimeRegistryHandlerEntry>>
     */
    private \WeakMap $weakRegistry;

    /**
     * Ensures registerHooksOn() runs exactly once per object regardless of
     * the order of manual registerAction()/registerFilter() calls.
     *
     * @var \WeakMap<object, bool>
     */
    private \WeakMap $scanned;
    /** @var array<THookName, list<RuntimeRegistryHandlerEntry>> */
    public private(set) array $queuedRemovalEntry = [];

    public function __construct(
        private HookRuntimeResolver $runtimeResolver = new HookRuntimeResolver(),
        private EntriesIndexer $entriesIndexer = new EntriesIndexer(),
        public ?WPHooksInstanceObjectCache $cache = null,
        private readonly ?RuntimeWPHookProvider $provider = null,
    ) {
        $this->weakRegistry = new \WeakMap();
        $this->scanned = new \WeakMap();
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
     */
    public function registerHooksOn(object $instance, ?string $autoUnregisterAtHook = null): void
    {
        if (isset($this->scanned[$instance])) {
            return;
        }
        /** @var list<InstanceHookMetadata> $metadata */
        $metadata = [];
        /** @var RuntimeRegistryHandlerEntry[] $records */
        $records = [];
        $hasDeferred = false;
        if ($instance instanceof ModuleClassHookMetadata) {
            $cached = $this->cache?->get($instance->getParentClass(), $instance->parentProperty);
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
            function (\ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($instance, &$records, &$metadata, &$hasDeferred, &$hookKey): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $method->getName())
                    : $this->runtimeResolver->resolveClosureHook($attr->hook, $instance, $method->getName());
                if ($hook === null) {
                    return;
                }
                $hookKey = new HookKey(
                    class: spl_object_hash($instance),
                    method: $method->getName(),
                    target: 'method',
                    type: $attr instanceof Action ? 'action' : 'filter',
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                );

                if ($instance instanceof ModuleClassHookMetadata) {
                    $metadata[] = new InstanceHookMetadata(
                        hook: $hook,
                        type: $type,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        once: $attr->once,
                        executeIf: $attr->executeIf,
                        executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                        registerIf: $attr->registerIf,
                        registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf) : [],
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null) : [],
                        hookArgNames: $this->provider !== null ? $this->runtimeResolver->resolveHookArgNames($instance, $method->getName()) : [],
                        target: 'method',
                        targetName: $method->getName(),
                        visibility: $visibility,
                    );
                }


                if ($this->provider !== null) {
                    $gatePassed = $this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $method->getName(), $instance);
                    if (!$gatePassed) {
                        Logger::warning('WPHooksInstanceRegistry', 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $method->getName() . ' with hook ' . $hook . ' and ' . 'priority ' . (string) $attr->priority . ' skipped');
                        return;
                    }
                } elseif (!$this->evaluateRegisterIf($attr->registerIf, $instance, $method->getName())) {
                    Logger::warning('WPHooksInstanceRegistry', 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $method->getName() . ' with hook ' . $hook . ' and ' . 'priority ' . (string) $attr->priority . ' skipped');
                    return;
                }

                $handler = new RuntimeInstanceHookInvoker(
                    instance: $instance,
                    hookName: $hook,
                    priority: $attr->priority,
                    method: $method->getName(),
                    visibility: $visibility,
                    type: $type,
                    executeIf: $attr->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                    hookArgNames: $this->provider !== null ? $this->runtimeResolver->resolveHookArgNames($instance, $method->getName()) : [],
                    once: $attr->once,
                );

                $record = new RuntimeRegistryHandlerEntry(
                    handler: $handler,
                    hook: $hook,
                    priority: $attr->priority,
                    type: $type,
                    acceptedArgs: $attr->acceptedArgs,
                    owner: \WeakReference::create($instance),
                    callback: null,
                );
                $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($record));

                if ($attr->deferRegisterUntilHook !== null) {
                    $hasDeferred = true;
                    $this->deferUntilTriggerHook($hook, $hookKey, $attr, $handler, $instance);
                    return;
                }
                $records[] = $record->register();
            }
        );

        $this->scanPropertyHooks(
            $ref,
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records, &$metadata, &$hasDeferred, &$hookKey): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $property->getName())
                    : $this->runtimeResolver->resolveClosureHook($attr->hook, $instance, $property->getName());
                if ($hook === null) {
                    return;
                }

                $hookKey = new HookKey(
                    class: spl_object_hash($instance),
                    method: $target,
                    target: $property->getName(),
                    type: $attr instanceof Action ? 'action' : 'filter',
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                );

                if ($instance instanceof ModuleClassHookMetadata) {
                    $metadata[] = new InstanceHookMetadata(
                        hook: $hook,
                        type: $type,
                        priority: $attr->priority,
                        acceptedArgs: $attr->acceptedArgs,
                        once: $attr->once,
                        executeIf: $attr->executeIf,
                        executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                        registerIf: $attr->registerIf,
                        registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf) : [],
                        deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                        deferRegisterUntilHookParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null) : [],
                        hookArgNames: $this->provider !== null ? $this->provider->extractPropertyCallableParamNames($property, $instance) : [],
                        target: $target,
                        targetName: $property->getName(),
                        visibility: $visibility,
                    );
                }

                if ($this->provider !== null) {
                    $gatePassed = $this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $property->getName(), $instance);
                    if (!$gatePassed) {
                        Logger::warning('WPHooksInstanceRegistry', 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $property->getName() . ' with hook ' . $hook . ' and ' . 'priority' . (string) $attr->priority . ' skipped');
                        return;
                    }
                } elseif (!$this->evaluateRegisterIf($attr->registerIf, $instance, $property->getName())) {
                    Logger::warning('WPHooksInstanceRegistry', 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $property->getName() . ' with hook ' . $hook . ' and ' . 'priority' . (string) $attr->priority . ' skipped');
                    return;
                }


                $handler = new RuntimeInstancePropertyHookInvoker(
                    instance: $instance,
                    hookName: $hook,
                    priority: $attr->priority,
                    property: $property->getName(),
                    visibility: $visibility,
                    type: $type,
                    executeIf: $attr->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                    hookArgNames: $this->provider !== null ? $this->provider->extractPropertyCallableParamNames($property, $instance) : [],
                    once: $attr->once,
                );
                $record = new RuntimeRegistryHandlerEntry(
                    handler: $handler,
                    hook: $hook,
                    priority: $attr->priority,
                    type: $type,
                    acceptedArgs: $attr->acceptedArgs,
                    owner: \WeakReference::create($instance),
                    callback: null,
                );
                $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($record));

                if ($attr->deferRegisterUntilHook !== null) {
                    $hasDeferred = true;
                    $this->deferUntilTriggerHook($hook, $hookKey, $attr, $handler, $instance);
                    return;
                }
                SharedUtils::isDevelopment() && Logger::debug('WPHooksInstanceRegistry', 'Registered ' . $handler->label);

                $records[] = $record->register();
            }
        );

        $this->scanned[$instance] = true;
        $this->weakRegistry[$instance] = array_merge($this->weakRegistry[$instance] ?? [], $records);

        if ($instance instanceof ModuleClassHookMetadata && !$hasDeferred && $metadata !== []) {
            $this->cache?->set($instance->getParentClass(), $instance->parentProperty, $metadata);
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
        $this->unregisterMatchingDeferredEntries(
            $this->entriesIndexer->byClass[\spl_object_hash($instance)] ?? [],
            static fn(DeferredHookEntryDTO $data): bool => $data->instance?->get() === $instance,
        );

        if (!isset($this->weakRegistry[$instance])) {
            return;
        }

        foreach ($this->weakRegistry[$instance] as $record) {
            $record->unregister();
        }

        unset($this->weakRegistry[$instance]);
        unset($this->scanned[$instance]);
        unset($this->entriesIndexer->byClass[\spl_object_hash($instance)]);
    }

    private function scheduleAutoUnregister(object $instance, string $hook): void
    {
        if (\did_action($hook) >= 1) {
            Logger::warning('WPHooksInstanceRegistry', 'Hook ' . $hook . ' has passed lifecycle execution, cannot schedule unregister there');
            return;
        }
        $owner = \WeakReference::create($instance);

        $listener = function () use ($owner, $hook): void {
            if (($instance = $owner->get()) !== null) {
                $this->unregisterHooksOn($instance);
            }
            \remove_action($hook, \Closure::getCurrent(), PHP_INT_MIN);
        };

        \add_action($hook, $listener, PHP_INT_MIN, 0);
    }

    /**
     * Re-hydrate cached metadata entries into live handlers bound to
     * $instance, applying the registerIf gate per entry. Only eager
     * (non-deferred) entries are cached, so no deferral handling here.
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

        foreach ($entries as $entry) {
            if ($this->provider !== null) {
                $gatePassed = $this->provider->evaluateRuntimeRegisterIf($entry->registerIf, $this->provider->buildCallablePlan($entry->registerIf), $entry->targetName, $instance);
                if (!$gatePassed) {
                    Logger::warning('WPHooksInstanceRegistry', 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $entry->targetName . ' with hook ' . $entry->hook . ' and ' . 'priority ' . (string) $entry->priority . ' skipped');
                    continue;
                }
            } elseif (!$this->evaluateRegisterIf($entry->registerIf, $instance, $entry->targetName)) {
                Logger::warning('WPHooksInstanceRegistry', 'registerIf for ' . ($instance instanceof ModuleClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $entry->targetName . ' with hook ' . $entry->hook . ' and ' . 'priority ' . (string) $entry->priority . ' skipped');
                continue;
            }

            $handler = match ($entry->target) {
                'method' => new RuntimeInstanceHookInvoker(
                    instance: $instance,
                    hookName: $entry->hook,
                    priority: $entry->priority,
                    method: $entry->targetName,
                    visibility: $entry->visibility,
                    type: $entry->type,
                    executeIf: $entry->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $entry->executeIfParams,
                    hookArgNames: $entry->hookArgNames,
                    once: $entry->once,
                ),
                'property', 'property-hook' => new RuntimeInstancePropertyHookInvoker(
                    instance: $instance,
                    hookName: $entry->hook,
                    priority: $entry->priority,
                    property: $entry->targetName,
                    visibility: $entry->visibility,
                    type: $entry->type,
                    executeIf: $entry->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $entry->executeIfParams,
                    hookArgNames: $entry->hookArgNames,
                    once: $entry->once,
                ),
            };
            $record = new RuntimeRegistryHandlerEntry(
                handler: $handler,
                hook: $entry->hook,
                priority: $entry->priority,
                type: $entry->type,
                acceptedArgs: $entry->acceptedArgs,
                owner: \WeakReference::create($instance),
            );
            $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($record));

            $records[] = $record->register();
        }

        return $records;
    }

    /**
     * Register a deferRegisterUntilHook entry on the
     * runtime path: the handler is held in the deferred pool until the
     * trigger hook fires, then activated automatically — no manual
     * activation API.
     *
     * @param Action|Filter $attr The hook attribute.
     * @param RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker $handler Runtime handler for the entry.
     * @param object $instance Owner instance (tracks the entry for lifetime cleanup).
     */
    private function deferUntilTriggerHook(
        string $hook,
        HookKey $hookKey,
        Action|Filter $attr,
        RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker $handler,
        object $instance,
    ): void {
        $triggerHook = $attr->deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            $triggerHook = $this->provider !== null
                ? $this->provider->resolveRuntimeHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook), $hookKey->target, $instance)
                : $this->runtimeResolver->resolveClosureHook($triggerHook, $instance, $hookKey->target);
            if ($triggerHook === null) {
                return;
            }
        }

        $this->addDeferred(new DeferredHookEntryDTO(
            hook: $hook,
            key: $hookKey,
            handler: $handler,
            type: $hookKey->type,
            priority: $hookKey->priority,
            acceptedArgs: $hookKey->acceptedArgs,
            tags: [],
            registerIf: $attr->registerIf,
            registerIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf) : [],
            executeIf: $attr->executeIf,
            executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
            once: $attr->once,
            instance: \WeakReference::create($instance),
        ));

        if (did_action($triggerHook)) {
            $this->activateMatchingDeferredEntries(
                $this->entriesIndexer->byHook[$hook] ?? [],
                $this->activateRuntimeEntry,
            );
            return;
        }

        $listener = function () use ($triggerHook, $hook): void {
            $activated = $this->activateMatchingDeferredEntries(
                $this->entriesIndexer->byHook[$hook] ?? [],
                $this->activateRuntimeEntry,
            );

            if ($activated > 0) {
                \remove_action($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
            }
        };

        \add_action($triggerHook, $listener, PHP_INT_MIN, 0);

        Logger::debug('WPHooksInstanceRegistry', 'Deferred ' . $handler->label . ' until trigger ' . $triggerHook);
    }

    /**
     * Activate a deferred runtime entry: register the handler with WordPress
     * and record it under the owner instance.
     * @var \Closure(string, DeferredHookEntryDTO, string): bool
     * @return bool True when the entry was activated.
     */
    private \Closure $activateRuntimeEntry {
        get => $this->activateRuntimeEntry ??= function (DeferredHookEntryDTO $data): bool {
            $owner = $data->instance?->get();
            if ($owner === null) {
                return false;
            }

            $record = new RuntimeRegistryHandlerEntry(
                handler: $data->handler,
                hook: $data->hook,
                priority: $data->priority,
                type: $data->type,
                acceptedArgs: $data->acceptedArgs,
                owner: $data->instance,
                callback: null,
            );

            $records = $this->weakRegistry[$owner] ?? [];
            $records[] = $record->register();
            $this->weakRegistry[$owner] = $records;

            Logger::debug('WPHooksInstanceRegistry', 'Activated deferred hook ' . $data->handler->label);

            return true;
        };
    }

    /**
     * Detach a runtime handler from WordPress (once-consume or
     * instance-lifetime cleanup). The owner is passed as a WeakReference so
     * the callback never keeps the instance alive — only the record drop
     * touches it, and only when it still exists.
     *
     * @param RuntimeRegistryHandlerEntry $entry Registry entry holding the handler and metadata.
     */
    private function removeRuntimeHook(RuntimeRegistryHandlerEntry $entry): void
    {
        #region next new `once` with new hook name iteration
        /**
         *  Temporary solution till bug fixed upstream
         * ? In Invokers its `finallly` perhaps?
         */
        if ($this->queuedRemovalEntry !== []) {
            foreach ($this->queuedRemovalEntry as $hookName => $entries) {
                if (\doing_filter($hookName)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    $entry->unregister();
                }

                unset($this->queuedRemovalEntry[$hookName]);
            }
        }
        #endregion

        /**
         * Skip unregister hook while still in the same hook stack
         * 
         * Skip the WordPress-side removal while the hook is being dispatched:
         * remove_action during dispatch corrupts WP_Hook's iteration
         * (resort_active_iterations skips the immediately-following priority).
         * The consumed flag already prevents re-firing, so the callback can
         * safely linger in $wp_filter until the request ends.
         * @link https://core.trac.wordpress.org/ticket/61263
         */
        // !$entry->stillDispatchingSameHook() && $entry->unregister();
        if ($entry->stillDispatchingSameHook()) {
            $this->queuedRemovalEntry[$entry->hook][] = $entry;
        }

        $owner = $entry->owner->get();
        if ($owner !== null && isset($this->weakRegistry[$owner])) {
            $this->weakRegistry[$owner] = array_values(array_filter(
                $this->weakRegistry[$owner],
                static fn(RuntimeRegistryHandlerEntry $record): bool => $record !== $entry,
            ));
        }
    }

    /**
     * Re-evaluate the registerIf registration gate when activating a
     * deferred runtime entry. Without a provider there is no way to evaluate
     * the gate, so the entry is allowed (mirrors the container path's defer
     * semantics).
     */
    private function gateDeferredActivation(DeferredHookEntryDTO $data): bool
    {
        $registerIf = $data->registerIf ?? null;
        if ($registerIf === null || $this->provider === null) {
            return true;
        }

        try {
            $allowed = $this->provider->evaluateRuntimeRegisterIf(
                $registerIf,
                $data->registerIfParams ?? [],
                $data->hook,
            );
        } catch (\Throwable $e) {
            Logger::warning('WPHooksInstanceRegistry', 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate threw: ' . $e->getMessage());
            return false;
        }

        if (!$allowed) {
            Logger::warning('WPHooksInstanceRegistry', 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate returned false.');
            return false;
        }

        return true;
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
     * @internal Manual registration, subject to change
     * @throws \RuntimeException when the owner cannot be inferred or the callback is not callable.
     */
    public function registerAction(
        string $hook,
        callable|array $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
        bool $once = false,
        string|\Closure|null $deferRegisterUntilHook = null,
        ?object $owner = null,
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register action hook ' . $hook . ' with ' . 'priority' . (string) $priority . ' and ' . 'acceptedArgs' . (string) $acceptedArgs . ' — callback is not callable.';
            Logger::error('WPHooksInstanceRegistry', $error);
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
     * @internal Manual registration, subject to change
     * @throws \RuntimeException when the owner cannot be inferred or the callback is not callable.
     */
    public function registerFilter(
        string $hook,
        callable|array $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
        bool $once = false,
        string|\Closure|null $deferRegisterUntilHook = null,
        ?object $owner = null,
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register filter hook ' . $hook . ' with ' . 'priority' . (string) $priority . ' and ' . 'acceptedArgs' . (string) $acceptedArgs . ' — callback is not callable.';
            Logger::error('WPHooksInstanceRegistry', $error);
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
        object $owner,
        bool $once = false,
        string|\Closure|null $deferRegisterUntilHook = null,
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register ' . $type . ' hook ' . $hook . ' with ' . 'priority' . (string) $priority . ' and ' . 'acceptedArgs' . (string) $acceptedArgs . ' — callback is not callable.';
            Logger::error('WPHooksInstanceRegistry', $error);
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
            class: \spl_object_hash($owner),
            method: '#manual-registration',
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
            once: $once,
        );
        $record = new RuntimeRegistryHandlerEntry(
            handler: $handler,
            hook: $hook,
            priority: $priority,
            type: $type,
            acceptedArgs: $acceptedArgs,
            owner: \WeakReference::create($owner),
            callback: $callback,
        );

        if ($once) {
            $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($record));
        }

        if ($deferRegisterUntilHook !== null) {
            $this->deferManualUntilHook($hook, $hookKey, $deferRegisterUntilHook, $handler, $owner, $executeIf, $once);
            return;
        }

        $existing[] = $record->register();
        $this->weakRegistry[$owner] = $existing;
    }

    /**
     * Defer a manual hook registration until a trigger hook fires.
     *
     * @param string $hook The hook name this entry listens on.
     * @param string|\Closure|null $deferRegisterUntilHook Trigger hook name or closure resolving to one.
     * @param RuntimeCallableHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeInstanceHookInvoker $handler Runtime handler for the entry.
     * @param object $instance Owner instance (tracks the entry for lifetime cleanup).
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param bool $once remove self after any executeIf eval fire.
     */
    private function deferManualUntilHook(
        string $hook,
        HookKey $hookKey,
        string|\Closure|null $deferRegisterUntilHook,
        RuntimeCallableHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeInstanceHookInvoker $handler,
        object $instance,
        ?\Closure $executeIf,
        bool $once,
    ): void {
        $triggerHook = $deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            $triggerHook = $this->provider !== null
                ? $this->provider->resolveRuntimeHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook), 'manual')
                : $this->runtimeResolver->resolveClosureHook($triggerHook, $instance, 'manual');
            if ($triggerHook === null) {
                return;
            }
        }
        $dtoEntry = new DeferredHookEntryDTO(
                hook: $hook,
                key: $hookKey,
                handler: $handler,
                type: $hookKey->type,
                priority: $hookKey->priority,
                acceptedArgs: $hookKey->acceptedArgs,
                tags: [],
                registerIf: null,
                registerIfParams: [],
                executeIf: $executeIf,
                executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($executeIf) : [],
                once: $once,
                instance: \WeakReference::create($instance),
        );
        $this->addDeferred($dtoEntry);

        if (did_action($triggerHook)) {
            $this->activateMatchingDeferredEntries(
                $this->entriesIndexer->byHook[$hook] ?? [],
                $this->activateRuntimeEntry,
            );
            return;
        }

        $listener = function () use ($triggerHook, $hook): void {
            $activated = $this->activateMatchingDeferredEntries(
                $this->entriesIndexer->byHook[$hook] ?? [],
                $this->activateRuntimeEntry,
            );

            if ($activated > 0) {
                \remove_action($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
            }
        };

        \add_action($triggerHook, $listener, PHP_INT_MIN, 0);

        Logger::debug('WPHooksInstanceRegistry', 'Deferred ' . $dtoEntry->handler->label . ' until trigger ' . $triggerHook);
    }

    /**
     * Evaluate an attribute-parameter registerIf gate (static closure
     * invoked directly).
     *
     * @param \Closure|null $registerIf Gate closure (null = no gate).
     * @param object $instance Owner instance.
     * @param string $targetName Method/property name (for log messages).
     */
    private function evaluateRegisterIf(?\Closure $registerIf, object $instance, string $targetName): bool
    {
        if ($registerIf === null) {
            return true;
        }

        try {
            $allowed = $registerIf();
        } catch (\Throwable $e) {
            Logger::warning('WPHooksInstanceRegistry', 'Skipping hook on ' . $targetName . ' — registerIf closure failed: ' . $e->getMessage());
            return false;
        }

        if (!\is_bool($allowed)) {
            Logger::warning('WPHooksInstanceRegistry', 'Skipping hook on ' . $targetName . ' — registerIf must return bool, got ' . get_debug_type($allowed));
            return false;
        }

        if ($allowed === false) {
            Logger::warning('WPHooksInstanceRegistry', 'Skipping hook on ' . $targetName . ' — registerIf gate returned false.');
            return false;
        }

        return true;
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
     * @phpstan-ignore-next-line
     * @var ?__WPHooksInstanceObjectCacheState
     */
    private ?object $cacheState {
        get {
            return $this->cacheState ??= new class($this->file) {
                public private(set) bool $alreadyLoaded = false;

                /** @var TCache */
                public array $bufferRuntime = [];
                /** @var TCache */
                public array $loadedCache = [];

                public function __construct(private readonly ?string $file) {
                    return;
                    \class_alias(__CLASS__, __WPHooksInstanceObjectCacheState::class);
                }

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

        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
            rename($tmp, $this->file);
        }
    }
}

/**
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @internal
 */
class HookRuntimeResolver
{

    /**
     * Resolve the owner of a manual registration.
     *
     * @param callable    $callback Registration callback.
     * @param object|null $owner    Explicit owner — always wins when provided.
     *
     * @throws \RuntimeException when no owner can be inferred.
     */
    public function resolveOwner(callable $callback, ?object $owner): object
    {
        if ($owner !== null) {
            return $owner;
        }

        if (is_array($callback) && is_object($callback[0] ?? null)) {
            return $callback[0];
        }

        if ($callback instanceof \Closure) {
            $bound = (new ReflectionFunction($callback))->getClosureThis();
            if ($bound !== null) {
                return $bound;
            }

            // Static / unbound closure — nothing to infer the owner from.
            throw new \RuntimeException(
                'Cannot infer owner for hook registration — pass owner: explicitly.',
            );
        }

        if (is_object($callback)) {
            return $callback;
        }

        throw new \RuntimeException(
            'Cannot infer owner for hook registration — pass owner: explicitly.',
        );
    }

    /**
     * Resolve a hook name from a static string or an attribute-parameter closure.
     *
     * Closures declared in attribute parameters are static and already scoped
     * to the declaring class (PHP 8.1 RFC 'Closures in constant expressions'),
     * so private members resolve via self:: and the closure is invoked directly.
     *
     * @param string|\Closure $hook       Static hook name or closure resolving to one.
     * @param object          $instance   Owner instance.
     * @param string          $targetName Method/property name (for log messages).
     *
     * @return string|null The resolved hook name, or null when it could not be resolved.
     */
    public function resolveClosureHook(string|\Closure $hook, object $instance, string $targetName): ?string
    {
        if (\is_string($hook)) {
            return $hook;
        }

        try {
            $resolved = $hook();
        } catch (\Throwable $e) {
            Logger::warning(
                'WPHooksInstanceRegistry',
                'Skipping hook on ' . $targetName . ' — hook closure failed: ' . $e->getMessage()
            );
            return null;
        }

        if (!\is_string($resolved) || $resolved === '') {
            Logger::warning(
                'WPHooksInstanceRegistry',
                'Skipping hook on ' . $targetName . ' — hook closure did not resolve to a non-empty string'
            );
            return null;
        }

        return $resolved;
    }

    /**
     * Resolve the parameter names of a hook-annotated method, used to build
     * named hook args for executeIf parameter resolution.
     *
     * @return list<string>
     */
    public function resolveHookArgNames(object $instance, string $method): array
    {
        return array_map(
            static fn(\ReflectionParameter $param): string => $param->getName(),
            (new \ReflectionMethod($instance, $method))->getParameters(),
        );
    }
}
