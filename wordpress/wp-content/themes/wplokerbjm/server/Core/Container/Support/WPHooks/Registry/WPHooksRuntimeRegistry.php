<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use DeferredHookEntry;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use Brick\VarExporter\VarExporter;
use RuntimeHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{RuntimeInstancePropertyHookHandler, RuntimeInstanceHookHandler, RuntimeCallableHookHandler};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\RuntimeHookMetadata;

/**
 *  @example on Property Hook:
 * ```php
 *  class Service {
 *   public $service { get => $this->service ??= new class(self::class, __PROPERTY__, $this->registry) extends AnonClassHookMetadata {
 *       public function __construct(
 *           string $parentClass,
 *           string $propertyName,
 *           private WPHooksRuntimeRegistry $registry
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
 * @phpstan-type RuntimeHandlerEntry array{
 *     handler?: RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler,
 *     hook: string,
 *     priority: int,
 *     type: 'action'|'filter',
 *     callback?: callable
 * }
 */
class WPHooksRuntimeRegistry
{
    use HookScannerTrait;
    use DeferredHooksTrait;

    /**
     * Every registration — attribute-discovered or manual — is owned by
     * exactly one object; unregisterHooksOn($owner) removes all of them at once.
     *
     * @var \WeakMap<object, list<RuntimeHandlerEntry>>
     */
    private \WeakMap $registry;

    /**
     * Ensures registerHooksOn() runs exactly once per object regardless of
     * the order of manual registerAction()/registerFilter() calls.
     *
     * @var \WeakMap<object, bool>
     */
    private \WeakMap $scanned;

