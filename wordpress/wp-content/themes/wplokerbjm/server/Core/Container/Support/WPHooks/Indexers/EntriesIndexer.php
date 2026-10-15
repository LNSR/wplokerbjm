<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Indexers;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\ContainerRegistryHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\RuntimeRegistryHandlerEntry;

/**
 * Indexes hook entries by hook / class / tag / callable / namespace for O(K)
 * lookup.
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
    /** @var array<class-string, array<TKey, TKey>> */
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
        $namespace = $this->getNamespace($entry);
        if ($namespace !== '') {
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
        $namespace = $this->getNamespace($entry);
        if ($namespace !== '') {
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
     * Get the namespace of the entry.
     *
     * @param IndexEntryShape $entry
     * @return string
     */
    public function getNamespace(DeferredHookEntryHandler|ContainerRegistryHandlerEntry $entry): string
    {
        $className = trim($entry->key->class, '\\');

        $position = strrpos($className, '\\');

        return $position === false
            ? ''
            : substr($className, 0, $position);
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
