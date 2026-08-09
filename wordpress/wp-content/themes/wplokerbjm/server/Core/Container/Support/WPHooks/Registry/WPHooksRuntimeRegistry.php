<?php

declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use ReflectionClass;
use ReflectionFunction;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{RuntimeInstancePropertyHookHandler, RuntimeInstanceHookHandler, RuntimeCallableHookHandler};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};

/**
 * Runtime hook registry for on-the-fly object hook registration.
 *
 ** Unlike WPHooksContainerRegistry (which works with file-scanned, container-resolved
 ** classes), this registry registers hooks immediately from an already-
 ** instantiated object. This enables hook registration for anonymous classes
 ** and dynamically-created objects that cannot be discovered by the file-based
 ** WPHooksScanner.
 *
 * Usage:
 *   $service = new class($this->registry) {
 *       #[Action(hook: 'init')]
 *       public function __construct(WPHooksRuntimeRegistry $registry) { 
 *           $registry->registerHooksOn($this); // register
 *           $registry->unregisterHooksOn($this); // unregister (optional)
 *       }
 *       public function onInit(): void { ... }
 *   };
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
 */
class WPHooksRuntimeRegistry
{
    use HookScannerTrait;
    use DeferredHooksTrait;

    /**
     * Maps owner objects to their registered hook metadata and handlers.
     *
     * Every registration — attribute-discovered or manual — is owned by exactly
     * one object; unregisterHooksOn($owner) removes all of them at once.
     *
     * @var \WeakMap<object, list<array{
     *     handler: RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler,
     *     hook: string,
     *     priority: int,
     *     type: 'action'|'filter'
     * }>>
     */
    private \WeakMap $registry;

    /**
     * Tracks which owners already had their attributes scanned, so
     * registerHooksOn() runs exactly once per object regardless of the
     * order of manual registerAction()/registerFilter() calls.
     *
     * @var \WeakMap<object, bool>
     */
    private \WeakMap $scanned;

    public function __construct(
        private readonly ?RuntimeWPHookProvider $provider = null,
    ) {
        $this->registry = new \WeakMap();
        $this->scanned = new \WeakMap();
    }