    public function __construct(
        private HookRuntimeResolver $runtimeResolver = new HookRuntimeResolver(),
        public ?WPHooksRuntimeCache $cache = null,
        private readonly ?RuntimeWPHookProvider $provider = null,
    ) {
        $this->registry = new \WeakMap();
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
     * @param object|AnonClassHookMetadata $instance An instantiated object with hook-annotated methods/properties.
     */
    public function registerHooksOn(object $instance): void
    {
        if (isset($this->scanned[$instance])) {
            return;
        }
        /** @var RuntimeHandlerEntry[] $records */
        $records = [];
        $hasDeferred = false;
        if ($instance instanceof AnonClassHookMetadata) {
            $cached = $this->cache?->get($instance->getParentClass(), $instance->parentProperty);
            if ($cached !== null) {
                $this->scanned[$instance] = true;
                $this->registry[$instance] = $this->registerCachedEntries($cached, $instance);
                return;
            }
        }

        $ref = new ReflectionClass($instance);
        $metadata = [];

        $this->scanMethodHooks(
            $ref,
            function (\ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($instance, &$records, &$metadata, &$hasDeferred): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $method->getName())
                    : $this->runtimeResolver->resolveClosureHook($attr->hook, $instance, $method->getName());
                if ($hook === null) {
                    return;
                }

                if ($instance instanceof AnonClassHookMetadata) {
                    /**
                     * @var list<RuntimeHookMetadata> $metadata
                     */
                    $metadata[] = new RuntimeHookMetadata(
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
                    $gatePassed = $this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $method->getName());
                    if (!$gatePassed) {
                        Logger::warning('WPHooksRuntimeRegistry', 'registerIf for ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $method->getName() . ' with hook ' . $hook . ' skipped');
                        return;
                    }
                } elseif (!$this->evaluateRegisterIf($attr->registerIf, $instance, $method->getName())) {
                    Logger::warning('WPHooksRuntimeRegistry', 'registerIf for ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $method->getName() . ' with hook ' . $hook . ' skipped');
                    return;
                }


                $handler = new RuntimeInstanceHookHandler(
                    instance: $instance,
                    method: $method->getName(),
                    visibility: $visibility,
                    type: $type,
                    executeIf: $attr->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                    hookArgNames: $this->provider !== null ? $this->runtimeResolver->resolveHookArgNames($instance, $method->getName()) : [],
                    once: $attr->once,
                );

                $ownerRef = \WeakReference::create($instance);
                $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($hook, $handler, $attr->priority, $type, $ownerRef));

                if ($attr->deferRegisterUntilHook !== null) {
                    $hasDeferred = true;
                    $this->deferUntilTriggerHook($hook, $attr, $handler, $instance, $type, $method->getName());
                    return;
                }

                if ($type === 'action') {
                    \add_action($hook, $handler, $attr->priority, $attr->acceptedArgs);
                } else {
                    \add_filter($hook, $handler, $attr->priority, $attr->acceptedArgs);
                }
                $records[] = [
                    'handler' => $handler,
                    'hook' => $hook,
                    'priority' => $attr->priority,
                    'type' => $type,
                ];
            }
        );

        $this->scanPropertyHooks(
            $ref,
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records, &$metadata, &$hasDeferred): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $property->getName())
                    : $this->runtimeResolver->resolveClosureHook($attr->hook, $instance, $property->getName());
                if ($hook === null) {
                    return;
                }

                if ($instance instanceof AnonClassHookMetadata) {
                    /**
                     * @var list<RuntimeHookMetadata> $metadata
                     */
                    $metadata[] = new RuntimeHookMetadata(
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
                    $gatePassed = $this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $property->getName());
                    if (!$gatePassed) {
                        Logger::warning('WPHooksRuntimeRegistry', 'registerIf for ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $property->getName() . ' with hook ' . $hook . ' skipped');
                        return;
                    }
                } elseif (!$this->evaluateRegisterIf($attr->registerIf, $instance, $property->getName())) {
                    Logger::warning('WPHooksRuntimeRegistry', 'registerIf for ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $property->getName() . ' with hook ' . $hook . ' skipped');
                    return;
                }


                $handler = new RuntimeInstancePropertyHookHandler(
                    instance: $instance,
                    property: $property->getName(),
                    visibility: $visibility,
                    type: $type,
                    executeIf: $attr->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                    hookArgNames: $this->provider !== null ? $this->provider->extractPropertyCallableParamNames($property, $instance) : [],
                    once: $attr->once,
                );

                $ownerRef = \WeakReference::create($instance);
                $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($hook, $handler, $attr->priority, $type, $ownerRef));

                if ($attr->deferRegisterUntilHook !== null) {
                    $hasDeferred = true;
                    $this->deferUntilTriggerHook($hook, $attr, $handler, $instance, $type, $property->getName());
                    return;
                }

                if ($type === 'action') {
                    \add_action($hook, $handler, $attr->priority, $attr->acceptedArgs);
                } else {
                    \add_filter($hook, $handler, $attr->priority, $attr->acceptedArgs);
                }

                SharedUtils::isDevelopment() && Logger::debug('WPHooksRuntimeRegistry', 'Registered ' . $type . ' hook ' . $hook . ' on ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class));

                $records[] = [
                    'handler' => $handler,
                    'hook' => $hook,
                    'priority' => $attr->priority,
                    'type' => $type,
                ];
            }
        );

        $this->scanned[$instance] = true;
        $this->registry[$instance] = array_merge($this->registry[$instance] ?? [], $records);

        if ($instance instanceof AnonClassHookMetadata && !$hasDeferred && $metadata !== []) {
            $this->cache?->set($instance->getParentClass(), $instance->parentProperty, $metadata);
        }
    }
    /**
     * Remove all hooks previously registered for the given instance.
     *
     * Calls remove_action() / remove_filter() for each registered hook and
     * clears internal tracking. Calling this on an instance that was never
     * registered is a no-op.
     * @api
     * @param object|AnonClassHookMetadata $instance The instance whose hooks should be removed.
     */
    public function unregisterHooksOn(object $instance): void
    {
        $this->unregisterMatchingDeferredEntries(
            fn(string $hook, array $data): bool => $this->deferredEntryOwner($data) === $instance,
        );

        if (!isset($this->registry[$instance])) {
            return;
        }

        foreach ($this->registry[$instance] as $record) {
            if ($record['type'] === 'action') {
                \remove_action($record['hook'], $record['handler'], $record['priority']);
            } else {
                \remove_filter($record['hook'], $record['handler'], $record['priority']);
            }
        }

        unset($this->registry[$instance]);
        unset($this->scanned[$instance]);
    }

