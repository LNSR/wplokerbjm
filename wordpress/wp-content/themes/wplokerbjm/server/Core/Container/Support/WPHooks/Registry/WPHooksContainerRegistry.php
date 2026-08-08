<?php

declare(strict_types=1);
namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use ReflectionClass;
use ReflectionFunction;
use DI\Container;
use ReflectionProperty;
use TargetClass;
use WPLokerBJM\Shared\Log\Logger;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\{Provider\WPHookPlanProvider, HookRegistration, HookKey};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookHandler, ContainerLazyPropertyHookHandler};
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\{HookPattern, HookTagUtilities};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\{AnonClassHookPropertyAbstract};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookScannerTrait;

/**
 * Registry for WordPress hooks discovered via #[Action] and #[Filter] attributes.
 *
 * Stores all hook registrations as identifiable ContainerLazyHookHandler instances,
 * enabling unregistration by hook name, class, or specific class::method.
 * Service resolution is deferred to hook-fire time (lazy loading).
 * @phpstan-type HookConditionParam array{name: string, type: class-string|null, hasDefault: bool, default: mixed}
 * @phpstan-type SchedulerHookAttributeType array{
 *  key: HookKey,
 *  handler: ContainerLazyHookHandler|ContainerLazyPropertyHookHandler,
 *  type: 'action'|'filter',
 *  priority: int,
 *  accepted_args: int,
 *  tags: array<int, string>,
 *  registerIf: \Closure|null,
 *  registerIfParams: array<int, HookConditionParam>,
 *  executeIf: \Closure|null,
 *  executeIfParams: array<int, HookConditionParam>,
 *  once: bool,
 * }
 * @phpstan-type HandlerEntry array{key: HookKey, handler: ContainerLazyHookHandler|ContainerLazyPropertyHookHandler, type: 'action'|'filter', priority: int, accepted_args: int, tags: array<int, string>, registerIf: \Closure|null, registerIfParams: array<int, HookConditionParam>, executeIf: \Closure|null, executeIfParams: array<int, HookConditionParam>, once: bool}
 * @phpstan-type RemoveHandlerEntry array{handler: ContainerLazyHookHandler|ContainerLazyPropertyHookHandler, type: 'action'|'filter', priority: int}
 * @phpstan-import-type HookTargetResolve from DeferredHookManager
 */
class WPHooksContainerRegistry
{
    /**
     * @var array<string, array<string, HandlerEntry>>
     */
    private array $handlers = [];

    private bool $initialized = false;

    /**
     * deferRegisterUntilHook entries whose trigger hook already fired before
     * initialize(). Processed after registerAll's handler loop so no handler
     * is registered twice.
     *
     * @var list<array{string, string}> [hook, key] pairs.
     */
    private array $pendingDeferredActivation = [];

    /**
     * @param ContainerInterface $container
     * @param HookRegistration[] $hooksRegistration
     * @param WPHookPlanProvider $planProvider Plan provider for condition/hook-name resolution.
     * @param DeferredHookManager $deferredHookManager Deferred hook manager.
     * @param HookTargetResolver $resolverTarget Resolves callable targets to class/method pairs.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private array $hooksRegistration,
        private WPHookPlanProvider $planProvider,
        private DeferredHookManager $deferredHookManager,
        private HookTargetResolver $resolverTarget,
    ) {
    }

    /**
     * Register all stored hooks with WordPress via add_action/add_filter.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->registerAll($this->hooksRegistration);
        foreach ($this->handlers as $hook => $hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                try {
                    $this->addSingleHook($hook, $data);
                } catch (\Exception $e) {
                    Logger::error(
                        'WPHooksContainerRegistry',
                        "Error registering {$data['type']} '{$hook}' for {$key}: {$e->getMessage()}"
                    );
                }
            }
        }

        // Trigger hooks that had already fired before boot: activate the
        // entries now that the active pool is fully registered (deferring this
        // avoids double-registering handlers that were activated mid-registerAll).
        $activate = fn (string $h, array $d, string $k) => $this->activateEntry($h, $d, $k);
        foreach ($this->pendingDeferredActivation as [$hook, $key]) {
            $this->deferredHookManager->activateDeferredByKey($hook, $key, $activate);
        }
        $this->pendingDeferredActivation = [];

        $this->initialized = true;
    }

    #region deferred handlers activation methods

    /**
     * Activate all deferred handlers registered for a specific WordPress hook.
     *
     * Moves matching ContainerLazyHookHandler instances from $this->deferredHandlers
     * into $this->handlers and registers them with WordPress via add_action/add_filter.
     * Handlers that have already been activated are silently skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByHook('init');
     * ```
     ** types autocomplete DX generated by generate-meta-hooks.php
     * @param string $hook WordPress hook name.
     */
    public function activateDeferredByHook(string $hook): void
    {
        $this->deferredHookManager->activateDeferredByHook(
            $hook,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
        );
    }

