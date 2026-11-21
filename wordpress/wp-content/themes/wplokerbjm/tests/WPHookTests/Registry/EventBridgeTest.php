<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Registry;

use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\{ContainerRegistryDeferredHookManager, InstanceRegistryDeferredHookManager};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\{ContainerRegistryHookTargetResolver, InstanceRegistryRuntimeHookResolver};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the control-plane event bridges moved into the registries.
 *
 * The selector APIs (`activateDeferredBy*`, `unregisterDeferredBy*`,
 * `unregisterBy*`, runtime equivalents) are reachable through raw WordPress
 * events so application code can drive the registries without injecting them.
 * The bridges themselves must be:
 *  - registered exactly once (idempotent boot),
 *  - plain array callables (removable, not first-class closures),
 *  - outside the registry handler pools, so selector sweeps cannot remove them.
 */
final class EventBridgeTest extends WplokerbjmTestCase
{
    public function testContainerBridgeIsBootedOnceAndActivatesDeferredEntries(): void
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $container = $builder->build();

        $planProvider = new WPHookPlanProvider($container);
        $targetResolver = new ContainerRegistryHookTargetResolver();
        $entriesIndexer = new EntriesIndexer();
        $deferredManager = new ContainerRegistryDeferredHookManager($planProvider, $targetResolver, $entriesIndexer);
        $registry = new WPHooksContainerRegistry(
            $container,
            [],
            $planProvider,
            $deferredManager,
            $targetResolver,
            $this->queuedHooksCleaner = new QueuedHooksCleaner(),
            $entriesIndexer,
        );

        $registerAll = \Closure::bind(
            static fn(WPHooksContainerRegistry $r, array $registrations): mixed => $r->registerAll($registrations),
            null,
            WPHooksContainerRegistry::class,
        );
        $registerAll($registry, [[
            'class' => self::class,
            'method' => 'deferredBridgeTarget',
            'type' => 'action',
            'hook' => 'event_bridge_target',
            'priority' => 10,
            'acceptedArgs' => 1,
            'deferRegister' => true,
        ]]);

        $registry->initialize();
        $registry->initialize(); // idempotent boot — no duplicate listeners

        foreach (\array_keys(WPHooksContainerRegistry::EVENT_BRIDGE_HOOKS) as $bridgeHook) {
            self::assertCount(
                1,
                $GLOBALS['wp_filter'][$bridgeHook]->callbacks[10] ?? [],
                "Container bridge [$bridgeHook] must be registered exactly once",
            );
        }

        self::assertNull($this->findRegisteredHook('action', 'event_bridge_target'));

        \do_action(WPHooksContainerRegistry::ACTIVATE_DEFERRED_BY_CLASS, self::class);

        self::assertNotNull($this->findRegisteredHook('action', 'event_bridge_target'));

        // Control plane: sweeping the registry class must not remove the bridge.
        \do_action(WPHooksContainerRegistry::UNREGISTER_BY_CLASS, WPHooksContainerRegistry::class);
        self::assertCount(
            1,
            $GLOBALS['wp_filter'][WPHooksContainerRegistry::UNREGISTER_BY_CLASS]->callbacks[10] ?? [],
            'The bridge must stay outside the registry handler pools',
        );

        // Reversible toggle: remove clears the flag, init boots it again.
        $registry->removeEventBridge();
        foreach (\array_keys(WPHooksContainerRegistry::EVENT_BRIDGE_HOOKS) as $bridgeHook) {
            self::assertArrayNotHasKey($bridgeHook, $GLOBALS['wp_filter'] ?? [], "Container bridge [$bridgeHook] must be removed");
        }