    /**
     * Re-hydrate cached metadata entries into live handlers bound to
     * $instance, applying the registerIf gate per entry. Only eager
     * (non-deferred) entries are cached, so no deferral handling here.
     *
     * @param list<RuntimeHookMetadata> $entries Cached metadata entries.
     * @param object $instance Owner instance to bind handlers to.
     *
     * @return array<int, array{
     *     handler: RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler,
     *     hook: string,
     *     priority: int,
     *     type: 'action'|'filter'
     * }>
     */
    private function registerCachedEntries(array $entries, object $instance): array
    {
        /** @var list<RuntimeHandlerEntry> $records */
        $records = [];

        foreach ($entries as $entry) {
            if ($this->provider !== null) {
                $gatePassed = $this->provider->evaluateRuntimeRegisterIf($entry->registerIf, $this->provider->buildCallablePlan($entry->registerIf), $entry->targetName);
                if (!$gatePassed) {
                    Logger::warning('WPHooksRuntimeRegistry', 'registerIf for ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $entry->targetName . ' with hook ' . $entry->hook . ' skipped');
                    continue;
                }
            } elseif (!$this->evaluateRegisterIf($entry->registerIf, $instance, $entry->targetName)) {
                Logger::warning('WPHooksRuntimeRegistry', 'registerIf for ' . ($instance instanceof AnonClassHookMetadata ? $instance->getParentClass() . '->' . $instance->parentProperty : $instance::class) . '->' . $entry->targetName . ' with hook ' . $entry->hook . ' skipped');
                continue;
            }

            $handler = $entry->target === 'method'
                ? new RuntimeInstanceHookHandler(
                    instance: $instance,
                    method: $entry->targetName,
                    visibility: $entry->visibility,
                    type: $entry->type,
                    executeIf: $entry->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $entry->executeIfParams,
                    hookArgNames: $entry->hookArgNames,
                    once: $entry->once,
                )
                : new RuntimeInstancePropertyHookHandler(
                    instance: $instance,
                    property: $entry->targetName,
                    visibility: $entry->visibility,
                    type: $entry->type,
                    executeIf: $entry->executeIf,
                    hookPlanProvider: $this->provider,
                    executeIfParams: $entry->executeIfParams,
                    hookArgNames: $entry->hookArgNames,
                    once: $entry->once,
                );

            $ownerRef = \WeakReference::create($instance);
            $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($entry->hook, $handler, $entry->priority, $entry->type, $ownerRef));

            if ($entry->type === 'action') {
                \add_action($entry->hook, $handler, $entry->priority, $entry->acceptedArgs);
            } else {
                \add_filter($entry->hook, $handler, $entry->priority, $entry->acceptedArgs);
            }

            $records[] = [
                'handler' => $handler,
                'hook' => $entry->hook,
                'priority' => $entry->priority,
                'type' => $entry->type,
            ];
        }

        return $records;
    }

