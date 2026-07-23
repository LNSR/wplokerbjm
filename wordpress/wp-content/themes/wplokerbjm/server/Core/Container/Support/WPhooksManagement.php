<?php

namespace WPLokerBJM\Core\Container\Support;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\Utilities\FileScannerTrait;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * Scans directories for WordPress hook attribute registrations.
 *
 * This scanner searches for public, non-static methods annotated with
 * #[Action] or #[Filter] attributes across all autowirable PHP classes.
 * The resulting registrations are used by the Init service to automatically
 * register WordPress hooks via the DI container.
 *
 * @see \WPLokerBJM\Core\Container\Init
 * @see Action
 * @see Filter
 * @see Utilities\FileScannerTrait
 *
 * @phpstan-type HookRegistration array{class: class-string, method: string, type: 'action'|'filter', hook: string, priority: int, accepted_args: int, defer: bool}
 */
class WPhooksScanner
{
    use FileScannerTrait;

    /** @var array<int, HookRegistration>|null */
    private ?array $cachedHookRegistrations = null;

    public function __construct(private string $baseDirectory, private string $namespace = 'WPLokerBJM')
    {
        $this->baseDirectory = rtrim($baseDirectory, '/');
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Get hook registrations from #[Action] and #[Filter] attributes.
     *
     * Scans public, non-static methods for hook attributes across all PHP files
     * in the base directory. Results are cached in-memory for the request.
     *
     * @return HookRegistration[]
     */
    public function getHookRegistrations(): array
    {
        if ($this->cachedHookRegistrations !== null) {
            return $this->cachedHookRegistrations;
        }

        $registrations = $this->performHookRegistrationScan();
        $this->cachedHookRegistrations = $registrations;
        return $registrations;
    }

    /**
     * Perform the actual hook registration scanning logic.
     *
     * Only public, **non-static** methods are considered: hooks are resolved
     * through the DI container so the owning service must be instantiable.
     * Static hook methods are intentionally ignored.
     *
     * @return HookRegistration[]
     */
    private function performHookRegistrationScan(): array
    {
        $registrations = [];
        $phpFiles = $this->findPhpFiles();

        foreach ($phpFiles as $file) {
            $classNames = $this->getClassNamesFromFile($file);


            foreach ($classNames as $className) {
                if (class_exists($className)) {
                    try {
                        $reflection = new ReflectionClass($className);
                        /** @var ReflectionMethod $method */
                        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                            // Skip static methods — hooks must be instance methods
                            // because the container resolves the owning service lazily.
                            if ($method->isStatic()) {
                                continue;
                            }
                            /** @var ReflectionClass $attribute */
                            foreach ($method->getAttributes(Action::class) as $attribute) {
                                $action = $attribute->newInstance();
                                /** @var Action $action */
                                $registrations[] = [
                                    'class' => $className,
                                    'method' => $method->getName(),
                                    'type' => 'action',
                                    'hook' => $action->hook,
                                    'priority' => $action->priority,
                                    'accepted_args' => $action->acceptedArgs,
                                    'defer' => $action->defer,
                                ];
                            }
                            /** @var ReflectionClass $attribute */
                            foreach ($method->getAttributes(Filter::class) as $attribute) {
                                $filter = $attribute->newInstance();
                                /** @var Filter $filter */
                                $registrations[] = [
                                    'class' => $className,
                                    'method' => $method->getName(),
                                    'type' => 'filter',
                                    'hook' => $filter->hook,
                                    'priority' => $filter->priority,
                                    'accepted_args' => $filter->acceptedArgs,
                                    'defer' => $filter->defer,
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::error('WPhooksScanner', 'Error scanning hooks for class ' . $className . ': ' . $e->getMessage());
                    }
                }
            }
        }

        return $registrations;
    }
}

/**
 * Lazy hook handler — invocable object that defers container resolution to hook-fire time.
 *
 * Unlike closures, this is a named class that appears in debugging tools
 * WordPress can match it by instance identity (spl_object_hash) for
 * remove_action()/remove_filter().
 */
class LazyHookHandler
{
    public readonly string $label;

    /**
     * @param ContainerInterface $container
     * @param class-string $class
     * @param string $method
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $class,
        private readonly string $method,
    ) {
        $this->label = $this->class . '::' . $this->method;
    }

    public function __invoke(mixed ...$args): mixed
    {
        try {
            $execute = $this->container->get($this->class)->{$this->method}(...$args);
            // SharedUtils::isDevelopment() && Logger::debug("LazyHookHandler", "Hook invoke {$this->label}");
            return $execute;
        } catch (\Throwable $e) {
            Logger::error('WPHooksRegistry', 'Error invoking hook for class ' . $this->class . ' and method ' . $this->method . ': ' . $e->getMessage());
            return array_key_exists(0, $args) ? $args[0] : null;
        }
    }
}

/**
 * Registry for WordPress hooks discovered via #[Action] and #[Filter] attributes.
 *
 * Stores all hook registrations as identifiable LazyHookHandler instances,
 * enabling unregistration by hook name, class, or specific class::method.
 * Service resolution is deferred to hook-fire time (lazy loading).
 *
 * @phpstan-import-type HookRegistration from \WPLokerBJM\Core\Container\Support\WPhooksScanner
 *
 * @phpstan-type HandlerEntry array{handler: LazyHookHandler, type: 'action'|'filter', priority: int, accepted_args: int}
 * @phpstan-type RemoveHandlerEntry array{handler: LazyHookHandler, type: 'action'|'filter', priority: int}
 */
class WPHooksRegistry
{
    /**
     * @var array<string, array<string, HandlerEntry>>
     */
    private array $handlers = [];

    /**
     * Deferred handlers not yet registered with WordPress.
     *
     * @var array<string, array<string, HandlerEntry>>
     */
    private array $deferredHandlers = [];

    private bool $initialized = false;

    /**
     * Summary of __construct
     * @param ContainerInterface $container
     * @param HookRegistration[] $hooksRegistration
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private array $hooksRegistration,
    ) {
    }

    /**
     * Register hook registrations from the scanner.
     * Pre-builds LazyHookHandler instances and validates container existence.
     *
     * @param HookRegistration[] $registrations
     */
    private function registerAll(array $registrations): void
    {
        foreach ($registrations as $reg) {
            if (!$this->container->has($reg['class'])) {
                Logger::warning(
                    'WPHooksRegistry',
                    'Skipping hook ' . $reg['hook']
                    . ' — class not in container: ' . $reg['class']
                );
                continue;
            }

            $handler = new LazyHookHandler(
                $this->container,
                $reg['class'],
                $reg['method'],
            );

            $key = $reg['class'] . '::' . $reg['method'] . '::' . $reg['priority'];
            $target = !empty($reg['defer'])
                ? 'deferredHandlers'
                : 'handlers';
            $this->{$target}[$reg['hook']][$key] = [
                'handler' => $handler,
                'type' => $reg['type'],
                'priority' => $reg['priority'],
                'accepted_args' => $reg['accepted_args'],
            ];
        }
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
                        'WPHooksRegistry',
                        "Error registering {$data['type']} '{$hook}' for {$key}: {$e->getMessage()}"
                    );
                }
            }
        }

        $this->initialized = true;
    }