    /**
     * Activate all deferred handlers belonging to a specific service class.
     *
     * Scans all deferred hooks and activates any ContainerLazyHookHandler whose owning
     * class matches the given FQCN. Already-active handlers are silently skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByClass(WPGraphQL::class);
     * ```
     *
     * @param class-string $class Fully qualified class name.
     */
    public function activateDeferredByClass(string $class): void
    {
        $this->deferredHookManager->activateDeferredByClass(
            $class,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
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
     * Fast-path resolution when a method manages its own hook status. DRY and refactor-proof via `__FUNCTION__`.
     * ```php
     *! $registry->activateDeferredByCallable([$this, __FUNCTION__]);
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
     *
     * @return void
     * @throws \InvalidArgumentException If the target cannot be resolved to a valid class and member.
     */
    public function activateDeferredByCallable(callable|string|array $target): void
    {
        $this->deferredHookManager->activateDeferredByCallable(
            $target,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
        );
    }

    /**
     * Activate all deferred handlers carrying at least one of the given tags.
     *
     * Scans the deferred handlers pool and transfers matching entries to the
     * active pool, registering them immediately with WordPress. Accepts a
     * single tag string or an array of tags — a hook matches when any of its
     * tags intersects the query set. Already-active handlers are silently
     * skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByTags(['cache', 'seo']);
     * $registry->activateDeferredByTags('cache');
     * ```
     *
     * @param array<string> $tags Tag or list of tags to activate.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTags(array $tags): int
    {
        $tags = HookTagUtilities::normalizeTags($tags);

        return $this->deferredHookManager->activateDeferredByTags(
            $tags,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
        );
    }

    /**
     * Activate all deferred handlers belonging to a service namespace.
     *
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash, so 'App\Core' never matches 'App\CoreExtra\Foo'.
     *
     * @param string $namespace Fully qualified namespace prefix.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByNamespace(string $namespace): int
    {
        return $this->deferredHookManager->activateDeferredByNamespace(
            $namespace,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
        );
    }

    /**
     * Activate all deferred handlers whose hook name matches a wildcard pattern.
     *
     * The pattern must contain exactly one trailing asterisk with a literal
     * prefix of at least two characters, e.g. 'mail_*'. Already-active
     * handlers are silently skipped.
     *
     * @param string $pattern Wildcard hook-name pattern.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByHookPattern(string $pattern): int
    {
        return $this->deferredHookManager->activateDeferredByHookPattern(
            $pattern,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
        );
    }

    /**
     * Activate all deferred handlers carrying at least one tag matching any of
     * the given wildcard patterns.
     *
     * Each pattern is its own tag family to wipe: an entry matches when any of
     * its tags matches any pattern (union of families).
     *
     * @param array<string> $patterns Tag wildcard patterns.
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTagPattern(array $patterns): int
    {
        return $this->deferredHookManager->activateDeferredByTagPattern(
            $patterns,
            fn(string $h, array $d, string $k) => $this->activateEntry($h, $d, $k)
        );
    }
    #endregion

    #region deferred handlers unregisteration methods
    /**
     * Unregister a deferred hook using a first-class callable, array lookup,
     * or class-string identifier.
     *
     * @param HookTargetResolve $target
     */
    public function unregisterDeferredByCallable(callable|string|array $target): void
    {
        $this->deferredHookManager->unregisterDeferredByCallable($target);
    }

    /**
     * Unregister all deferred handlers for a specific WordPress hook name.
     *
     * @param string $hook WordPress hook name.
     */
    public function unregisterDeferredByHook(string $hook): void
    {
        $this->deferredHookManager->unregisterDeferredByHook($hook);
    }

    /**
     * Unregister all deferred hooks belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     */
    public function unregisterDeferredByClass(string $class): void
    {
        $this->deferredHookManager->unregisterDeferredByClass($class);
    }

    /**
     * Unregister all deferred hooks belonging to a service namespace.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param string $namespace Fully qualified namespace prefix.
     */
    public function unregisterDeferredByNamespace(string $namespace): void
    {
        $this->deferredHookManager->unregisterDeferredByNamespace($namespace);
    }

    /**
     * Unregister all deferred handlers carrying at least one of the given tags.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * Accepts a single tag string or an array of tags; a hook matches when any
     * of its tags intersects the query set.
     *
     * @param array<string> $tags Tag or list of tags to unregister.
     */
    public function unregisterDeferredByTags(array $tags): void
    {
        $this->deferredHookManager->unregisterDeferredByTags(HookTagUtilities::normalizeTags($tags));
    }

    /**
     * Unregister all deferred handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterDeferredByHookPattern(string $pattern): void
    {
        $this->deferredHookManager->unregisterDeferredByHookPattern($pattern);
    }

    /**
     * Unregister all deferred handlers carrying at least one tag matching any
     * of the given wildcard patterns.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterDeferredByTagPattern(array $patterns): void
    {
        $this->deferredHookManager->unregisterDeferredByTagPattern($patterns);
    }
    #endregion
    #region main handlers unregisteration methods
    /**
     * Unregister a registered hook via first-class callable, array lookup,
     * or class-string identifier.
     *
     * @param HookTargetResolve $target
     */
    public function unregisterByCallable(callable|string|array $target): void
    {
        [$class, $method] = $this->resolverTarget->resolve($target);

        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$data['key']->isForCallable($class, $method)) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all handlers for a specific WordPress hook name.
     *
     * @param string $hook WordPress hook name.
     */
    public function unregisterByHook(string $hook): void
    {
        foreach ($this->handlers[$hook] ?? [] as $data) {
            $this->removeSingleHook($hook, $data);
        }

        unset($this->handlers[$hook]);
    }

    /**
     * Unregister all hooks belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     */
    public function unregisterByClass(string $class): void
    {
        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$data['key']->isForClass($class)) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all active hooks belonging to a service namespace.
     *
     * Only touches the active pool — deferred handlers are never affected.
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash, so 'App\Core' never matches 'App\CoreExtra\Foo'.
     *
     * @param string $namespace Fully qualified namespace prefix.
     */
    public function unregisterByNamespace(string $namespace): void
    {
        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$data['key']->isWithinNamespace($namespace)) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->handlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all active handlers carrying at least one of the given tags.
     *
     * Only touches the active pool — deferred handlers are never affected.
     * Accepts a single tag string or an array of tags; a hook matches when any
     * of its tags intersects the query set.
     *
     * @param array<string> $tags Tag or list of tags to unregister.
     */
    public function unregisterByTags(array $tags): void
    {
        $tags = HookTagUtilities::normalizeTags($tags);

        if ($tags === []) {
            return;
        }

        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (array_intersect($tags, $data['tags']) === []) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->handlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all active handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the active pool — deferred handlers are never affected.
     *
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterByHookPattern(string $pattern): void
    {
        HookPattern::assertValid($pattern);

        foreach ($this->handlers as $hook => &$hookHandlers) {
            if (!HookPattern::matches($hook, $pattern)) {
                continue;
            }
            foreach ($hookHandlers as $key => $data) {
                $this->removeSingleHook($hook, $data);
            }
            unset($this->handlers[$hook]);
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all active handlers carrying at least one tag matching any of
     * the given wildcard patterns.
     *
     * Only touches the active pool — deferred handlers are never affected.
     *
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterByTagPattern(array $patterns): void
    {
        if ($patterns === []) {
            return;
        }
        HookPattern::assertValidAll($patterns);

        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!HookPattern::matchesAny($data['tags'], $patterns)) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->handlers[$hook]);
            }
        }
        unset($hookHandlers);
    }
    #endregion
    /**
     * Internal: call add_action or add_filter for a single handler entry.
     *
     * @param string $hook
     * @param HandlerEntry $data
     */
    private function addSingleHook(string $hook, array $data): void
    {
        if ($data['type'] === 'action') {
            add_action($hook, $data['handler'], $data['priority'], $data['accepted_args']);
        } else {
            add_filter($hook, $data['handler'], $data['priority'], $data['accepted_args']);
        }
    }

    /**
     * Callback provided to the DeferredHookManager: moves a deferred entry into
     * the active pool and registers it with WordPress.
     *
     * @param HandlerEntry $data
     */
    private function activateEntry(string $hook, array $data, string $key): bool
    {
        // Guard: skip if already activated
        if (isset($this->handlers[$hook][$key])) {
            return false;
        }

        $this->handlers[$hook][$key] = $data;

        $this->addSingleHook($hook, $data);

        return true;
    }

    /**
     * Internal: call remove_action or remove_filter for a single handler entry.
     *
     * @param RemoveHandlerEntry $data
     */
    private function removeSingleHook(string $hook, array $data): void
    {
        if ($data['type'] === 'action') {
            remove_action($hook, $data['handler'], $data['priority']);
        } else {
            remove_filter($hook, $data['handler'], $data['priority']);
        }
    }

    /**
     * Internal: remove a once-registration from the active pool after its
     * first fire (consume-on-any-evaluation). Idempotent.
     */
    private function removeOnceEntry(string $hook, string $key): void
    {
        if (!isset($this->handlers[$hook][$key])) {
            return;
        }

        $this->removeSingleHook($hook, $this->handlers[$hook][$key]);
        unset($this->handlers[$hook][$key]);

        if (empty($this->handlers[$hook])) {
            unset($this->handlers[$hook]);
        }
    }

    /**
     * Register hook registrations from the scanner.
     * Pre-builds ContainerLazyHookHandler instances and validates container existence.
     *
     * @param HookRegistration[] $registrations
     */
    private function registerAll(array|HookRegistration $registrations): void
    {
        foreach ($registrations as $reg) {
            $registration = $reg instanceof HookRegistration ? $reg : HookRegistration::fromArray($reg);

            if (!$this->container->has($registration->class)) {
                Logger::warning(
                    'WPHooksContainerRegistry',
                    'Skipping hook ' . $registration->hook
                    . ' — class not in container: ' . $registration->class
                );
                continue;
            }

            try {
                $hookName = $this->planProvider->resolveHookName(
                    $registration->hook,
                    $this->container,
                    $registration->hookParams,
                    $registration->class . '::' . $registration->method
                );
            } catch (\RuntimeException $e) {
                Logger::error(
                    'WPHooksContainerRegistry',
                    'Skipping hook for ' . $registration->class . '::' . $registration->method . ' — ' . $e->getMessage()
                );
                continue;
            }

            // Registration gate: evaluated ONCE at registration time — a false
            // result means the hook is never registered (deferred or not).
            // Entries carrying deferRegisterUntilHook skip this gate entirely:
            // they defer to the named trigger hook, where the gate is evaluated
            // at activation time (when request context exists).
            if ($registration->deferRegisterUntilHook === null) {
                try {
                    $allowed = $this->planProvider->evaluateRegistrationGate(
                        $registration->registerIf,
                        $registration->registerIfParams,
                        $this->container,
                        $registration->class . '::' . $registration->method,
                        $registration->class
                    );
                } catch (\Throwable $e) {
                    Logger::error(
                        'WPHooksContainerRegistry',
                        'Skipping hook for ' . $registration->class . '::' . $registration->method . ' — ' . $e->getMessage()
                    );
                    continue;
                }
                if (!$allowed) {
                    Logger::warning(
                        'WPHooksContainerRegistry',
                        'Skipping hook ' . $hookName . ' — registerIf gate returned false.'
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
                        $this->container,
                        $registration->class . '::' . $registration->method,
                        $registration->class
                    );
                } else {
                    $resolvedTags = $registration->tags;
                }

                $resolvedTags = array_map(
                    static fn ($tag) => HookTagUtilities::normalizeTagValue($tag),
                    $resolvedTags
                );
            } catch (\Throwable $e) {
                Logger::error(
                    'WPHooksContainerRegistry',
                    'Skipping hook for ' . $registration->class . '::' . $registration->method
                    . ' — ' . $e->getMessage()
                );
                continue;
            }

            $key = HookKey::fromRegistration($registration);

            $handler = $registration->target === 'method'
                ? new ContainerLazyHookHandler($this->container, $registration->class, $registration->method, $registration->visibility, $registration->type, $registration->executeIf, $registration->executeIfParams, $this->planProvider, $registration->hookArgs, $registration->once)
                : new ContainerLazyPropertyHookHandler($this->container, $registration->class, $registration->method, $registration->visibility, $registration->type, $registration->executeIf, $registration->executeIfParams, $this->planProvider, $registration->hookArgs, $registration->once);

            if ($registration->once) {
                $handler->setRemoveCallback(
                    fn () => $this->removeOnceEntry($hookName, $key->toString())
                );
            }
            /**
             * @var SchedulerHookAttributeType
             */
            $entry = [
                'key' => $key,
                'handler' => $handler,
                'type' => $registration->type,
                'priority' => $registration->priority,
                'accepted_args' => $registration->acceptedArgs,
                'tags' => $resolvedTags,
                'registerIf' => $registration->registerIf,
                'registerIfParams' => $registration->registerIfParams,
                'executeIf' => $registration->executeIf,
                'executeIfParams' => $registration->executeIfParams,
                'once' => $registration->once,
            ];

            // deferRegisterUntilHook implies deferral: the entry is held in the
            // deferred pool until the trigger hook fires, regardless of how the
            // registration array was built (attribute, scanner cache, runtime).
            if ($registration->deferRegister || $registration->deferRegisterUntilHook !== null) {
                $this->deferredHookManager->addDeferred($hookName, $key->toString(), $entry);

                if ($registration->deferRegisterUntilHook !== null) {
                    $triggerHook = $registration->deferRegisterUntilHook;
                    if ($triggerHook instanceof \Closure) {
                        try {
                            $triggerHook = $this->planProvider->resolveHookName(
                                $registration->deferRegisterUntilHook,
                                $this->container,
                                $registration->deferRegisterUntilHookParams ?? [],
                                $registration->class . '::' . $registration->method
                            );
                        } catch (\RuntimeException $e) {
                            Logger::error(
                                'WPHooksContainerRegistry',
                                'Skipping hook for ' . $registration->class . '::' . $registration->method
                                . ' — ' . $e->getMessage()
                            );
                            continue;
                        }
                    }
                    $this->scheduleDeferredActivation(
                        $triggerHook,
                        $hookName,
                        $key->toString()
                    );
                }
            } else {
                $this->handlers[$hookName][$key->toString()] = $entry;
            }
        }
    }

    /**
     * Schedule one-shot activation of a deferRegisterUntilHook entry when the
     * trigger hook fires. If the trigger already fired (did_action), the entry
     * is activated immediately.
     *
     * The listener stays attached while the gates keep failing, so a later
     * fire of the trigger hook gets another chance. Once activation succeeds
     * the listener removes itself.
     *
     * @param string $triggerHook WordPress hook that gates activation.
     * @param string $hook        The deferred entry's own hook name.
     * @param string $key         The deferred entry's key string.
     */
    private function scheduleDeferredActivation(string $triggerHook, string $hook, string $key): void
    {
        $activate = fn (string $h, array $d, string $k) => $this->activateEntry($h, $d, $k);

        if (did_action($triggerHook)) {
            // The trigger already fired before boot — defer to initialize()'s
            // pending queue so the entry activates exactly once, after the
            // active handler loop has run.
            $this->pendingDeferredActivation[] = [$hook, $key];
            return;
        }

        $listener = null;
        $listener = function () use (&$listener, $triggerHook, $hook, $key, $activate): void {
            $activated = $this->deferredHookManager->activateDeferredByKey($hook, $key, $activate);

            if ($activated) {
                remove_action($triggerHook, $listener, PHP_INT_MIN);
            }
        };

        add_action($triggerHook, $listener, PHP_INT_MIN, 0);
    }
}
/**
 * @internal not for external use beyond @see WPHooksContainerRegistry
 * 
 * @template TargetClass of Object|class-string
 * @phpstan-type HookTargetResolve TargetClass|callable|string|array{TargetClass, string}
 * @phpstan-import-type HandlerEntry from WPHooksContainerRegistry
 * @phpstan-import-type SchedulerHookAttributeType from WPHooksContainerRegistry
 */
class DeferredHookManager
{
    /**
     * Deferred handlers pool, keyed by [hook][key-string] like the active pool.
     *
     * @var array<string, array<string, HandlerEntry>>
     */
    private array $deferredHandlers = [];

