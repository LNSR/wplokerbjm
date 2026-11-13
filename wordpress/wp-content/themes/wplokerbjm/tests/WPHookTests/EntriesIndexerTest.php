<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\ContainerRegistryHandlerEntry;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Unit coverage for the hook-entry index.
 *
 * Every bucket must be keyed by the entry's own unique key, `setIndexes()`
 * and `removeIndexes()` must be perfectly symmetric (no leaks, empty buckets
 * dropped), and a namespace bucket must contain the whole subtree of that
 * namespace without ever crossing into a sibling prefix.
 */
final class EntriesIndexerTest extends WplokerbjmTestCase
{
    private ?EntriesIndexer $entriesIndexer { get => $this->entriesIndexer ??= new EntriesIndexer(); }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entriesIndexer = null;
    }
    
    /**
     * @param list<string> $tags
     */
    private function entry(
        string $class,
        string $method,
        string $hook = 'idx_hook',
        int $priority = 10,
        array $tags = [],
        string $target = 'method',
    ): ContainerRegistryHandlerEntry {
        $key = new HookKey(
            class: $class,
            method: $method,
            target: $target,
            type: 'action',
            priority: $priority,
            acceptedArgs: 1,
        );

        $handler = $target === 'method'
            ? new ContainerLazyHookInvoker($this->container(), $hook, $priority, $class, $method)
            : new ContainerLazyPropertyHookInvoker($this->container(), $hook, $priority, $class, $method);

        return new ContainerRegistryHandlerEntry(
            hook: $hook,
            key: $key,
            handler: $handler,
            type: 'action',
            priority: $priority,
            acceptedArgs: 1,
            tags: $tags,
            registerIf: null,
            registerIfParams: [],
            executeIf: null,
            executeIfParams: [],
            once: false,
            onUnregisterDo: null,
            onUnregisterDoParams: [],
            removeEntryIndexCallback: $this->entriesIndexer->removeIndexes(...)
        );
    }

    public function testIndexesEveryBucketForAMethodEntry(): void
    {
        $entry = $this->entry('App\Core\Sub\Foo', 'onInit', 'idx_hook', 10, ['graphql', 'cache']);

        $this->entriesIndexer->setIndexes($entry);

        $uniqueKey = $entry->toUniqueKey();

        self::assertSame([$uniqueKey => $uniqueKey], $this->entriesIndexer->byHook['idx_hook']);
        self::assertSame([$uniqueKey => $uniqueKey], $this->entriesIndexer->byClass['App\Core\Sub\Foo']);
        self::assertSame([$uniqueKey => $uniqueKey], $this->entriesIndexer->byCallable['App\Core\Sub\Foo->onInit']);
        self::assertSame([$uniqueKey => $uniqueKey], $this->entriesIndexer->byTag['graphql']);
        self::assertSame([$uniqueKey => $uniqueKey], $this->entriesIndexer->byTag['cache']);
    }

    public function testNamespaceChainIndexesEveryAncestor(): void
    {
        $entry = $this->entry('App\Core\Sub\Foo', 'onInit');

        $this->entriesIndexer->setIndexes($entry);

        $uniqueKey = $entry->toUniqueKey();

        self::assertSame(
            ['App\Core\Sub', 'App\Core', 'App'],
            $this->entriesIndexer->getNamespaceChain('App\Core\Sub\Foo'),
            'Chain must be deepest-first so getNamespace() can read index 0',
        );

        foreach (['App\Core\Sub', 'App\Core', 'App'] as $namespace) {
            self::assertSame(
                [$uniqueKey => $uniqueKey],
                $this->entriesIndexer->byNamespace[$namespace],
                'Ancestor namespace ' . $namespace . ' must contain the entry',
            );
        }
    }

    public function testSiblingPrefixIsNeverIndexed(): void
    {
        $entry = $this->entry('App\CoreExtra\Foo', 'onInit');

        $this->entriesIndexer->setIndexes($entry);

        self::assertArrayNotHasKey('App\Core', $this->entriesIndexer->byNamespace, 'Prefix match must not leak into a sibling namespace');
        self::assertArrayHasKey('App\CoreExtra', $this->entriesIndexer->byNamespace);
        self::assertArrayHasKey('App', $this->entriesIndexer->byNamespace);
    }

    public function testRemoveIndexesIsSymmetricAndGarbageCollectsEveryBucket(): void
    {
        $entry = $this->entry('App\Core\Sub\Foo', 'onInit', 'idx_hook', 10, ['graphql']);

        $this->entriesIndexer->setIndexes($entry);
        unset($entry);

        self::assertSame([], $this->entriesIndexer->byHook);
        self::assertSame([], $this->entriesIndexer->byClass);
        self::assertSame([], $this->entriesIndexer->byTag);
        self::assertSame([], $this->entriesIndexer->byCallable);
        self::assertSame([], $this->entriesIndexer->byNamespace, 'Every ancestor bucket must be garbage-collected');
    }

    public function testMultiplePrioritiesShareTheCallableBucketAndStayIndividuallyRemovable(): void
    {
        $original = $this->entry('App\Core\Foo', 'onInit', 'idx_hook', 10);
        $second = $this->entry('App\Core\Foo', 'onInit', 'idx_hook', 20);

        $this->entriesIndexer->setIndexes($original);
        $this->entriesIndexer->setIndexes($second);

        self::assertCount(2, $this->entriesIndexer->byCallable['App\Core\Foo->onInit']);
        self::assertCount(2, $this->entriesIndexer->byHook['idx_hook']);

        unset($original);

        $secondKey = $second->toUniqueKey();

        self::assertSame([$secondKey => $secondKey], $this->entriesIndexer->byCallable['App\Core\Foo->onInit']);
        self::assertSame([$secondKey => $secondKey], $this->entriesIndexer->byHook['idx_hook']);
        self::assertSame([$secondKey => $secondKey], $this->entriesIndexer->byClass['App\Core\Foo']);
    }

    public function testPropertyEntryUsesTheSameCallableShapeAsMethodEntries(): void
    {
        $entry = $this->entry('App\Core\Foo', 'settings', 'idx_prop', 10, [], 'property');

        $this->entriesIndexer->setIndexes($entry);

        self::assertArrayHasKey('App\Core\Foo->settings', $this->entriesIndexer->byCallable);
        self::assertArrayHasKey('idx_prop', $this->entriesIndexer->byHook);

        unset($entry);

        self::assertSame([], $this->entriesIndexer->byCallable);
        self::assertSame([], $this->entriesIndexer->byHook);
    }

    public function testGlobalNamespaceClassHasNoNamespaceBucket(): void
    {
        $entry = $this->entry('GlobalFoo', 'onInit');

        $this->entriesIndexer->setIndexes($entry);

        self::assertSame([], $this->entriesIndexer->getNamespaceChain('GlobalFoo'));
        self::assertSame('', $this->entriesIndexer->getNamespace($entry));
        self::assertSame([], $this->entriesIndexer->byNamespace);
    }

    public function testGetCallableAcceptsAnArrayTarget(): void
    {
        self::assertSame(
            'App\Core\Foo->onInit',
            (new EntriesIndexer())->getCallable(['App\Core\Foo', 'onInit']),
        );
    }
}