    /**
     * Activate all deferred handlers registered for a specific WordPress hook.
     *
     * Moves matching LazyHookHandler instances from $this->deferredHandlers
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
        if (empty($this->deferredHandlers[$hook])) {
            return;
        }

        foreach ($this->deferredHandlers[$hook] as $key => $data) {
            // Guard: skip if already activated
            if (isset($this->handlers[$hook][$key])) {
                continue;
            }

            $this->handlers[$hook][$key] = $data;

            $this->addSingleHook($hook, $data);
        }

        unset($this->deferredHandlers[$hook]);
    }

    /**
     * Activate all deferred handlers belonging to a specific service class.
     *
     * Scans all deferred hooks and activates any LazyHookHandler whose owning
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
        $prefix = $class . '::';
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!str_starts_with($key, $prefix)) {
                    continue;
                }

                // Guard: skip if already activated
                if (isset($this->handlers[$hook][$key])) {
                    unset($hookHandlers[$key]);
                    continue;
                }

                $this->handlers[$hook][$key] = $data;

                $this->addSingleHook($hook, $data);

                unset($hookHandlers[$key]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Activate a specific deferred hook method on a service class.
     *
     * Targets a single method on a single class — the most granular activation.
     * Already-active handlers are silently skipped.
     *
     * **Example:**
     * ```php
     * $registry->activateDeferredByMethod(WPGraphQL::class, 'setCacheHeader');
     * ```
     * @template T of Object
     * @param class-string<T> $class  Fully qualified class name.
     * @param key-of<T, string> $method Method name to activate.
     */
    public function activateDeferredByMethod(string $class, string $method): void
    {
        $prefix = $class . '::' . $method . '::';
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!str_starts_with($key, $prefix)) {
                    continue;
                }

