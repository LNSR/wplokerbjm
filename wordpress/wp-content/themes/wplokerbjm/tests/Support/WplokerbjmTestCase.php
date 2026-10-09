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
     * When true the hook-inspection helpers flush queued once-hook removals
     * before reading WordPress state, so assertions observe the settled
     * (post-removal) registrations. Set to false to inspect the raw queue
     * while entries are still deferred — e.g. to assert the queued state and
     * then flush it explicitly with {@see flushQueuedRemovals()}.
     */
    protected bool $flushQueuedRemovalsAfterDispatch = true;

    /**
     * Registries created during the current test, used to flush queued
     * once-hook removals.
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
     * Reset the real WordPress hook engine globals for the current test.
     *
     * The engine itself (`add_action` / `add_filter` / `do_action` /
     * `apply_filters` / `remove_action` / ... / WP_Hook) is loaded from
     * `wp-includes/plugin.php` in tests/bootstrap.php — no Brain Monkey
     * function mocks are used for hooks. Only its state needs resetting,
     * because PHPUnit runs every test in the same process.
     *
     * @return void
     */
    protected function setupWordPressHookMocks(): void
    {
        $GLOBALS['wp_filter'] = [];
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['wp_current_filter'] = [];
    }

    /**
     * Return the hooks currently registered in WordPress, flattened across
     * every hook name and priority.
     *
     * Each entry exposes `hook`, `callable`, `priority` and `acceptedArgs`.
     * WordPress does not record whether a hook was registered via
     * `add_action` or `add_filter` (both use the same engine), so no `type`
     * key is provided.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function registeredHooks(): array
    {
        if ($this->flushQueuedRemovalsAfterDispatch) {
            $this->flushQueuedRemovals();
        }

        $hooks = [];

        foreach ($GLOBALS['wp_filter'] ?? [] as $hookName => $wpHook) {
            if (!$wpHook instanceof \WP_Hook) {
                continue;
            }

            foreach ($wpHook->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $hooks[] = [
                        'hook' => $hookName,
                        'callable' => $callback['function'],
                        'priority' => (int) $priority,
                        'acceptedArgs' => (int) ($callback['accepted_args'] ?? 1),
                    ];
                }
            }
        }

        return $hooks;
    }

    /**
     * Find the first registered callback for the given hook name.
     *
     * The `$type` parameter is kept for call-site compatibility; the real
     * WordPress engine does not distinguish actions from filters.
     *
     * @param string $type 'action' or 'filter' (ignored)
     * @param string $hook Hook name
     * @return array<string,mixed>|null
     */
    protected function findRegisteredHook(string $type, string $hook): ?array
    {
        if ($this->flushQueuedRemovalsAfterDispatch) {
            $this->flushQueuedRemovals();
        }

        foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                return [
                    'hook' => $hook,
                    'callable' => $callback['function'],
                    'priority' => (int) $priority,
                    'acceptedArgs' => (int) ($callback['accepted_args'] ?? 1),
                ];
            }
        }

        return null;
    }

    /**
     * Track a registry so its queued once-hook removals can be flushed.
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
