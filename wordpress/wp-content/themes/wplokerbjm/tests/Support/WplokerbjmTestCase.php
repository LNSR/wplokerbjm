<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use PHPUnit\Framework\TestCase;
use \DI\Container;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookTargetResolver, WPHooksContainerRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;

abstract class WplokerbjmTestCase extends TestCase
{
    private static $mockCache = [];

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
     * (limited by their `accepted_args`) so the lazy resolution path can be
     * exercised end-to-end.
     *
     * @return void
     */
    protected function setupWordPressHookMocks(): void
{
    $GLOBALS['__wplokerbjm_registered_hooks'] = [];

    \Brain\Monkey\Functions\when('add_action')->alias(function ($hook, $callable, $priority = 10, $accepted_args = 1) {
        $GLOBALS['__wplokerbjm_registered_hooks'][] = [
            'type'          => 'action',
            'hook'          => $hook,
            'callable'      => $callable,
            'priority'      => (int) $priority,
            'accepted_args' => (int) $accepted_args,
        ];
        return true;
    });

    \Brain\Monkey\Functions\when('add_filter')->alias(function ($hook, $callable, $priority = 10, $accepted_args = 1) {
        $GLOBALS['__wplokerbjm_registered_hooks'][] = [
            'type'          => 'filter',
            'hook'          => $hook,
            'callable'      => $callable,
            'priority'      => (int) $priority,
            'accepted_args' => (int) $accepted_args,
        ];
        return true;
    });

    \Brain\Monkey\Functions\when('do_action')->alias(function ($hook, ...$args) {
        $callbacks = array_filter(
            $GLOBALS['__wplokerbjm_registered_hooks'],
            fn($reg) => $reg['type'] === 'action' && $reg['hook'] === $hook
        );

        // Sort by priority ascending
        usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($callbacks as $reg) {
            $limited = array_slice($args, 0, $reg['accepted_args']);
            ($reg['callable'])(...$limited);
        }
    });

    \Brain\Monkey\Functions\when('apply_filters')->alias(function ($hook, $value, ...$args) {
        $callbacks = array_filter(
            $GLOBALS['__wplokerbjm_registered_hooks'],
            fn($reg) => $reg['type'] === 'filter' && $reg['hook'] === $hook
        );

        // Sort by priority ascending
        usort($callbacks, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($callbacks as $reg) {
            $limited = array_slice([$value, ...$args], 0, $reg['accepted_args']);
            $value = ($reg['callable'])(...$limited);
        }

        return $value;
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

    protected function tearDown(): void
    {
        // Clean up Brain Monkey mocks
        \Brain\Monkey\tearDown();

        parent::tearDown();
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

        return new WPHooksContainerRegistry(
            $container,
            $registrations,
            $planProvider,
            new DeferredHookManager($planProvider, $container, $resolver),
            $resolver,
        );
    }
}