        $registry->initEventBridge();
        foreach (\array_keys(WPHooksContainerRegistry::EVENT_BRIDGE_HOOKS) as $bridgeHook) {
            self::assertCount(1, $GLOBALS['wp_filter'][$bridgeHook]->callbacks[10] ?? [], "Container bridge [$bridgeHook] must be re-registered");
        }
    }

    public function testInstanceBridgeBootsOnFirstRegistrationAndUnregistersThroughTheEvent(): void
    {
        $container = $this->container();
        $provider = new WPHookPlanProvider($container);

        $registry = new WPHooksInstanceRegistry(
            new InstanceRegistryDeferredHookManager($provider, new InstanceRegistryRuntimeHookResolver(), new EntriesIndexer()),
            $provider,
            $this->queuedHooksCleaner = new QueuedHooksCleaner(),
            new InstanceRegistryRuntimeHookResolver(),
            new WPHooksInstanceObjectCache(),
        );

        $service = new class {
            #[Action('event_bridge_runtime_hook', 10)]
            public function onHook(): void {}
        };

        $registry->initEventBridge();
        \do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $service);

        foreach (\array_keys(WPHooksInstanceRegistry::EVENT_BRIDGE_HOOKS) as $bridgeHook) {
            self::assertCount(
                1,
                $GLOBALS['wp_filter'][$bridgeHook]->callbacks[10] ?? [],
                "Runtime bridge [$bridgeHook] must be registered exactly once",
            );
        }

        self::assertNotNull($this->findRegisteredHook('action', 'event_bridge_runtime_hook'));

        \do_action(WPHooksInstanceRegistry::UNREGISTER_HOOKS, $service);

        self::assertNull($this->findRegisteredHook('action', 'event_bridge_runtime_hook'));

        // Reversible toggle: remove clears the flag, init boots it again.
        $registry->removeEventBridge();
        foreach (\array_keys(WPHooksInstanceRegistry::EVENT_BRIDGE_HOOKS) as $bridgeHook) {
            self::assertArrayNotHasKey($bridgeHook, $GLOBALS['wp_filter'] ?? [], "Runtime bridge [$bridgeHook] must be removed");
        }

        $registry->initEventBridge();
        foreach (\array_keys(WPHooksInstanceRegistry::EVENT_BRIDGE_HOOKS) as $bridgeHook) {
            self::assertCount(1, $GLOBALS['wp_filter'][$bridgeHook]->callbacks[10] ?? [], "Runtime bridge [$bridgeHook] must be re-registered");
        }
    }

    public function testInstanceClassDeferredEventsDriveTheRegistry(): void
    {
        $container = $this->container();
        $provider = new WPHookPlanProvider($container);

        $registry = new WPHooksInstanceRegistry(
            new InstanceRegistryDeferredHookManager($provider, new InstanceRegistryRuntimeHookResolver(), new EntriesIndexer()),
            $provider,
            $this->queuedHooksCleaner = new QueuedHooksCleaner(),
            new InstanceRegistryRuntimeHookResolver(),
            new WPHooksInstanceObjectCache(),
        );
        $registry->initEventBridge();

        $activatee = new class {
            public int $runs = 0;

            #[Action('event_bridge_class_activate_hook', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $dropped = new class {
            public int $runs = 0;

            #[Action('event_bridge_class_drop_hook', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        \do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $activatee);
        \do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $dropped);

        self::assertNull($this->findRegisteredHook('action', 'event_bridge_class_activate_hook'));
        self::assertNull($this->findRegisteredHook('action', 'event_bridge_class_drop_hook'));

        \do_action(WPHooksInstanceRegistry::ACTIVATE_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE, $activatee);
        \do_action(WPHooksInstanceRegistry::UNREGISTER_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE, $dropped);

        self::assertNotNull($this->findRegisteredHook('action', 'event_bridge_class_activate_hook'));
        self::assertNull($this->findRegisteredHook('action', 'event_bridge_class_drop_hook'));

        \do_action('event_bridge_class_activate_hook');
        \do_action('event_bridge_class_drop_hook');

        self::assertSame(1, $activatee->runs);
        self::assertSame(0, $dropped->runs);
    }

    public function testInstanceHookEventsDriveDeferredAndActiveSelectors(): void
    {
        $container = $this->container();
        $provider = new WPHookPlanProvider($container);

        $registry = new WPHooksInstanceRegistry(
            new InstanceRegistryDeferredHookManager($provider, new InstanceRegistryRuntimeHookResolver(), new EntriesIndexer()),
            $provider,
            $this->queuedHooksCleaner = new QueuedHooksCleaner(),
            new InstanceRegistryRuntimeHookResolver(),
            new WPHooksInstanceObjectCache(),
        );
        $registry->initEventBridge();

        $activatee = new class {
            public int $runs = 0;

            #[Action('event_bridge_hook_activate', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $dropped = new class {
            public int $runs = 0;

            #[Action('event_bridge_hook_drop', 10, deferRegister: true)]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $removed = new class {
            public int $runs = 0;

            #[Action('event_bridge_hook_remove', 10)]
            public function onActive(): void
            {
                $this->runs++;
            }
        };

        \do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $activatee);
        \do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $dropped);
        \do_action(WPHooksInstanceRegistry::REGISTER_HOOKS, $removed);

        self::assertNull($this->findRegisteredHook('action', 'event_bridge_hook_activate'));
        self::assertNull($this->findRegisteredHook('action', 'event_bridge_hook_drop'));
        self::assertNotNull($this->findRegisteredHook('action', 'event_bridge_hook_remove'));

        \do_action(
            WPHooksInstanceRegistry::ACTIVATE_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE,
            $activatee,
            'event_bridge_hook_activate',
        );
        \do_action(
            WPHooksInstanceRegistry::UNREGISTER_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE,
            $dropped,
            'event_bridge_hook_drop',
        );
        \do_action(
            WPHooksInstanceRegistry::UNREGISTER_BY_HOOK_IN_LIVE_INSTANCE,
            $removed,
            'event_bridge_hook_remove',
        );

        self::assertNotNull($this->findRegisteredHook('action', 'event_bridge_hook_activate'));
        self::assertNull($this->findRegisteredHook('action', 'event_bridge_hook_drop'));
        self::assertNull($this->findRegisteredHook('action', 'event_bridge_hook_remove'));

        \do_action('event_bridge_hook_activate');
        self::assertSame(1, $activatee->runs);
        self::assertSame(0, $dropped->runs);
    }

    public function deferredBridgeTarget(): void {}
}
