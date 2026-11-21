<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Deferral;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\InstanceRegistryDeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterLiveService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the bookkeeping released with a DEFERRED entry.
 *
 * Releasing a deferred entry (explicit `unregisterDeferredBy*`,
 * `unregisterHooksOn()` or owner garbage collection) destroys
 * {@see \WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler},
 * whose destructor must:
 *  - remove the container `EntriesIndexer` buckets (hook/class/callable/namespace/tag)
 *    when the entry came from the container manager,
 *  - remove the instance manager's owner bucket through its WeakMap,
 *  - remove the automatic `deferRegisterUntilHook` scheduler registered at
 *    `PHP_INT_MIN`, so the trigger never fires a removed entry,
 *  - and keep the destination hook unregistered.
 * @Note: No explicit \gc_collect_cycles() called otherwise circular reference may goes unnoticed.
 */
final class DeferredReleaseCleanupTest extends WplokerbjmTestCase
{
    public function testContainerDeferredUnregisterByHookRemovesSchedulerAndIndexes(): void
    {
        $registry = $this->createRegistry([$this->deferredRegistration()]);
        $registry->initialize();

        $manager = $this->privateValue($registry, 'containerRegistryDeferredHookManager');
        $indexer = $this->privateValue($manager, 'entriesIndexer');

        self::assertCount(1, $this->privateValue($manager, 'deferredHandlers'));
        self::assertNotEmpty($this->schedulerCallbacks('release_trigger'));
        self::assertArrayHasKey('release_destination', $indexer->byHook);
        self::assertArrayHasKey('release_tag', $indexer->byTag);

        $registry->unregisterDeferredByHook('release_destination');

        $this->assertDeferredPoolAndIndexesAreEmpty($manager, $indexer);
        self::assertSame([], $this->schedulerCallbacks('release_trigger'));
        self::assertSame(0, $registry->activateDeferredByTags(['release_tag']), 'a stale tag index must not resolve');

        \do_action('release_trigger');
        self::assertNull($this->findRegisteredHook('action', 'release_destination'));
    }

    public function testContainerDeferredUnregisterByTagsRemovesSchedulerAndIndexes(): void
    {
        $registry = $this->createRegistry([$this->deferredRegistration()]);
        $registry->initialize();

        $manager = $this->privateValue($registry, 'containerRegistryDeferredHookManager');
        $indexer = $this->privateValue($manager, 'entriesIndexer');

        $registry->unregisterDeferredByTags(['release_tag']);

        $this->assertDeferredPoolAndIndexesAreEmpty($manager, $indexer);
        self::assertSame([], $this->schedulerCallbacks('release_trigger'));
        self::assertSame(0, $registry->activateDeferredByTags(['release_tag']));
    }

    public function testContainerDeferredUnregisterByCallableRemovesSchedulerAndIndexes(): void
    {
        $registry = $this->createRegistry([$this->deferredRegistration()]);
        $registry->initialize();

        $manager = $this->privateValue($registry, 'containerRegistryDeferredHookManager');
        $indexer = $this->privateValue($manager, 'entriesIndexer');

        $registry->unregisterDeferredByCallable([OnUnregisterLiveService::class, 'onAlpha']);

        $this->assertDeferredPoolAndIndexesAreEmpty($manager, $indexer);
        self::assertSame([], $this->schedulerCallbacks('release_trigger'));
    }

    public function testInstanceDeferredUnregisterByCallableRemovesSchedulerAndIndexes(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('inst_release_destination', deferRegisterUntilHook: 'inst_release_trigger')]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        $manager = $this->privateValue($registry, 'instanceRegistryDeferredHookManager');

        self::assertCount(1, $this->privateValue($manager, 'deferredHandlers')[$service] ?? []);
        self::assertNotEmpty($this->schedulerCallbacks('inst_release_trigger'));

        $removed = $registry->unregisterDeferredByCallableInLiveInstance([$service, 'onDeferred']);

        self::assertSame(1, $removed);

        self::assertCount(0, $this->privateValue($manager, 'deferredHandlers'));
        self::assertSame([], $this->schedulerCallbacks('inst_release_trigger'));
        self::assertSame(0, $registry->activateDeferredByCallableInLiveInstance([$service, 'onDeferred']));