                // Guard: skip if already activated
                if (isset($this->handlers[$hook][$key])) {
                    unset($hookHandlers[$key]);
                    continue;
                }

                $this->handlers[$hook][$key] = $data;

                $this->addSingleHook($hook, $data);

                unset($hookHandlers[$key]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister all deferred handlers for a specific WordPress hook name.
     *
     * Removes deferred LazyHookHandler instances that were never registered
     * with WordPress — no remove_action/remove_filter needed.
     *
     ** types autocomplete DX generated by generate-meta-hooks.php
     * @param string $hook WordPress hook name (e.g. 'init', 'save_post', 'wp_robots').
     */
    public function unregisterDeferredByHook(string $hook): void
    {
        unset($this->deferredHandlers[$hook]);
    }

    /**
     * Unregister all deferred hooks belonging to a specific service class.
     *
     * Scans deferred handlers and removes every LazyHookHandler whose
     * owning class matches the given FQCN. Since these handlers were
     * never registered with WordPress, no remove_action/remove_filter
     * calls are needed — just internal cleanup.
     *
     * **Example:**
     * ```php
     * $registry->unregisterDeferredByClass(Cloudflare::class);
     * ```
     *
     * @param class-string $class Fully qualified class name.
     */
    public function unregisterDeferredByClass(string $class): void
    {
        $prefix = $class . '::';
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $_) {
                if (str_starts_with($key, $prefix)) {
                    unset($hookHandlers[$key]);
                }
            }
        }
    }

    /**
     * Unregister a specific deferred hook method on a service class.
     *
     * Targets a single method on a single class — the most granular
     * deferred unregistration. No WordPress cleanup needed since the
     * handler was never registered via add_action/add_filter.
     *
     * **Example:**
     * ```php
     * $registry->unregisterDeferredByMethod(Cloudflare::class, 'purgeCache');
     * // Only purgeCache deferred hooks are removed
     * ```
     * @template T of Object
     * @param class-string<T> $class  Fully qualified class name.
     * @param key-of<T, string> $method Method name to unregister.
     */
    public function unregisterDeferredByMethod(string $class, string $method): void
    {
        $prefix = $class . '::' . $method . '::';
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach (array_keys($hookHandlers) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset($hookHandlers[$key]);
                }
            }
        }
    }

    /**
     * Unregister all handlers for a specific WordPress hook name.
     *
     * Removes every LazyHookHandler that was registered for the given hook,
     * covering both actions and filters on that hook regardless of priority.
     * After this call, `add_action/add_filter` entries are removed from
     * WordPress and the internal handler map is cleaned up.
     *
     * **Example:**
     * ```php
     * $registry->unregisterByHook('save_post');
     * All save_post action and filter registrations are removed
     * ```
     ** types autocomplete DX generated by generate-meta-hooks.php
     * @param string $hook WordPress hook name (e.g. 'init', 'save_post', 'wp_robots').
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
     * Scans all registered hooks and removes every LazyHookHandler whose
     * owning class matches the given FQCN. Useful for disabling an entire
     * service in one call — e.g. during testing or conditional feature toggles.
     *
     * **Example:**
     * ```php
     * $registry->unregisterByClass(CacheInvalidationHooks::class);
     * All hooks from CacheInvalidationHooks are removed
     * ```
     *
     * @param class-string $class Fully qualified class name (e.g. 'WPLokerBJM\Core\CacheInvalidationHooks').
     */
    public function unregisterByClass(string $class): void
    {
        $prefix = $class . '::';
        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!str_starts_with($key, $prefix)) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Unregister a specific hook method on a service class.
     *
     * Targets a single method on a single class — the most granular unregistration.
     * Use this when you need to disable one specific hook without affecting other
     * hooks registered by the same service.
     *
     * **Example:**
     * ```php
     * $registry->unregisterByMethod(CacheInvalidationHooks::class, 'purgeCacheOnChange');
     * Only purgeCacheOnChange hooks are removed; other methods on the same service stay active
     * ```
     * @template T of Object
     * @param class-string<T> $class  Fully qualified class name.
     * @param key-of<T, string> $method Method name to activate.
     */
    public function unregisterByMethod(string $class, string $method): void
    {
        $prefix = $class . '::' . $method . '::';
        foreach ($this->handlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!str_starts_with($key, $prefix)) {
                    continue;
                }
                $this->removeSingleHook($hook, $data);
                unset($hookHandlers[$key]);
            }
        }
        unset($hookHandlers);
    }

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
}