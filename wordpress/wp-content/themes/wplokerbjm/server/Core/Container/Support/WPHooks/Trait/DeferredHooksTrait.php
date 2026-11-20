<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredActivationResultDTO,
    DeferredDebugPhase,
    DeferredRegistrationSnapshotDTO,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker};


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
 * unregisterDeferredBy*) stays on the container-side ContainerRegistryDeferredHookManager; the
 * runtime registry consumes the same mechanics behind an automatic-only
 * surface.
 * @phpstan-import-type CallableHookParams from WPHookPlanProvider
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-import-type DeferredHookEntry from DeferredHookEntryHandler
 * @phpstan-import-type ActivateEntry from WPHooksContainerRegistry
 * @phpstan-import-type ActivateRuntimeEntry from WPHooksInstanceRegistry
 * @template TKey
 * @phpstan-type IndexedHandler array<TKey, DeferredHookEntryHandler>
 * @mixin EntriesIndexer
 * 
 * @internal
 */
trait DeferredHooksTrait
{
    /**
     * Deferred handlers pool, keyed directly by unique entry key (TKey).
     *
     * @var IndexedHandler
     */
    protected array $deferredHandlers = [];
    /** @var DeferredHookEntry['removeEntryIndexCallback'] */
    protected ?\Closure $destroyIndexes = null;


    /**
     * Store a deferred hook entry under its unique key.
     * @internal
     */
    private function addDeferred(DeferredHookEntryHandler $entry): void
    {
        $this->deferredHandlers[$entry->toUniqueKey()] = $entry;
        $this->entriesIndexer->setIndexes($entry);
        $entry->setOnEntryRemovalRemoveIndexesCallback($this->destroyIndexes ??= $this->entriesIndexer->removeIndexes(...));
        $this->emitDeferredSnapshot($entry, DeferredDebugPhase::Queued);
    }

    /**
     * Emit one deferred phase for an entry, building its snapshot.
     */
    private function emitDeferredSnapshot(
        DeferredHookEntryHandler $entry,
        DeferredDebugPhase $phase,
        ?DeferredActivationResultDTO $after = null,
    ): void {
        $owner = $entry->instance?->get();
        $entry->handler->emitDeferredDebug(
            $phase,
            new DeferredRegistrationSnapshotDTO(
                uniqueKey: $entry->toUniqueKey(),
                deferRegister: true,
                deferRegisterUntilHook: null,
                tags: (array) ($entry->tags ?? []),
                hasRegisterIf: $entry->registerIf !== null,
                hasExecuteIf: $entry->executeIf !== null,
                ownerAlive: $entry->instance === null || $owner !== null,
            ),
            $after,
            $owner,
            $owner !== null || $entry->instance === null,
        );
    }

    /**
     * Activate deferred entries identified by the supplied keys.
     *
     * Registration gates are re-evaluated immediately before activation.
     *
     * @param list<TKey> $keys
     * @param ActivateEntry&ActivateRuntimeEntry $activateEntry Moves an accepted entry to the active pool.
     * @return int Number of newly activated entries.
     */
    private function activateMatchingDeferredEntries(array $keys, callable $activateEntry): int
    {
        $activated = 0;
        if ($keys === []) return $activated;
        foreach ($keys as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;

            $startedAt = \hrtime(true);
            $poolBefore = \count($this->deferredHandlers);
            $this->emitDeferredSnapshot($entry, DeferredDebugPhase::ActivationAttempt);

            if (!$this->gateDeferredActivation($entry)) {
                $this->emitDeferredSnapshot($entry, DeferredDebugPhase::ActivationRejected, new DeferredActivationResultDTO(
                    gatePassed: false,
                    activated: false,
                    reason: 'register_if_false',
                    elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
                    poolSizeBefore: $poolBefore,
                    poolSizeAfter: \count($this->deferredHandlers),
                ));
                continue;
            }
            if (!$activateEntry($entry)) {
                $this->emitDeferredSnapshot($entry, DeferredDebugPhase::ActivationFailed, new DeferredActivationResultDTO(
                    gatePassed: true,
                    activated: false,
                    reason: 'activate_entry_returned_false',
                    elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
                    poolSizeBefore: $poolBefore,
                    poolSizeAfter: \count($this->deferredHandlers),
                ));
                continue;
            }
            $activated++;
            unset($this->deferredHandlers[$key]);
            $this->emitDeferredSnapshot($entry, DeferredDebugPhase::Activated, new DeferredActivationResultDTO(
                gatePassed: true,
                activated: true,
                reason: 'activated',
                elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
                poolSizeBefore: $poolBefore,
                poolSizeAfter: \count($this->deferredHandlers),
            ));
        }
        return $activated;
    }

    /**
     * Sweep the deferred pool and remove every entry matching the predicate.
     * @param list<TKey> $keys
     * @param callable(DeferredHookEntryHandler): bool $matches
     */
    private function unregisterMatchingDeferredEntries(array $keys, callable $matches): void
    {
        if ($keys === []) return;
        foreach ($keys as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if (!$matches($entry)) {
                continue;
            }
            unset($this->deferredHandlers[$key]);
            $this->emitDeferredSnapshot($entry, DeferredDebugPhase::Released);
        }
    }

    /**
     * Re-evaluate the registration gate for a deferred entry at activation time.
     */
    abstract protected function gateDeferredActivation(DeferredHookEntryHandler $data): bool;
}