        \do_action('inst_release_trigger');
        self::assertNull($this->findRegisteredHook('action', 'inst_release_destination'));
    }

    public function testInstanceHeldDeferredReleaseRemovesIndexesWithoutScheduler(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('inst_held_destination', deferRegister: true)]
            public function onHeld(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        $manager = $this->privateValue($registry, 'instanceRegistryDeferredHookManager');

        self::assertCount(1, $this->privateValue($manager, 'deferredHandlers')[$service] ?? []);
        self::assertSame([], $this->schedulerCallbacks('inst_held_destination'), 'a trigger-less entry has no scheduler');

        $removed = $registry->unregisterDeferredByCallableInLiveInstance([$service, 'onHeld']);

        self::assertSame(1, $removed);

        self::assertCount(0, $this->privateValue($manager, 'deferredHandlers'));
        self::assertSame(0, $registry->activateDeferredByCallableInLiveInstance([$service, 'onHeld']));
    }

    public function testUnregisterHooksOnReleasesDeferredSchedulerAndIndexes(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('inst_owner_release_destination', deferRegisterUntilHook: 'inst_owner_release_trigger')]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        $manager = $this->privateValue($registry, 'instanceRegistryDeferredHookManager');
        self::assertCount(1, $this->privateValue($manager, 'deferredHandlers')[$service] ?? []);

        $registry->unregisterHooksOn($service);

        self::assertCount(0, $this->privateValue($manager, 'deferredHandlers'));
        self::assertSame([], $this->schedulerCallbacks('inst_owner_release_trigger'));
    }

    public function testOwnerGarbageCollectionReleasesDeferredSchedulerAndEntryImmediately(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('inst_gc_release_destination', deferRegisterUntilHook: 'inst_gc_release_trigger')]
            public function onDeferred(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        $manager = $this->privateValue($registry, 'instanceRegistryDeferredHookManager');
        self::assertCount(1, $this->privateValue($manager, 'deferredHandlers')[$service] ?? []);
        self::assertNotEmpty($this->schedulerCallbacks('inst_gc_release_trigger'));

        unset($service);

        self::assertCount(0, $this->privateValue($manager, 'deferredHandlers'), 'the WeakMap owner bucket must disappear with the owner');
        self::assertSame([], $this->schedulerCallbacks('inst_gc_release_trigger'));

        \do_action('inst_gc_release_trigger');

        self::assertCount(0, $this->privateValue($manager, 'deferredHandlers'));
        self::assertSame([], $this->schedulerCallbacks('inst_gc_release_trigger'));
        self::assertNull($this->findRegisteredHook('action', 'inst_gc_release_destination'));
    }

    // ── helpers ─────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function deferredRegistration(): array
    {
        return [
            'class' => OnUnregisterLiveService::class,
            'method' => 'onAlpha',
            'type' => 'action',
            'hook' => 'release_destination',
            'priority' => 10,
            'acceptedArgs' => 1,
            'deferRegisterUntilHook' => 'release_trigger',
            'tags' => ['release_tag'],
        ];
    }

    private function instanceRegistry(): WPHooksInstanceRegistry
    {
        // Build the collaborators directly: the DI container shares one
        // InstanceRegistryDeferredHookManager singleton, so using make() would
        // leak deferred entries from earlier test cases into this pool.
        $container = $this->container();
        $provider = $container->get(WPHookPlanProvider::class);
        $cleaner = new QueuedHooksCleaner();
        $this->queuedHooksCleaner = $cleaner;

        return new WPHooksInstanceRegistry(
            new InstanceRegistryDeferredHookManager($provider),
            $provider,
            $cleaner,
            new InstanceRegistryRuntimeHookResolver(),
            new WPHooksInstanceObjectCache(),
        );
    }

    private function privateValue(object $object, string $property): mixed
    {
        return (new \ReflectionProperty($object, $property))->getValue($object);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function schedulerCallbacks(string $hook): array
    {
        $wpHook = $GLOBALS['wp_filter'][$hook] ?? null;

        return $wpHook instanceof \WP_Hook ? ($wpHook->callbacks[\PHP_INT_MIN] ?? []) : [];
    }

    private function assertDeferredPoolAndIndexesAreEmpty(object $manager, object $indexer): void
    {
        self::assertSame([], $this->privateValue($manager, 'deferredHandlers'));
        self::assertSame([], $indexer->byHook, 'byHook must not keep a released entry');
        self::assertSame([], $indexer->byClass, 'byClass must not keep a released entry');
        self::assertSame([], $indexer->byCallable, 'byCallable must not keep a released entry');
        self::assertSame([], $indexer->byNamespace, 'byNamespace must not keep a released entry');
        self::assertSame([], $indexer->byTag, 'byTag must not keep a released entry');
    }
}