    /**
     * @param WPHookPlanProvider  $planProvider Plan provider used for registerIf gates.
     * @param ContainerInterface $container    Container used by gate closures.
     */
    public function __construct(
        private WPHookPlanProvider $planProvider,
        private ContainerInterface $container,
        private HookTargetResolver $resolverTarget,
    ) {
    }

    /**
     * Store a deferred hook entry under its hook + key.
     *
     * @param SchedulerHookAttributeType $entry
     */
    public function addDeferred(string $hook, string $key, array $entry): void
    {
        $this->deferredHandlers[$hook][$key] = $entry;
    }

    /**
     * Activate all deferred handlers registered for a specific WordPress hook.
     *
     * @param callable(string, array, string): bool $activateEntry Registry callback: moves the
     *                                                             entry to the active pool and
     *                                                             registers it; returns true when
     *                                                             newly activated, false when the
     *                                                             handler was already active.
     */
    public function activateDeferredByHook(string $hook, callable $activateEntry): void
    {
        if (empty($this->deferredHandlers[$hook])) {
            return;
        }

        foreach ($this->deferredHandlers[$hook] as $key => $data) {
            // Registration gate: re-evaluated at activation time.
            if (!$this->gateDeferredActivation($data, $hook, $key)) {
                continue;
            }

            $activateEntry($hook, $data, $key);
        }

        unset($this->deferredHandlers[$hook]);
    }