    /**
     * Scan and register hooks on the given object instance.
     *
     * Non-static methods and properties annotated with #[Action] or
     * #[Filter] are registered via add_action() / add_filter()
     * immediately. Calling this on an already-registered instance is
     * a no-op.
     *
     * @param object $instance An instantiated object with hook-annotated methods/properties.
     */
    public function registerHooksOn(object $instance): void
    {
        // Attributes are scanned exactly once per owner, no matter how many
        // manual registerAction()/registerFilter() calls ran before or after.
        if (isset($this->scanned[$instance])) {
            return;
        }

        $records = [];
        $ref = new ReflectionClass($instance);

        $this->scanMethodHooks(
            $ref,
            function (\ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($instance, &$records): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $method->getName())
                    : $this->resolveClosureHook($attr->hook, $instance, $method->getName());
                if ($hook === null) {
                    return;
                }

                if ($this->provider !== null) {
                    if (!$this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $method->getName())) {
                        return;
                    }
                } elseif (!$this->evaluateRegisterIf($attr->registerIf, $instance, $method->getName())) {
                    return;
                }

                $handler = new RuntimeInstanceHookHandler(
                    $instance,
                    $method->getName(),
                    $visibility,
                    $type,
                    $attr->executeIf,
                    $this->provider,
                    $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                    $this->provider !== null ? $this->resolveHookArgNames($instance, $method->getName()) : [],
                    $attr->once,
                );

                $ownerRef = \WeakReference::create($instance);
                $handler->setRemoveCallback(fn () => $this->removeRuntimeHook($hook, $handler, $attr->priority, $type, $ownerRef));

                if ($attr->deferRegisterUntilHook !== null) {
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
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records): void {
                $hook = $this->provider !== null && $attr->hook instanceof \Closure
                    ? $this->provider->resolveRuntimeHookName($attr->hook, $this->provider->buildCallablePlan($attr->hook), $property->getName())
                    : $this->resolveClosureHook($attr->hook, $instance, $property->getName());
                if ($hook === null) {
                    return;
                }

                if ($this->provider !== null) {
                    if (!$this->provider->evaluateRuntimeRegisterIf($attr->registerIf, $this->provider->buildCallablePlan($attr->registerIf), $property->getName())) {
                        return;
                    }
                } elseif (!$this->evaluateRegisterIf($attr->registerIf, $instance, $property->getName())) {
                    return;
                }

                $handler = new RuntimeInstancePropertyHookHandler(
                    $instance,
                    $property->getName(),
                    $visibility,
                    $type,
                    $attr->executeIf,
                    $this->provider,
                    $this->provider !== null ? $this->provider->buildCallablePlan($attr->executeIf) : [],
                    [],
                    $attr->once,
                );

                $ownerRef = \WeakReference::create($instance);
                $handler->setRemoveCallback(fn () => $this->removeRuntimeHook($hook, $handler, $attr->priority, $type, $ownerRef));

                if ($attr->deferRegisterUntilHook !== null) {
                    $this->deferUntilTriggerHook($hook, $attr, $handler, $instance, $type, $property->getName());
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

        $this->scanned[$instance] = true;
        $this->registry[$instance] = array_merge($this->registry[$instance] ?? [], $records);
    }

    /**
     * Resolve the parameter names of a hook-annotated method, used to build
     * named hook args for executeIf parameter resolution.
     *
     * @return array<int, string>
     */
    private function resolveHookArgNames(object $instance, string $method): array
    {
        return array_map(
            static fn (\ReflectionParameter $param): string => $param->getName(),
            (new \ReflectionMethod($instance, $method))->getParameters(),
        );
    }

    /**
     * Register a deferRegisterUntilHook (registerUnderHook) entry on the
     * runtime path: the handler is held in the deferred pool until the trigger
     * hook fires, then activated automatically — no manual activation API.
     *
     * @param string            $hook       The hook name this entry listens on.
     * @param Action|Filter     $attr       The hook attribute.
     * @param object            $handler    Runtime handler for the entry.
     * @param object            $instance   Owner instance (tracks the entry for lifetime cleanup).
     * @param 'action'|'filter' $type       Hook type.
     * @param string            $targetName Method/property name (for logs and keying).
     */
    private function deferUntilTriggerHook(
        string $hook,
        Action|Filter $attr,
        object $handler,
        object $instance,
        string $type,
        string $targetName,
    ): void {
        $triggerHook = $attr->deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            $triggerHook = $this->provider !== null
                ? $this->provider->resolveRuntimeHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook), $targetName)
                : $this->resolveClosureHook($triggerHook, $instance, $targetName);
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

        $activate = fn (string $h, array $d, string $k) => $this->activateRuntimeEntry($h, $d, $k);

        if (did_action($triggerHook)) {
            // Trigger already fired — activate immediately.
            $this->activateMatchingDeferredEntries(
                static fn (string $h, array $d, string $k): bool => $h === $hook && $k === $key,
                $activate,
            );
            return;
        }

        $listener = null;
        $listener = function () use (&$listener, $triggerHook, $hook, $key, $activate): void {
            $activated = $this->activateMatchingDeferredEntries(
                static fn (string $h, array $d, string $k): bool => $h === $hook && $k === $key,
                $activate,
            );

            if ($activated > 0) {
                \remove_action($triggerHook, $listener, PHP_INT_MIN);
            }
        };

        \add_action($triggerHook, $listener, PHP_INT_MIN, 0);
    }

