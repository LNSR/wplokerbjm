<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Indexers;

use WPLokerBJM\Core\Container\Support\WPHooks\ContainerRegistryHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\DeferredHookEntryDTO;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;

/**
 * Indexes based categories
 * @template TKey
 * @template IndexEntryShape of DeferredHookEntryDTO&ContainerRegistryHandlerEntry
 */
class EntriesIndexer
{
    /** @var array<string, list<TKey>> */
    public array $byHook = [];
    /** @var array<class-string, list<TKey>> */
    public array $byClass = [];
    /** @var array<string, list<TKey>> */
    public array $byTag = [];
    /** @var array<string, list<TKey>> */
    public array $byCallable = [];
    /** @var array<string, list<TKey>> */
    public array $byNamespace = [];

    /**
     * Indexes a new hook entry.
     * @param IndexEntryShape $entry Must implement toUniqueKey()
     */
    public function setIndexes(DeferredHookEntryDTO|ContainerRegistryHandlerEntry $entry): void
    {
        $uniqueKey = $entry->toUniqueKey();
        $this->byHook[$entry->hook][] = $uniqueKey;
        $this->byClass[$entry->key->class][] = $uniqueKey;
        foreach ($entry->tags as $tag) {
            $this->byTag[$tag][] = $uniqueKey;
        }
        $this->byCallable[$this->getCallable($entry)][] = $uniqueKey;
        $namespace = $this->getNamespace($entry);
        if ($namespace !== '') $this->byNamespace[$namespace][] = $uniqueKey;
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
    public function getNamespace(object $entry): string
    {
        $className = trim($entry->key->class, '\\');

        $position = strrpos($className, '\\');

        return $position === false
            ? ''
            : substr($className, 0, $position);
    }
}