    /**
     * Activate all deferred handlers belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     */
    public function activateDeferredByClass(string $class, callable $activateEntry): void
    {
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$data['key']->isForClass($class)) {
                    continue;
                }

                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }

                $activateEntry($hook, $data, $key);
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Activate all deferred handlers belonging to a service namespace.
     *
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash, so 'App\Core' never matches 'App\CoreExtra\Foo'.
     *
     * @param string $namespace Fully qualified namespace prefix.
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByNamespace(string $namespace, callable $activateEntry): int
    {
        $activated = 0;

        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$data['key']->isWithinNamespace($namespace)) {
                    continue;
                }

                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }

                $activateEntry($hook, $data, $key);
                unset($hookHandlers[$key]);
                $activated++;
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);

        return $activated;
    }

    /**
     * Activate deferred hook handlers matching the specified target callable or identifier.
     *
     * @param HookTargetResolve $target
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     *
     * @throws \InvalidArgumentException If the target cannot be resolved to a valid class and member.
     */
    public function activateDeferredByCallable(callable|string|array $target, callable $activateEntry): void
    {
        [$class, $method] = $this->resolverTarget->resolve($target);
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$data['key']->isForCallable($class, $method)) {
                    continue;
                }

                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }

                $activateEntry($hook, $data, $key);
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Activate all deferred handlers carrying at least one of the given tags.
     *
     * @param array $tags Tag or list of tags to activate.
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTags(array $tags, callable $activateEntry): int
    {
        if ($tags === []) {
            return 0;
        }

        $activated = 0;
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (array_intersect($tags, $data['tags']) === []) {
                    continue;
                }

                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }

                if ($activateEntry($hook, $data, $key)) {
                    $activated++;
                }
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);

        return $activated;
    }

    /**
     * Activate all deferred handlers whose hook name matches a wildcard pattern.
     *
     * The pattern must contain exactly one trailing asterisk with a literal
     * prefix of at least two characters, e.g. 'mail_*'. Already-active
     * handlers are silently skipped.
     *
     * @param string $pattern Wildcard hook-name pattern.
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByHookPattern(string $pattern, callable $activateEntry): int
    {
        HookPattern::assertValid($pattern);

        $activated = 0;
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            if (!HookPattern::matches($hook, $pattern)) {
                continue;
            }
            foreach ($hookHandlers as $key => $data) {
                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }
                if ($activateEntry($hook, $data, $key)) {
                    $activated++;
                }
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);

        return $activated;
    }

    /**
     * Activate all deferred handlers carrying at least one tag matching any of
     * the given wildcard patterns.
     *
     * Each pattern is its own tag family to wipe: an entry matches when any of
     * its tags matches any pattern (union of families).
     *
     * @param array<string> $patterns Tag wildcard patterns.
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTagPattern(array $patterns, callable $activateEntry): int
    {
        if ($patterns === []) {
            return 0;
        }
        HookPattern::assertValidAll($patterns);

        $activated = 0;
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!HookPattern::matchesAny($data['tags'], $patterns)) {
                    continue;
                }

                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }
                if ($activateEntry($hook, $data, $key)) {
                    $activated++;
                }
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);

        return $activated;
    }

    /**
     * Activate a single deferred handler by exact hook + key.
     *
     * One-shot entry point for deferRegisterUntilHook entries: the trigger
     * hook listener calls this when the trigger fires. registerIf is
     * re-evaluated as with every activation; executeIf is additionally
     * evaluated at activation time, but only when all its parameters are
     * resolvable from the container — request-scoped parameters (e.g.
     * \WP_Query) are never fabricated, so executeIf stays a per-fire guard
     * inside the handler for those entries.
     * @internal
     * @param callable(string, array, string): bool $activateEntry Registry callback (see activateDeferredByHook).
     * @return bool True when the entry was newly activated; false when the
     *              entry is unknown, already active, or a gate rejected it.
     */
    public function activateDeferredByKey(string $hook, string $key, callable $activateEntry): bool
    {
        if (!isset($this->deferredHandlers[$hook][$key])) {
            return false;
        }

        $data = $this->deferredHandlers[$hook][$key];

        // Registration gate: re-evaluated at activation time.
        if (!$this->gateDeferredActivation($data, $hook, $key)) {
            return false;
        }

        // executeIf gate: only when every parameter is container-resolvable.
        if (($data['executeIf'] ?? null) !== null && $this->executeIfResolvable($data['executeIfParams'] ?? [])) {
            try {
                $allowed = $this->planProvider->evaluateExecuteIf(
                    $data['executeIf'],
                    $data['executeIfParams'],
                    $this->container,
                    $key,
                    $data['key']->class,
                );
            } catch (\Throwable $e) {
                Logger::error(
                    'WPHooksContainerRegistry',
                    'Skipping deferred hook activation ' . $hook . ' — executeIf gate threw: ' . $e->getMessage()
                );
                return false;
            }

            if (!$allowed) {
                Logger::warning(
                    'WPHooksContainerRegistry',
                    'Skipping deferred hook activation ' . $hook . ' — executeIf gate returned false.'
                );
                return false;
            }
        }

        $activated = $activateEntry($hook, $data, $key);

        unset($this->deferredHandlers[$hook][$key]);

        return $activated;
    }

    /**
     * Whether every parameter of an executeIf plan is resolvable from the
     * container. Scalar parameters with defaults resolve to their default;
     * class-typed parameters must exist in the container. A parameter without
     * a type and without a default is request-scoped → not resolvable.
     *
     * @param array<string, mixed> $executeIfParams
     */
    private function executeIfResolvable(array $executeIfParams): bool
    {
        foreach (($executeIfParams['params'] ?? []) as $param) {
            $type = $param['type'] ?? null;
            if ($type === null) {
                if (!($param['hasDefault'] ?? false)) {
                    return false;
                }
                continue;
            }
            if (!$this->container->has($type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Unregister a deferred hook using a first-class callable, array lookup,
     * or class-string identifier.
     *
     * @param HookTargetResolve $target
     */
    public function unregisterDeferredByCallable(callable|string|array $target): void
    {
        [$class, $method] = $this->resolverTarget->resolve($target);

        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if ($data['key']->isForCallable($class, $method)) {
                    unset($hookHandlers[$key]);
                }
            }
        }
    }

    /**
     * Unregister all deferred handlers for a specific WordPress hook name.
     *
     * @param string $hook WordPress hook name.
     */
    public function unregisterDeferredByHook(string $hook): void
    {
        unset($this->deferredHandlers[$hook]);
    }

    /**
     * Unregister all deferred hooks belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     */
    public function unregisterDeferredByClass(string $class): void
    {
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if ($data['key']->isForClass($class)) {
                    unset($hookHandlers[$key]);
                }
            }
        }
    }

    /**
     * Unregister all deferred hooks belonging to a service namespace.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash.
     *
     * @param string $namespace Fully qualified namespace prefix.
     */
    public function unregisterDeferredByNamespace(string $namespace): void
    {
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if ($data['key']->isWithinNamespace($namespace)) {
                    unset($hookHandlers[$key]);
                }
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all deferred handlers carrying at least one of the given tags.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array $tags Tag or list of tags to unregister.
     */
    public function unregisterDeferredByTags(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (array_intersect($tags, $data['tags']) !== []) {
                    unset($hookHandlers[$key]);
                }
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all deferred handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterDeferredByHookPattern(string $pattern): void
    {
        HookPattern::assertValid($pattern);

        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            if (!HookPattern::matches($hook, $pattern)) {
                continue;
            }
            unset($this->deferredHandlers[$hook]);
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all deferred handlers carrying at least one tag matching any
     * of the given wildcard patterns.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterDeferredByTagPattern(array $patterns): void
    {
        if ($patterns === []) {
            return;
        }
        HookPattern::assertValidAll($patterns);

        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (HookPattern::matchesAny($data['tags'], $patterns)) {
                    unset($hookHandlers[$key]);
                }
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Re-evaluate the registerIf registration gate when activating a deferred hook.
     *
     * The gate was already evaluated once in registerAll(); activation is the
     * moment the hook actually registers with WordPress, so it is re-checked
     * here. On failure the entry stays in the deferred pool (a later activation
     * attempt may succeed once the gate flips to true except if 'once' is true).
     *
     * @param HandlerEntry $data Handler entry.
     * @param string $hook
     */
    private function gateDeferredActivation(array $data, string $hook, string $key): bool
    {
        if (($data['registerIf'] ?? null) === null) {
            return true;
        }

        try {
            $allowed = $this->planProvider->evaluateRegistrationGate(
                $data['registerIf'],
                $data['registerIfParams'] ?? [],
                $this->container,
                $key,
                $data['key']->class
            );
        } catch (\Throwable $e) {
            Logger::error(
                'WPHooksContainerRegistry',
                'Skipping deferred hook activation ' . $hook . ' — ' . $e->getMessage()
            );
            return false;
        }

        if (!$allowed) {
            Logger::warning(
                'WPHooksContainerRegistry',
                'Skipping deferred hook activation ' . $hook . ' — registerIf gate returned false.'
            );
            return false;
        }

        return true;
    }
}
/**
 * @internal not for use beyond \WPLokerBJM\Core\Container\Support\WPHooks\Registry\
 * @phpstan-import-type HookTargetResolve from DeferredHookManager
 */
class HookTargetResolver
{
    /**
     * Per-request cache of resolved callable → [FQCN, memberName].
     *
     * WeakMap automatically drops entries when their key (the callable
     * object) is garbage-collected, avoiding memory leaks.
     *
     * @var \WeakMap<object, array{class-string, string}>
     */
    private \WeakMap $callableTargetCache;

    public function __construct()
    {
        $this->callableTargetCache = new \WeakMap();
    }

    /**
     * @param HookTargetResolve $target
     * @return array{class-string, string}
     */
    public function resolve(object|callable|array|string $target): array
    {
        if (is_object($target) && isset($this->callableTargetCache[$target])) {
            return $this->callableTargetCache[$target];
        }

        $result = $this->doResolveCallableTarget($target);

        if (is_object($target)) {
            $this->callableTargetCache[$target] = $result;
        }
        return $result;
    }

    /**
     * @param HookTargetResolve $target
     * @return array{class-string, string}
     */
    private function doResolveCallableTarget(object|callable|array|string $target): array
    {
        // 1. Array [$object, 'memberName'] — fast path
        if (is_array($target)) {
            if (is_object($target[0])) {
                return [get_class($target[0]), $target[1]];
            } elseif (is_string($target[0])) {
                return [$target[0], $target[1]];
            }
        }

        // 2. String 'Class::method' — static fallback
        if (is_string($target)) {
            $parts = explode('::', $target, 2);
            return [$parts[0], $parts[1] ?? '__invoke'];
        }

        // 3. Invokable Object Instance (anonymous class from a property)
        if (!$target instanceof \Closure && is_object($target)) {
            return $this->analyseObject($target);
        }

        // 4. Closure (First-class callable or PHP 8.4 property hook accessor)
        $ref = new \ReflectionFunction($target);
        $calledClass = $ref->getClosureCalledClass()?->getName();

        if ($calledClass === null) {
            throw new \InvalidArgumentException('Callable target must be bound to an object instance.');
        }

        $name = $ref->getName();

        // Standard Instance Method ($service->method(...))
        if (!str_contains($name, '{closure')) {
            return [$calledClass, $name];
        }

        // PHP 8.4 Property Hook Closure ("{closure:FQCN::$propertyName::get():line}")
        if (str_contains($name, '::$')) {
            $start = strpos($name, '::$') + 3;
            $end = strpos($name, '::', $start);

            if ($start !== false && $end !== false) {
                return [$calledClass, substr($name, $start, $end - $start)];
            }
        }

        throw new \InvalidArgumentException("Unable to resolve hook target for class {$calledClass}.");
    }

    /**
     * 
     * @param object $target
     * @return array{class-string, string}
     */
    private function analyseObject(object $target): array
    {
        $className = $target::class;

        // Anonymous class extending AnonClassHookPropertyAbstract —
        // reads parent class & property directly, no backtrace needed.
        if ($target instanceof AnonClassHookPropertyAbstract) {
            return [$target->parentClass, $target->parentProperty];
        }

        // Best-effort fallback for anonymous classes: walk the call stack
        // to find which object's property holds this instance.
        if ((new \ReflectionClass($target))->isAnonymous()) {
            $frames = Backtrace::create()
                ->withArguments(false)
                ->limit(10)
                ->frames();

            /** @var Frame $frame */
            foreach ($frames as $frame) {
                $callerObject = $frame->object;
                if ($callerObject === null) {
                    continue;
                }

                $refClass = new \ReflectionClass($callerObject);
                foreach ($refClass->getProperties() as $property) {
                    if ($property->isInitialized($callerObject) && $property->getValue($callerObject) === $target) {
                        Logger::warning(
                            "WPHooksContainerRegistry: ",
                            "Anon class without extending AnonClassHookPropertyAbstract, it's recommended to use 'AnonClassHookPropertyAbstract'." . $refClass->getName() . "::" . $property->getName()
                        );
                        return [$refClass->getName(), $property->getName()];
                    }
                }
            }
        }

        return [$className, '__invoke'];
    }
}