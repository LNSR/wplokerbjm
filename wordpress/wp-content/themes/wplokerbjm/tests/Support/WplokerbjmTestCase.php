<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use PHPUnit\Framework\TestCase;
use \DI\Container;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookTargetResolver, WPHooksContainerRegistry, WPHooksInstanceRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;

abstract class WplokerbjmTestCase extends TestCase
{
    private static $mockCache = [];

    /**
     * When true, queued once-hook removals are flushed automatically at the
     * end of each simulated dispatch. This mirrors production, where the
     * removal queue is only swept on a later removal outside the hook's own
     * dispatch. Set to false in a test to observe the queued state directly.
     */
    protected bool $flushQueuedRemovalsAfterDispatch = true;

    /**
     * Registries created during the current test, used to flush queued
     * once-hook removals after a simulated dispatch completes.
     *
     * @var array<int,object>
     */
    private array $trackedRegistries = [];

    protected function setUp(): void
    {
        parent::setUp();

        ProxyContainer::boot();
        ProxyContainer::resetPerTest();

        // Reset mock cache per test
        self::$mockCache = [];

        // Initialize Brain Monkey
        \Brain\Monkey\setup();

        // Mock essential WordPress functions
        \Brain\Monkey\Functions\when('get_stylesheet_directory')->justReturn(dirname(__DIR__, 2));
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'] ?? 200;
        });
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return $response['body'] ?? '';
        });
        // Note: wp_remote_get and wp_remote_post are mocked per-test as needed to avoid conflicts
        \Brain\Monkey\Functions\when('register_rest_route')->justReturn(true);
        \Brain\Monkey\Functions\when('sanitize_text_field')->alias(function ($value) {
            return trim(strip_tags((string) $value));
        });
        \Brain\Monkey\Functions\when('wp_kses_post')->alias(function ($value) {
            return (string) $value;
        });
        \Brain\Monkey\Functions\when('get_stylesheet')->justReturn('wplokerbjm');
        \Brain\Monkey\Functions\when('is_admin')->justReturn(false);
        \Brain\Monkey\Functions\when('sanitize_email')->alias(function ($value) {
            return filter_var((string) $value, FILTER_SANITIZE_EMAIL);
        });
        \Brain\Monkey\Functions\when('esc_url_raw')->alias(function ($value) {
            return trim((string) $value);
        });
        \Brain\Monkey\Functions\when('wp_cache_get')->alias(function ($key, $group) {
            return self::$mockCache[$key] ?? false;
        });
        \Brain\Monkey\Functions\when('wp_cache_set')->alias(function ($key, $value, $group, $expiration) {
            self::$mockCache[$key] = $value;
            return true;
        });
        \Brain\Monkey\Functions\when('wp_cache_delete')->alias(function ($key, $group) {
            unset(self::$mockCache[$key]);
            return true;
        });

        $this->setupWordPressHookMocks();
    }

    /**
     * Mock WordPress hook registration and dispatch functions.
     *
     * Maintains a global registry of `add_action` / `add_filter` calls so tests
     * can assert which hooks were registered and with what callables.
     * `do_action` and `apply_filters` actually invoke the registered callables
     * (limited by their `acceptedArgs`) so the lazy resolution path can be
     * exercised end-to-end.
     *
     * @return void
     */
    protected function setupWordPressHookMocks(): void
    {
        $GLOBALS['__wplokerbjm_registered_hooks'] = [];
        $GLOBALS['__wplokerbjm_current_filter'] = [];

        \Brain\Monkey\Functions\when('add_action')->alias(function ($hook, $callable, $priority = 10, $acceptedArgs = 1) {
            $GLOBALS['__wplokerbjm_registered_hooks'][] = [
                'type' => 'action',
                'hook' => $hook,
                'callable' => $callable,
                'priority' => (int) $priority,
                'acceptedArgs' => (int) $acceptedArgs,
            ];
            return true;
        });

        \Brain\Monkey\Functions\when('add_filter')->alias(function ($hook, $callable, $priority = 10, $acceptedArgs = 1) {
            $GLOBALS['__wplokerbjm_registered_hooks'][] = [
                'type' => 'filter',
                'hook' => $hook,
                'callable' => $callable,
                'priority' => (int) $priority,
                'acceptedArgs' => (int) $acceptedArgs,
            ];
            return true;
        });

        \Brain\Monkey\Functions\when('do_action')->alias(function ($hook, ...$args) {
            $GLOBALS['__wplokerbjm_current_filter'][] = $hook;

            try {
                $callbacks = array_filter(
                    $GLOBALS['__wplokerbjm_registered_hooks'],
                    fn($reg) => $reg['type'] === 'action' && $reg['hook'] === $hook
                );

                // Sort by priority ascending
                usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

                foreach ($callbacks as $reg) {
                    $limited = array_slice($args, 0, $reg['acceptedArgs']);
                    ($reg['callable'])(...$limited);
                }
            } finally {
                array_pop($GLOBALS['__wplokerbjm_current_filter']);

                if ($this->flushQueuedRemovalsAfterDispatch) {
                    $this->flushQueuedRemovals();
                }
            }
        });

        \Brain\Monkey\Functions\when('apply_filters')->alias(function ($hook, $value, ...$args) {
            $GLOBALS['__wplokerbjm_current_filter'][] = $hook;

            try {
                $callbacks = array_filter(
                    $GLOBALS['__wplokerbjm_registered_hooks'],
                    fn($reg) => $reg['type'] === 'filter' && $reg['hook'] === $hook
                );

                // Sort by priority ascending
                usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

                foreach ($callbacks as $reg) {
                    $limited = array_slice([$value, ...$args], 0, $reg['acceptedArgs']);
                    $value = ($reg['callable'])(...$limited);
                }

                return $value;
            } finally {
                array_pop($GLOBALS['__wplokerbjm_current_filter']);

                if ($this->flushQueuedRemovalsAfterDispatch) {
                    $this->flushQueuedRemovals();
                }
            }
        });

        $removeHook = function ($type, $hook, $callable, $priority = 10) {
            foreach ($GLOBALS['__wplokerbjm_registered_hooks'] as $i => $reg) {
                if (
                    $reg['type'] === $type &&
                    $reg['hook'] === $hook &&
                    $reg['callable'] == $callable && // Loose comparison allows object array comparisons
                    $reg['priority'] === (int) $priority
                ) {
                    unset($GLOBALS['__wplokerbjm_registered_hooks'][$i]);
                    $GLOBALS['__wplokerbjm_registered_hooks'] = array_values($GLOBALS['__wplokerbjm_registered_hooks']);
                    return true;
                }
            }
            return false;
        };

        \Brain\Monkey\Functions\when('remove_action')->alias(fn($hook, $callable, $priority = 10) => $removeHook('action', $hook, $callable, $priority));
        \Brain\Monkey\Functions\when('remove_filter')->alias(fn($hook, $callable, $priority = 10) => $removeHook('filter', $hook, $callable, $priority));

        // In WordPress both `doing_action` and `doing_filter` read the same
        // `$wp_current_filter` stack, so mirror that here: the stack is pushed
        // by the dispatch mocks above, letting `stillDispatchingSameHook()`
        // behave faithfully during a hook's own dispatch.
        \Brain\Monkey\Functions\when('doing_action')->alias(
            static fn(string $hook): bool => in_array($hook, $GLOBALS['__wplokerbjm_current_filter'] ?? [], true)
        );
        \Brain\Monkey\Functions\when('doing_filter')->alias(
            static fn(string $hook): bool => in_array($hook, $GLOBALS['__wplokerbjm_current_filter'] ?? [], true)
        );
    }

    /**
     * Return the list of hooks registered via `add_action` / `add_filter`
     * during the current test.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function registeredHooks(): array
    {
        return $GLOBALS['__wplokerbjm_registered_hooks'] ?? [];
    }

    /**
     * Find the first registered hook matching the given type and name.
     *
     * @param string $type 'action' or 'filter'
     * @param string $hook Hook name
     * @return array<string,mixed>|null
     */
    protected function findRegisteredHook(string $type, string $hook): ?array
    {
        foreach ($this->registeredHooks() as $reg) {
            if ($reg['type'] === $type && $reg['hook'] === $hook) {
                return $reg;
            }
        }
        return null;
    }

    /**
     * Track a registry so its queued once-hook removals can be flushed after
     * a simulated dispatch completes.
     */
    protected function trackRegistry(WPHooksContainerRegistry|WPHooksInstanceRegistry $registry): void
    {
        if (!in_array($registry, $this->trackedRegistries, true)) {
            $this->trackedRegistries[] = $registry;
        }
    }

    /**
     * Flush queued once-hook removals for the given registries (defaults to
     * every registry tracked in the current test).
     *
     * Emulates production behaviour: a queued once-hook removal is only
     * applied to WordPress once its hook is no longer dispatching. During
     * dispatch the removal stays queued so `WP_Hook`'s iteration is not
     * corrupted (see WordPress ticket #61263) - this is why same-hook once
     * entries linger until a later removal sweeps them.
     *
     * @param array<int,WPHooksContainerRegistry&WPHooksInstanceRegistry>|null $registries
     */
    protected function flushQueuedRemovals(?array $registries = null): void
    {
        foreach ($registries ?? $this->trackedRegistries as $registry) {
            $queued = $registry->queuedRemovalEntry ?? [];

            foreach ($queued as $hook => $entries) {
                if (\doing_action($hook) || \doing_filter($hook)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    $entry->unregister();
                }
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up Brain Monkey mocks
        \Brain\Monkey\tearDown();

        parent::tearDown();
        $dir = \dirname(__DIR__, 1);
        $cacheDir = $dir . '/cache';
        if (is_dir($cacheDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $fileInfo) {
                $todo = $fileInfo->isDir() ? 'rmdir' : 'unlink';
                $todo($fileInfo->getRealPath());
            }

            rmdir($cacheDir);
        }
    }

    protected function container(): Container
    {
        return ProxyContainer::container();
    }

    /**
     * Central factory for building the container-backed hook registry.
     *
     * Holds the full collaborator wiring (plan provider, deferred-hook
     * manager, hook-target resolver) so new constructor parameters only
     * ever need to be added here — not at every test construction site.
     */
    protected function createRegistry(array $registrations, ?ContainerInterface $container = null): WPHooksContainerRegistry
    {
        $container ??= $this->container();
        $planProvider = new WPHookPlanProvider();
        $resolver = new HookTargetResolver();
        $entriesIndexer = new EntriesIndexer();

        $registry = new WPHooksContainerRegistry(
            $container,
            $registrations,
            $planProvider,
            new DeferredHookManager($planProvider, $container, $resolver, $entriesIndexer),
            $resolver,
            $entriesIndexer
        );

        $this->trackRegistry($registry);

        return $registry;
    }
}
