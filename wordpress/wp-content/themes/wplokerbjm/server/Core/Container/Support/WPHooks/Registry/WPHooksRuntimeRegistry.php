<?php

declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use ReflectionClass;
use ReflectionFunction;
use WPLokerBJM\Core\Container\Support\WPHooks\{RuntimeInstancePropertyHookHandler, RuntimeInstanceHookHandler, RuntimeCallableHookHandler};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookScannerTrait;

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
 *! All attribute hooks are registered eagerly — the `defer` & 'condition' flags are
 *! ignored on the attribute path (no container access), and static methods are
 *! silently skipped.
 ** But for compensation:
 *! Manual registration (registerAction() / registerFilter()) is the escape hatch
 *! for runtime state: hook names, callbacks and condition closures may capture
 *! the surrounding scope directly, and `condition` closures ARE supported there
 *! (invoked directly, must return bool).
 *
 */
class WPHooksRuntimeRegistry
{
    use HookScannerTrait;

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

    public function __construct()
    {
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
                if ($attr->hook instanceof \Closure) {
                    Logger::warning(
                        'WPHooksRuntimeRegistry',
                        'Skipping hook on ' . $method->getName()
                        . ' — closure hooks are unsupported on runtime-registered instances (no container access)'
                    );
                    return;
                }

                if ($attr->registerIf !== null) {
                    Logger::warning(
                        'WPHooksRuntimeRegistry',
                        'Skipping hook on ' . $method->getName()
                        . ' — registerIf closures are unsupported on runtime-registered instances (no container access)'
                    );
                    return;
                }

                if ($attr->executeIf !== null) {
                    Logger::warning(
                        'WPHooksRuntimeRegistry',
                        'Skipping hook ' . $attr->hook . ' on ' . $method->getName()
                        . ' — executeIf closures are unsupported on runtime-registered instances (no container access)'
                    );
                    return;
                }

                $handler = new RuntimeInstanceHookHandler(
                    $instance,
                    $method->getName(),
                    $visibility,
                    $type
                );

                if ($type === 'action') {
                    \add_action($attr->hook, $handler, $attr->priority, $attr->acceptedArgs);
                } else {
                    \add_filter($attr->hook, $handler, $attr->priority, $attr->acceptedArgs);
                }

                $records[] = [
                    'handler' => $handler,
                    'hook' => $attr->hook,
                    'priority' => $attr->priority,
                    'type' => $type,
                ];
            }
        );

        $this->scanPropertyHooks(
            $ref,
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records): void {
                if ($attr->hook instanceof \Closure) {
                    Logger::warning(
                        'WPHooksRuntimeRegistry',
                        'Skipping hook on ' . $property->getName()
                        . ' — closure hooks are unsupported on runtime-registered instances (no container access)'
                    );
                    return;
                }

                if ($attr->registerIf !== null) {
                    Logger::warning(
                        'WPHooksRuntimeRegistry',
                        'Skipping hook on ' . $property->getName()
                        . ' — registerIf closures are unsupported on runtime-registered instances (no container access)'
                    );
                    return;
                }

                if ($attr->executeIf !== null) {
                    Logger::warning(
                        'WPHooksRuntimeRegistry',
                        'Skipping hook ' . $attr->hook . ' on ' . $property->getName()
                        . ' — executeIf closures are unsupported on runtime-registered instances (no container access)'
                    );
                    return;
                }

                $handler = new RuntimeInstancePropertyHookHandler(
                    $instance,
                    $property->getName(),
                    $visibility,
                    $type
                );

                if ($type === 'action') {
                    \add_action($attr->hook, $handler, $attr->priority, $attr->acceptedArgs);
                } else {
                    \add_filter($attr->hook, $handler, $attr->priority, $attr->acceptedArgs);
                }

                $records[] = [
                    'handler' => $handler,
                    'hook' => $attr->hook,
                    'priority' => $attr->priority,
                    'type' => $type,
                ];
            }
        );

        $this->scanned[$instance] = true;
        $this->registry[$instance] = array_merge($this->registry[$instance] ?? [], $records);
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
}