    /**
     * Register a deferRegisterUntilHook (registerUnderHook) entry on the
     * runtime path: the handler is held in the deferred pool until the
     * trigger hook fires, then activated automatically — no manual
     * activation API.
     *
     * @param string $hook The hook name this entry listens on.
     * @param Action|Filter $attr The hook attribute.
     * @param RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler $handler Runtime handler for the entry.
     * @param object $instance Owner instance (tracks the entry for lifetime cleanup).
     * @param 'action'|'filter' $type Hook type.
     * @param string $targetName Method/property name (for logs and keying).
     */
    private function deferUntilTriggerHook(
        string $hook,
        Action|Filter $attr,
        RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler $handler,
        object $instance,
        string $type,
        string $targetName,
    ): void {
        $triggerHook = $attr->deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            $triggerHook = $this->provider !== null
                ? $this->provider->resolveRuntimeHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook), $targetName)
                : $this->runtimeResolver->resolveClosureHook($triggerHook, $instance, $targetName);
            if ($triggerHook === null) {
                return;
            }
        }

        $key = $instance::class . '::' . $targetName . '#' . spl_object_hash($instance);

        $this->addDeferred($hook, $key, [
            'handler' => $handler,
            'type' => $type,
            'priority' => $attr->priority,
            'accepted_args' => $attr->acceptedArgs,
            'tags' => [],
            'registerIf' => $attr->registerIf,
            'registerIfParams' => $this->provider !== null ? $this->provider->buildCallablePlan($attr->registerIf) : [],
            'executeIf' => $attr->executeIf,
            'executeIfParams' => $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
            'once' => $attr->once,
            'instance' => \WeakReference::create($instance),
        ]);

        $activate = fn(string $h, array $d, string $k) => $this->activateRuntimeEntry($h, $d, $k);

        if (did_action($triggerHook)) {
            $this->activateMatchingDeferredEntries(
                static fn(string $h, array $d, string $k): bool => $h === $hook && $k === $key,
                $activate,
            );
            return;
        }

        $listener = null;
        $listener = function () use (&$listener, $triggerHook, $hook, $key, $activate): void {
            $activated = $this->activateMatchingDeferredEntries(
                static fn(string $h, array $d, string $k): bool => $h === $hook && $k === $key,
                $activate,
            );

            if ($activated > 0) {
                \remove_action($triggerHook, $listener, PHP_INT_MIN);
            }
        };

        \add_action($triggerHook, $listener, PHP_INT_MIN, 0);

        Logger::debug('WPHooksRuntimeRegistry', 'Deferred ' . $hook . ' until trigger ' . $triggerHook);
    }

    /**
     * Detach a runtime handler from WordPress (once-consume or
     * instance-lifetime cleanup). The owner is passed as a WeakReference so
     * the callback never keeps the instance alive — only the record drop
     * touches it, and only when it still exists.
     *
     * @param string $hook The hook the handler is registered on.
     * @param RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler $handler The handler being removed.
     * @param int $priority The registered priority.
     * @param 'action'|'filter' $type 'action' or 'filter'.
     * @param \WeakReference<object>|null $ownerRef Weak owner reference (optional).
     */
    private function removeRuntimeHook(string $hook, RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler $handler, int $priority, string $type, ?\WeakReference $ownerRef = null): void
    {
        // Skip the WordPress-side removal while the hook is being dispatched:
        // remove_action during dispatch corrupts WP_Hook's iteration
        // (resort_active_iterations skips the immediately-following priority).
        // The consumed flag already prevents re-firing, so the callback can
        // safely linger in $wp_filter until the request ends.
        if ($type === 'action' && !\doing_action($hook)) {
            \remove_action($hook, $handler, $priority);
        } elseif ($type === 'filter' && !\doing_filter($hook)) {
            \remove_filter($hook, $handler, $priority);
        }

        $owner = $ownerRef?->get();
        if ($owner !== null && isset($this->registry[$owner])) {
            $this->registry[$owner] = array_values(array_filter(
                $this->registry[$owner],
                static fn(array $record): bool => $record['handler'] !== $handler,
            ));
        }
    }

    /**
     * Resolve the owning instance of a deferred entry. Entries store a
     * WeakReference so the owner stays collectible (instance-lifetime
     * scoping); legacy object entries are accepted as-is.
     *
     * @return object|null The live owner, or null when it is gone.
     */
    private function deferredEntryOwner(array $data): ?object
    {
        $owner = $data['instance'] ?? null;
        if ($owner instanceof \WeakReference) {
            return $owner->get();
        }

        return is_object($owner) ? $owner : null;
    }

    /**
     * Activate a deferred runtime entry: register the handler with WordPress
     * and record it under the owner instance.
     * @param DeferredHookEntry $data
     * @return bool True when the entry was activated.
     */
    private function activateRuntimeEntry(string $hook, array $data, string $key): bool
    {
        $owner = $this->deferredEntryOwner($data);
        if ($owner === null) {
            return false;
        }

        $handler = $data['handler'];

        if ($data['type'] === 'action') {
            \add_action($hook, $handler, $data['priority'], $data['accepted_args']);
        } else {
            \add_filter($hook, $handler, $data['priority'], $data['accepted_args']);
        }

        $records = $this->registry[$owner] ?? [];
        $records[] = [
            'handler' => $handler,
            'hook' => $hook,
            'priority' => $data['priority'],
            'type' => $data['type'],
        ];
        $this->registry[$owner] = $records;

        Logger::debug('WPHooksRuntimeRegistry', 'Activated deferred hook ' . $hook);

        return true;
    }


    /**
     * Re-evaluate the registerIf registration gate when activating a
     * deferred runtime entry. Without a provider there is no way to evaluate
     * the gate, so the entry is allowed (mirrors the container path's defer
     * semantics).
     *
     * @param DeferredHookEntry $data
     * @param string $hook
     * @param string $key
     */
    private function gateDeferredActivation(array $data, string $hook, string $key): bool
    {
        $registerIf = $data['registerIf'] ?? null;
        if ($registerIf === null || $this->provider === null) {
            return true;
        }

        try {
            $allowed = $this->provider->evaluateRuntimeRegisterIf(
                $registerIf,
                $data['registerIfParams'] ?? [],
                $hook,
            );
        } catch (\Throwable $e) {
            Logger::warning('WPHooksRuntimeRegistry', 'Skipping deferred hook activation ' . $hook . ' — registerIf gate threw: ' . $e->getMessage());
            return false;
        }

        if (!$allowed) {
            Logger::warning('WPHooksRuntimeRegistry', 'Skipping deferred hook activation ' . $hook . ' — registerIf gate returned false.');
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
     * @template T of callable|array
     * @template O of object
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
            $error = 'Cannot register action hook ' . $hook . ' — callback is not callable.';
            Logger::error('WPHooksRuntimeRegistry', $error);
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
     * @template T of callable|array
     * @template O of object
     * @param string $hook Hook name
     * @param T $callback Callable invoked when the hook fires.
     * @param int $priority Hook priority
     * @param int $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param bool $once remove self after any executeIf eval fire.
     * @param string|\Closure|null $deferRegisterUntilHook Defer hook registration until certain hook fire
     * @param O|null $owner Owning object (defaults to inference).
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
            $error = 'Cannot register filter hook ' . $hook . ' — callback is not callable.';
            Logger::error('WPHooksRuntimeRegistry', $error);
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
     * RuntimeCallableHookHandler, registers it with WordPress immediately
     * and records it under the owner for lifetime-scoped unregistration.
     *
     * @template T of callable
     * @template O of object
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
            $error = 'Cannot register ' . $type . ' hook ' . $hook . ' — callback is not callable.';
            Logger::error('WPHooksRuntimeRegistry', $error);
            throw new \RuntimeException($error);
        }

        $existing = $this->registry[$owner] ?? [];
        foreach ($existing as $record) {
            if (
                $record['hook'] === $hook
                && ($record['callback'] ?? null) === $callback
                && $record['priority'] === $priority
            ) {
                return;
            }
        }

        $handler = new RuntimeCallableHookHandler($callback, $executeIf, $type, $once);

        if ($once) {
            $ownerRef = \WeakReference::create($owner);
            $handler->setRemoveCallback(fn() => $this->removeRuntimeHook($hook, $handler, $priority, $type, $ownerRef));
        }

        if ($deferRegisterUntilHook !== null) {
            $this->deferManualUntilHook($hook, $deferRegisterUntilHook, $handler, $owner, $type, $priority, $acceptedArgs, $executeIf, $once);
            return;
        }

        if ($type === 'action') {
            \add_action($hook, $handler, $priority, $acceptedArgs);
        } else {
            \add_filter($hook, $handler, $priority, $acceptedArgs);
        }

        $existing[] = [
            'handler' => $handler,
            'callback' => $callback,
            'hook' => $hook,
            'priority' => $priority,
            'type' => $type,
        ];
        $this->registry[$owner] = $existing;
    }

    /**
     * Defer a manual hook registration until a trigger hook fires.
     *
     * @param string $hook The hook name this entry listens on.
     * @param string|\Closure|null $deferRegisterUntilHook Trigger hook name or closure resolving to one.
     * @param RuntimeCallableHookHandler|RuntimeInstancePropertyHookHandler|RuntimeInstanceHookHandler $handler Runtime handler for the entry.
     * @param object $instance Owner instance (tracks the entry for lifetime cleanup).
     * @param string $type 'action' or 'filter'.
     * @param int $priority Hook priority.
     * @param int $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param bool $once remove self after any executeIf eval fire.
     */
    private function deferManualUntilHook(
        string $hook,
        string|\Closure|null $deferRegisterUntilHook,
        RuntimeCallableHookHandler|RuntimeInstancePropertyHookHandler|RuntimeInstanceHookHandler $handler,
        object $instance,
        string $type,
        int $priority,
        int $acceptedArgs,
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

        $key = $instance::class . '::manual#' . spl_object_hash($instance);

        $this->addDeferred($hook, $key, [
            'handler' => $handler,
            'type' => $type,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'tags' => [],
            'registerIf' => null,
            'registerIfParams' => [],
            'executeIf' => $executeIf,
            'executeIfParams' => $this->provider !== null ? $this->provider->buildCallablePlan($executeIf) : [],
            'once' => $once,
            'instance' => \WeakReference::create($instance),
        ]);

        $activate = fn(string $h, array $d, string $k) => $this->activateRuntimeEntry($h, $d, $k);

        if (did_action($triggerHook)) {
            $this->activateMatchingDeferredEntries(
                static fn(string $h, array $d, string $k): bool => $h === $hook && $k === $key,
                $activate,
            );
            return;
        }

        $listener = function () use (&$listener, $triggerHook, $hook, $key, $activate): void {
            $activated = $this->activateMatchingDeferredEntries(
                static fn(string $h, array $d, string $k): bool => $h === $hook && $k === $key,
                $activate,
            );

            if ($activated > 0) {
                \remove_action($triggerHook, $listener, PHP_INT_MIN);
            }
        };

        \add_action($triggerHook, $listener, PHP_INT_MIN, 0);

        Logger::debug('WPHooksRuntimeRegistry', 'Deferred ' . $hook . ' until trigger ' . $triggerHook);
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
            Logger::warning('WPHooksRuntimeRegistry', 'Skipping hook on ' . $targetName . ' — registerIf closure failed: ' . $e->getMessage());
            return false;
        }

        if (!\is_bool($allowed)) {
            Logger::warning('WPHooksRuntimeRegistry', 'Skipping hook on ' . $targetName . ' — registerIf must return bool, got ' . get_debug_type($allowed));
            return false;
        }

        if ($allowed === false) {
            Logger::warning('WPHooksRuntimeRegistry', 'Skipping hook on ' . $targetName . ' — registerIf gate returned false.');
            return false;
        }

        return true;
    }
}

