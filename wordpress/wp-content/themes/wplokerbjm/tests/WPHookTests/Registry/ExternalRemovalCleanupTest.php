<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Registry;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\InstanceRegistryDeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterLiveService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the registry bookkeeping side of EXTERNAL WordPress removal.
 *
 * `remove_all_actions()` / `remove_all_filters()` drop the last strong
 * reference to the invoker; its destructor must run the registry cleanup
 * callback so the registry forgets the entry completely:
 *  - active `$handlers` / `$weakRegistry` records,
 *  - `$entriesByInvokers` weak maps,
 *  - `QueuedHooksCleaner` once-entry tracking,
 *  - `EntriesIndexer` hook/class/callable/namespace/tag buckets,
 *  - and the `onUnregisterDo` side effect.
 ** Note: No explicit \gc_collect_cycles() called. Circular reference may goes unnoticed.
 */
final class ExternalRemovalCleanupTest extends WplokerbjmTestCase
{
    public function testRemoveAllActionsCleansContainerRegistryBookkeeping(): void
    {
        $registry = $this->createRegistry([
            $this->registration('external_hook', 'onAlpha', 10),
            $this->registration('external_hook', 'onBeta', 20),
            $this->registration('external_hook', 'onAlpha', 30, ['once' => true]),
        ]);
        $registry->initialize();

        $indexer = $this->privateValue($registry, 'entriesIndexer');

        self::assertCount(3, $this->privateValue($registry, 'handlers'));
        self::assertCount(1, $this->privateValue($this->queuedHooksCleaner, 'entries'), 'the once entry is tracked by the cleaner');
        self::assertArrayHasKey('external_hook', $indexer->byHook);

        $hook = $this->findRegisteredHook('action', 'external_hook');
        self::assertNotNull($hook);
        $reference = \WeakReference::create($hook['callable']);
        unset($hook);

        \remove_all_actions('external_hook');

        self::assertNull($reference->get(), 'WordPress must be the sole strong owner of active invokers');
        self::assertSame([], $this->privateValue($registry, 'handlers'));
        self::assertCount(0, $this->privateValue($registry, 'entriesByInvokers'));
        self::assertCount(0, $this->privateValue($this->queuedHooksCleaner, 'entries'));
        self::assertSame([], $indexer->byHook);
        self::assertSame([], $indexer->byClass);
        self::assertSame([], $indexer->byCallable);
        self::assertSame([], $indexer->byNamespace);
        self::assertSame([], $indexer->byTag);

        // Selectors are idempotent no-ops once the bookkeeping is gone.
        $registry->unregisterByHook('external_hook');
        self::assertSame([], $this->privateValue($registry, 'handlers'));
    }

    public function testRemoveAllFiltersCleansContainerRegistryBookkeeping(): void
    {
        $registry = $this->createRegistry([
            $this->registration('external_filter', 'onAlpha', 10, ['type' => 'filter']),
        ]);
        $registry->initialize();

        $indexer = $this->privateValue($registry, 'entriesIndexer');
        self::assertNotNull($this->findRegisteredHook('filter', 'external_filter'));
        self::assertArrayHasKey('external_filter', $indexer->byHook);

        \remove_all_filters('external_filter');

        self::assertNull($this->findRegisteredHook('filter', 'external_filter'));
        self::assertSame([], $this->privateValue($registry, 'handlers'));
        self::assertCount(0, $this->privateValue($registry, 'entriesByInvokers'));
        self::assertSame([], $indexer->byHook);
        self::assertSame([], $indexer->byClass);
        self::assertSame([], $indexer->byNamespace);
    }

    public function testRemoveAllActionsRunsOnUnregisterDoCleanup(): void
    {
        $cleanups = 0;

        $registry = $this->createRegistry([
            $this->registration('external_cleanup', 'onAlpha', 10, [
                'onUnregisterDo' => static function () use (&$cleanups): void {
                    $cleanups++;
                },
            ]),
        ]);
        $registry->initialize();

        \remove_all_actions('external_cleanup');

        self::assertSame(1, $cleanups, 'external removal must run the cleanup exactly once');
    }