    /**
     * Detach a runtime handler from WordPress (once-consume or instance-lifetime
     * cleanup). The owner is passed as a WeakReference so the callback never
     * keeps the instance alive — only the record drop touches it, and only
     * when it still exists.
     *
     * @param string $hook       The hook the handler is registered on.
     * @param object $handler    The handler being removed.
     * @param int    $priority   The registered priority.
     * @param string $type       'action' or 'filter'.
     * @param \WeakReference<object>|null $ownerRef Weak owner reference (optional).
     */
    private function removeRuntimeHook(string $hook, object $handler, int $priority, string $type, ?\WeakReference $ownerRef = null): void
    {
        if ($type === 'action') {
            \remove_action($hook, $handler, $priority);
        } else {
            \remove_filter($hook, $handler, $priority);
        }

        $owner = $ownerRef?->get();
        if ($owner !== null && isset($this->registry[$owner])) {
            $this->registry[$owner] = array_values(array_filter(
                $this->registry[$owner],
                static fn (array $record): bool => $record['handler'] !== $handler,
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
     *
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

        return true;
    }

    /**
     * Re-evaluate the registerIf registration gate when activating a deferred
     * runtime entry. Without a provider there is no way to evaluate the gate,
     * so the entry is allowed (mirrors the container path's defer semantics).
     * @param DeferredHookEntry $data
     */
    protected function gateDeferredActivation(array $data, string $hook, string $key): bool
    {
        $registerIf = $data['registerIf'] ?? null;
        if ($registerIf === null) {
            return true;
        }

        if ($this->provider === null) {
            return true;
        }

        try {
            $allowed = $this->provider->evaluateRuntimeRegisterIf(
                $registerIf,
                $data['registerIfParams'] ?? [],
                $hook,
            );
        } catch (\Throwable $e) {
            Logger::warning(
                'WPHooksRuntimeRegistry',
                'Skipping deferred hook activation ' . $hook . ' — registerIf gate threw: ' . $e->getMessage()
            );
            return false;
        }

        if (!$allowed) {
            Logger::warning(
                'WPHooksRuntimeRegistry',
                'Skipping deferred hook activation ' . $hook . ' — registerIf gate returned false.'
            );
            return false;
        }

        return true;
    }

    /**
     * Remove a once-consumed runtime hook: detach the handler from WordPress
     * and drop its record from the owner's registry list.
     *
     * Idempotent — the record may already be gone (e.g. the owner was
     * unregistered before the hook ever fired).
     *
     * @param object $instance Owner instance.
     * @param object $handler  The handler that consumed its once-fire.
     */
    private function removeOnceRecord(object $instance, object $handler): void
    {
        if (!isset($this->registry[$instance])) {
            return;
        }

        foreach ($this->registry[$instance] as $record) {
            if ($record['handler'] === $handler) {
                if ($record['type'] === 'action') {
                    \remove_action($record['hook'], $record['handler'], $record['priority']);
                } else {
                    \remove_filter($record['hook'], $record['handler'], $record['priority']);
                }
                break;
            }
        }

        $this->registry[$instance] = array_values(array_filter(
            $this->registry[$instance],
            static fn (array $record): bool => $record['handler'] !== $handler,
        ));
    }

    /**
     * Remove all hooks previously registered for the given instance.
     *
     * Calls remove_action() / remove_filter() for each registered hook
     * and clears internal tracking. Calling this on an instance that was
     * never registered is a no-op.
     *
     * @param object $instance The instance whose hooks should be removed.
     */
    public function unregisterHooksOn(object $instance): void
    {
        // Sweep the deferred pool too — entries still waiting for their
        // trigger hook (registerUnderHook) belong to this owner.
        $this->unregisterMatchingDeferredEntries(
            fn (string $hook, array $data): bool => $this->deferredEntryOwner($data) === $instance,
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
     * Register an action hook manually for an owner object.
     *
     * The owner is resolved from the callback when not given explicitly:
     *   - explicit $owner (always wins when provided);
     *   - `[$object, 'method']` array callables → the object;
     *   - first-class callables / bound closures → the bound `$this`;
     *   - invokable objects → the object itself.
     *
     * The hook name, callback and executeIf closure may capture the surrounding
     * scope directly — no container is involved on the runtime registry.
     * @template T of callable
     * @template O of Object
     * @param string      $hook        Hook name.
     * @param T           $callback    Callable invoked when the hook fires.
     * @param int         $priority    Hook priority.
     * @param int         $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf   Optional gate: invoked directly, must return bool.
     * @param O|null $owner       Owning object (defaults to inference).
     *
     * @throws \RuntimeException when the owner cannot be inferred or the callback is not callable.
     */
    public function registerAction(
        string $hook,
        mixed $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
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
            owner: $this->resolveOwner($callback, $owner),
        );
    }

    /**
     * Register a filter hook manually for an owner object.
     *
     * Semantics identical to {@see registerAction()} — the callback result is
     * returned to the filter pipeline, and the original value passes through
     * untouched when the handler (or its executeIf) fails.
     * @template T of callable
     * @template O of Object
     * @param string $hook Hook name
     * @param T $callback Callable invoked when the hook fires.
     * @param int $priority Hook priority
     * @param int $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf Optional gate: invoked directly, must return bool.
     * @param O|null $owner Owning object (defaults to inference).
     * @throws \RuntimeException when the owner cannot be inferred or the callback is not callable.
     */
    public function registerFilter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
        ?\Closure $executeIf = null,
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
            owner: $this->resolveOwner($callback, $owner),
        );
    }

    /**
     * Shared core for manual registration: wraps the callback in a
     * RuntimeCallableHookHandler, registers it with WordPress immediately and
     * records it under the owner for lifetime-scoped unregistration.
     *
     * @template T of callable
     * @template O of Object
     * @param string        $type        'action' or 'filter'.
     * @param string        $hook        Hook name.
     * @param T             $callback    Callable invoked when the hook fires.
     * @param int           $priority    Hook priority.
     * @param int           $acceptedArgs Number of accepted arguments.
     * @param \Closure|null $executeIf   Optional gate: invoked directly, must return bool.
     * @param O        $owner       Owning object (resolved by the caller).
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
    ): void {
        if (!is_callable($callback)) {
            $error = 'Cannot register ' . $type . ' hook ' . $hook . ' — callback is not callable.';
            Logger::error('WPHooksRuntimeRegistry', $error);
            throw new \RuntimeException($error);
        }

        // Match WordPress semantics: identical (hook, callback, priority)
        // registrations are deduplicated.
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

        $handler = new RuntimeCallableHookHandler($callback, $executeIf, $type);

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
     * Evaluate an attribute-parameter registerIf gate (static closure invoked directly).
     *
     * @param \Closure|null $registerIf Gate closure (null = no gate).
     * @param object        $instance  Owner instance.
     * @param string        $targetName Method/property name (for log messages).
     */
    private function evaluateRegisterIf(?\Closure $registerIf, object $instance, string $targetName): bool
    {
        if ($registerIf === null) {
            return true;
        }

        try {
            $allowed = $registerIf();
        } catch (\Throwable $e) {
            Logger::warning(
                'WPHooksRuntimeRegistry',
                'Skipping hook on ' . $targetName . ' — registerIf closure failed: ' . $e->getMessage()
            );
            return false;
        }

        if (!\is_bool($allowed)) {
            Logger::warning(
                'WPHooksRuntimeRegistry',
                'Skipping hook on ' . $targetName . ' — registerIf must return bool, got ' . get_debug_type($allowed)
            );
            return false;
        }

        if ($allowed === false) {
            Logger::warning(
                'WPHooksRuntimeRegistry',
                'Skipping hook on ' . $targetName . ' — registerIf gate returned false.'
            );
            return false;
        }

        return true;
    }

    #region ResolverUtils

    /**
     * Resolve the owner of a manual registration.
     *
     * @param callable    $callback Registration callback.
     * @param object|null $owner    Explicit owner — always wins when provided.
     *
     * @throws \RuntimeException when no owner can be inferred.
     */
    private function resolveOwner(callable $callback, ?object $owner): object
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
                'Cannot infer owner for hook registration — pass owner: explicitly.'
            );
        }

        if (is_object($callback)) {
            return $callback;
        }

        throw new \RuntimeException(
            'Cannot infer owner for hook registration — pass owner: explicitly.'
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
    private function resolveClosureHook(string|\Closure $hook, object $instance, string $targetName): ?string
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
    #endregion
}