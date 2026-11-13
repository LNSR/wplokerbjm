<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Indexers;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{ContainerRegistryHandlerEntry, RuntimeRegistryHandlerEntry};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\DeferredHookEntryHandler;

/**
 * Indexes hook entries by hook / class / tag / callable / namespace for O(K)
 * lookup.
 *
 * Namespace buckets store every ANCESTOR prefix of the entry class, so a
 * parent-namespace query (`App\Core`) matches its whole subtree
 * (`App\Core\Sub\Foo`) while never matching a sibling prefix
 * (`App\CoreExtra\Foo`).
 *
 * Every bucket is keyed by the entry's own unique key (`toUniqueKey()`), so
 * {@see setIndexes()} and {@see removeIndexes()} are perfectly symmetric and a
 * removal is O(1) with no list re-scan. Empty buckets are dropped on removal so
 * the index never leaks.
 *
 * @template TKey of string
 * @template IndexEntryShape of DeferredHookEntryHandler|ContainerRegistryHandlerEntry
 */
class EntriesIndexer
{
    /** @var array<string, array<TKey, TKey>> */
    public array $byHook = [];
    /** @var array<class-string|string, array<TKey, TKey>> */
    public array $byClass = [];
    /** @var array<string, array<TKey, TKey>> */
    public array $byTag = [];
    /** @var array<string, array<TKey, TKey>> */
    public array $byCallable = [];
    /** @var array<string, array<TKey, TKey>> */
    public array $byNamespace = [];

    /**
     * Indexes a new hook entry.
     * @param IndexEntryShape $entry Must implement toUniqueKey()
     */
    public function setIndexes(DeferredHookEntryHandler|ContainerRegistryHandlerEntry $entry): void
    {
        $uniqueKey = $entry->toUniqueKey();
        $this->byHook[$entry->hook][$uniqueKey] = $uniqueKey;
        $this->byClass[$entry->key->class][$uniqueKey] = $uniqueKey;
        foreach ($entry->tags as $tag) {
            $this->byTag[$tag][$uniqueKey] = $uniqueKey;
        }
        $this->byCallable[$this->getCallable($entry)][$uniqueKey] = $uniqueKey;
        foreach ($this->getNamespaceChain($entry->key->class) as $namespace) {
            $this->byNamespace[$namespace][$uniqueKey] = $uniqueKey;
        }
    }

    /**
     * Remove hook entry indexes.
     *
     * Mirrors {@see setIndexes()} exactly (same buckets, same keys) and drops
     * emptied buckets so repeated register/unregister cycles never leak.
     *
     */
    public function removeIndexes(DeferredHookEntryHandler|ContainerRegistryHandlerEntry $entry): void
    {
        $uniqueKey = $entry->toUniqueKey();
        $this->unsetIndex($this->byHook, $entry->hook, $uniqueKey);
        $this->unsetIndex($this->byClass, $entry->key->class, $uniqueKey);
        foreach ($entry->tags as $tag) {
            $this->unsetIndex($this->byTag, $tag, $uniqueKey);
        }
        $this->unsetIndex($this->byCallable, $this->getCallable($entry), $uniqueKey);
        foreach ($this->getNamespaceChain($entry->key->class) as $namespace) {
            $this->unsetIndex($this->byNamespace, $namespace, $uniqueKey);
        }
    }


    /**
     * Get the callable string representation of the entry.
     *
     * @param IndexEntryShape|array<class-string, callable-string> $entry
     * @return string
     */
    public function getCallable(object|array $entry): string
    {
        if (is_array($entry)) {
            return $entry['0'] . '->' . $entry['1'];
        }
        return $entry->key->class . '->' . $entry->key->method;
    }

    /**
     * Get the immediate namespace of the entry (the deepest ancestor).
     *
     * @param IndexEntryShape $entry
     * @return string Empty string for a class in the global namespace.
     */
    public function getNamespace(DeferredHookEntryHandler|ContainerRegistryHandlerEntry $entry): string
    {
        return $this->getNamespaceChain($entry->key->class)[0] ?? '';
    }

    /**
     * Every ancestor namespace of a class, deepest first.
     *
     * `setIndexes()` indexes the whole chain, so a parent-namespace lookup
     * stays O(K): `byNamespace['App\Core']` contains `App\Core\Foo`,
     * `App\Core\Sub\Foo`, … without scanning bucket keys. Sibling prefixes are
     * never included (`App\CoreExtra` is absent from `App\Core`).
     *
     * @return list<string> `A\B\C\Foo` → ['A\B\C', 'A\B', 'A']; global class → [].
     */
    public function getNamespaceChain(string $className): array
    {
        $parts = \explode('\\', \trim($className, '\\'));
        \array_pop($parts); // drop the class short name

        $chain = [];

        while ($parts !== []) {
            $chain[] = \implode('\\', $parts);
            \array_pop($parts);
        }

        return $chain;
    }

    /**
     * Drop one entry from a bucket and garbage-collect the bucket when empty.
     *
     * @param array<string, array<TKey, TKey>> $bucket
     * @param string $index Bucket key (hook / class / tag / callable / namespace).
     * @param TKey $uniqueKey Entry unique key.
     */
    private function unsetIndex(array &$bucket, string $index, string $uniqueKey): void
    {
        if (!isset($bucket[$index])) {
            return;
        }
        unset($bucket[$index][$uniqueKey]);
        if ($bucket[$index] === []) {
            unset($bucket[$index]);
        }
    }
}