/**
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @internal description
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
                'WPHooksRuntimeRegistry',
                'Skipping hook on ' . $targetName . ' — hook closure failed: ' . $e->getMessage()
            );
            return null;
        }

        if (!\is_string($resolved) || $resolved === '') {
            Logger::warning(
                'WPHooksRuntimeRegistry',
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

/**
 * File-backed metadata cache for runtime-registered hook sites.
 *
 * Anonymous hook classes extending AnonClassHookMetadata carry a
 * stable (parentClass, parentProperty) pair that uniquely identifies the
 * property-hook site. The reflected metadata (resolved hook names, plans,
 * hook-arg names) for that site is accumulated in an in-memory buffer during
 * the request and flushed atomically to WPHooksRuntimeCache.php, so repeated
 * registerHooksOn() calls skip all reflection.
 *
 * Per-instance state (owner instance, WeakReference, remove callbacks) is
 * intentionally NOT cached — only scan-derived metadata.
 * 
 * @phpstan-import-type RuntimeHookMetadataData from RuntimeHookMetadata
 * @internal
 */
class WPHooksRuntimeCache
{
    /** @var array<string, array<string, list<RuntimeHookMetadata>>> */
    private array $buffer = [];

    /** @var array<string, array<string, list<RuntimeHookMetadata>>> */
    private array $loaded = [];