    public function testUnsetContainerRegistryRemoveWordpressBookkeeping(): void
    {
        $registry = $this->createRegistry([
            $this->registration('external_cleanup', 'onAlpha', 10)
        ]);
        $registerRef = \WeakReference::create($registry);
        $registry->initialize();
        self::assertNotNull($this->findRegisteredHook('action', 'external_cleanup'), 'the registry must register all hook when initialized');
        unset($registry);
        self::assertNull($registerRef->get(), 'the registry reference must be null');
        self::assertNull($this->findRegisteredHook('action', 'external_cleanup'), 'the registry must unregister all hooks when unset');
    }
    public function testUnsetInstanceRegistryRemoveWordpressBookkeeping(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('external_instance_hook', 10)]
            #[Action('external_instance_hook', 20)]
            public function onHook(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->instanceRegistry();
        $registerRef = \WeakReference::create($registry);
        $registry->registerHooksOn($service);
        self::assertNotNull($this->findRegisteredHook('action', 'external_instance_hook'), 'the registry must register all hook in instance');
        unset($registry);
        self::assertNull($registerRef->get(), 'the registry reference must be null');
        self::assertNull($this->findRegisteredHook('action', 'external_instance_hook'), 'the registry must unregister all hooks when unset');
    }

    public function testRemoveAllActionsCleansInstanceRegistryBookkeeping(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('external_instance_hook', 10)]
            #[Action('external_instance_hook', 20)]
            public function onHook(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertCount(2, $this->privateValue($registry, 'weakRegistry')[$service] ?? []);
        self::assertCount(2, $this->privateValue($registry, 'entriesByInvokers'));

        $hook = $this->findRegisteredHook('action', 'external_instance_hook');
        self::assertNotNull($hook);
        $reference = \WeakReference::create($hook['callable']);
        unset($hook);

        \remove_all_actions('external_instance_hook');

        self::assertNull($reference->get());
        self::assertSame([], $this->privateValue($registry, 'weakRegistry')[$service] ?? []);
        self::assertCount(0, $this->privateValue($registry, 'entriesByInvokers'));
        self::assertCount(0, $this->privateValue($this->queuedHooksCleaner, 'entries'));

        // Idempotent: the owner has no records left to release.
        $registry->unregisterHooksOn($service);
    }

    public function testRemoveAllActionsCleansPropertyHookBookkeeping(): void
    {
        $service = new class {
            public int $runs = 0;

            #[Action('external_property_hook', 10)]
            public \Closure $handler {
                get => $this->handler ??= function (): void {
                    $this->runs++;
                };
            }
        };

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        $handlers = $this->privateValue($registry, 'weakRegistry')[$service] ?? [];
        self::assertCount(1, $handlers);

        \remove_all_actions('external_property_hook');

        self::assertSame([], $this->privateValue($registry, 'weakRegistry')[$service] ?? []);
        self::assertCount(0, $this->privateValue($registry, 'entriesByInvokers'));
    }

    public function testOwnerGoneCleanInstanceRegistryBookkeeping(): void
    { {
            $service = new class {
                public int $runs = 0;

                #[Action('external_instance_hook', 10)]
                #[Action('external_instance_hook', 20)]
                public function onHook(): void
                {
                    $this->runs++;
                }
                #[Action('external_instance_hook_1', 10)]
                #[Action('external_instance_hook_1', 20)]
                public function onHook1(): void
                {
                    $this->runs++;
                }
            };

            $registry = $this->instanceRegistry();
            $registry->registerHooksOn($service);

            self::assertCount(4, $this->privateValue($registry, 'weakRegistry')[$service] ?? []);
            self::assertCount(4, $this->privateValue($registry, 'entriesByInvokers'));

            self::assertNotNull($this->findRegisteredHook('action', 'external_instance_hook'));

            unset($service);

            self::assertNull($this->findRegisteredHook('action', 'external_instance_hook'));
            self::assertCount(0, $this->privateValue($registry, 'weakRegistry'));
            self::assertCount(0, $this->privateValue($registry, 'entriesByInvokers'));
            self::assertCount(0, $this->privateValue($this->queuedHooksCleaner, 'entries'));
        }
    }

    // ── helpers ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function registration(string $hook, string $method, int $priority, array $extra = []): array
    {
        return [
            'class' => OnUnregisterLiveService::class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'acceptedArgs' => 1,
            ...$extra,
        ];
    }

    private function instanceRegistry(): WPHooksInstanceRegistry
    {
        // Direct construction — the DI container shares one deferred manager
        // singleton, which would leak pool state across test cases.
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
}
