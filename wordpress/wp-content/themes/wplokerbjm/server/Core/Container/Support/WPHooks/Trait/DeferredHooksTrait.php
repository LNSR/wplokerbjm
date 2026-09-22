<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\DeferredHookEntryDTO;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookHandler, ContainerLazyPropertyHookHandler, RuntimeCallableHookHandler, RuntimeInstanceHookHandler, RuntimeInstancePropertyHookHandler};


/**
 * Shared deferred-hook pool mechanics for hook registries.
 *
 * Owns the deferred pool storage plus the generic activation and
 * unregistration sweeps. The registration gate is re-evaluated at activation
 * time through {@see gateDeferredActivation()}, which each consuming class
 * implements with its own provider (WPHookPlanProvider on the container path,
 * RuntimeHookProvider on the runtime path).
 *
 * The micromanage API (activateDeferredByHook/ByClass/ByTags/...,
 * unregisterDeferredBy*) stays on the container-side DeferredHookManager; the
 * runtime registry consumes the same mechanics behind an automatic-only
 * surface.
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-import-type ActivateEntry from WPHooksContainerRegistry
 * @template TKey
 * @phpstan-type IndexedHandler array<TKey, DeferredHookEntryDTO>
 * @internal
 */
trait DeferredHooksTrait
{
    /**
     * Deferred handlers pool, keyed directly by unique entry key (TKey).
     *
     * @var IndexedHandler
     */
    public private(set) array $deferredHandlers = [];

    /**
     * Store a deferred hook entry under its unique key.
     */
    public function addDeferred(DeferredHookEntryDTO $entry): void
    {
        $this->deferredHandlers[$entry->toUniqueKey()] = $entry;
        $this->entriesIndexer->setIndexes($entry);
    }

    /**
     * Sweep the deferred pool and activate every entry matching the predicate.
     * @param list<TKey> $keys
     * @param ActivateEntry $activateEntry Moves the entry to the active pool.
     * @return int Number of newly activated entries.
     */
    protected function activateMatchingDeferredEntries(array $keys, callable $activateEntry): int
    {
        $activated = 0;
        if ($keys === []) return $activated;
        foreach ($keys as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if (!$this->gateDeferredActivation($entry)) {
                continue;
            }
            $activateEntry($entry);
            $activated++;
            unset($this->deferredHandlers[$key]);
        }
        return $activated;
    }

    /**
     * Sweep the deferred pool and remove every entry matching the predicate.
     * @param list<TKey> $keys
     * @param callable(DeferredHookEntryDTO): bool $matches Predicate over ($hook, $entry, $key).
     */
    protected function unregisterMatchingDeferredEntries(array $keys, callable $matches): void
    {
        if ($keys === []) return;
        foreach ($keys as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if (!$matches($entry)) {
                continue;
            }
            unset($this->deferredHandlers[$key]);
        }
    }

    /**
     * Re-evaluate the registration gate for a deferred entry at activation time.
     */
    abstract protected function gateDeferredActivation(DeferredHookEntryDTO $data): bool;
}