    /**
     * @param string|null $file file path cache to configure
     */
    public function __construct(private ?string $file = null)
    {
        if ($file !== null) {
            $this->load();
        }
    }

    public function __destruct()
    {
        if (!empty($this->file) && \file_exists($this->file)) {
            return;
        }
        $this->flush();
    }

    /**
     * Clear all runtime hooks cache.
     */
    public function clearCacheFile(): void
    {
        if (!empty($this->file) && file_exists($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * @param string $parentClass
     * @param string $parentProperty
     *
     * @return list<RuntimeHookMetadata>|null
     */
    public function get(string $parentClass, string $parentProperty): ?array
    {
        return $this->loaded[$parentClass][$parentProperty]
            ?? $this->buffer[$parentClass][$parentProperty]
            ?? null;
    }

    /**
     * @param string $parentClass
     * @param string $parentProperty
     * @param list<RuntimeHookMetadata> $metadata
     */
    public function set(string $parentClass, string $parentProperty, array $metadata): void
    {
        $this->buffer[$parentClass][$parentProperty] = $metadata;
    }

    /**
     * @param string|null $file
     */
    private function load(?string $file = null): void
    {
        if ($file !== null) {
            $this->file = $file;
        }

        if ($this->file !== null && is_file($this->file)) {
            $loaded = require $this->file;
            if (is_array($loaded)) {
                $this->loaded = array_map(
                    // lvl1: parentClass
                    static fn(array $sites): array => array_map(
                        // lvl2: parentProperty
                        static fn(array $entries): array => array_map(
                            // lvl3: RuntimeHookMetadata
                            static fn(
                                /** @var RuntimeHookMetadataData $entry */
                                RuntimeHookMetadata|array $entry
                            ): RuntimeHookMetadata => $entry instanceof RuntimeHookMetadata ? $entry : RuntimeHookMetadata::fromArray($entry),
                            $entries,
                        ),
                        $sites,
                    ),
                    $loaded,
                );
            }
        }
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        if ($this->file === null || $this->buffer === []) {
            return;
        }

        $allCache = $this->buffer;
        if (is_file($this->file)) {
            $allCache = array_replace_recursive($this->loaded, $this->buffer);
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
