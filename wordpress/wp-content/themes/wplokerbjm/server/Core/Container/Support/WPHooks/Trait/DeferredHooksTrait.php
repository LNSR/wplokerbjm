<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredActivationResultDTO,
    DeferredActivationSource,
    DeferredDebugPhase,
    DeferredRegistrationSnapshotDTO,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;


/**
 * Shared deferred-hook pool mechanics for hook registries.
 *
 * Owns the generic activation and unregistration sweeps. Storage is supplied
 * by the consuming manager:
 *
 * - container registry: one flat array keyed by unique entry key;
 * - instance registry: a WeakMap keyed by the owner object, with each bucket
 *   keyed by unique entry key.
 *
 * The registration gate is re-evaluated at activation time through
 * {@see gateDeferredActivation()}, which each consuming class implements with
 * its own provider (WPHookPlanProvider on the container path, RuntimeHookProvider
 * on the runtime path).
 *
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-import-type DeferredHookEntry from DeferredHookEntryHandler
 * @phpstan-import-type ActivateEntry from WPHooksContainerRegistry
 * @phpstan-import-type ActivateRuntimeEntry from WPHooksInstanceRegistry
 * @template TKey
 * @phpstan-type IndexedHandler array<TKey, DeferredHookEntryHandler>
 *
 * @internal
 */
trait DeferredHooksTrait
{
    /**
     * Get one deferred entry. Instance managers pass their owner; container
     * managers pass null and use their flat pool.
     *
     * @param TKey $key
     */
    abstract protected function deferredGet(string $key, ?object $owner = null): ?DeferredHookEntryHandler;

    /**
     * Store one deferred entry under its unique key.
     *
     * @param TKey $key
     */
    abstract protected function deferredPut(string $key, DeferredHookEntryHandler $entry, ?object $owner = null): void;

    /**
     * Remove one deferred entry.
     *
     * @param TKey $key
     */
    abstract protected function deferredRemove(string $key, ?object $owner = null): void;

    /**
     * Number of entries in the pool (owner bucket for instance managers).
     */
    abstract protected function deferredCount(?object $owner = null): int;

    /**
     * All entries in the pool (owner bucket for instance managers).
     *
     * @return IndexedHandler
     */
    abstract protected function deferredEntries(?object $owner = null): array;

    /**
     * Hook for managers that maintain secondary indexes. The container
     * manager uses this for EntriesIndexer; the instance manager has none.
     */
    protected function afterDeferredAdded(DeferredHookEntryHandler $entry): void {}

    /**
     * Store a deferred hook entry under its unique key.
     * @internal
     */
    private function addDeferred(DeferredHookEntryHandler $entry): void
    {
        $owner = $entry->instance?->get();

        // Instance-scoped entries cannot be tracked after their owner died.
        if ($entry->instance instanceof \WeakReference && $owner === null) return;

        $this->deferredPut($entry->toUniqueKey(), $entry, $owner);
        $this->afterDeferredAdded($entry);
        $this->emitDeferredSnapshot($entry, DeferredDebugPhase::Queued);
    }

    /**
     * Emit one deferred phase for an entry, building its snapshot.
     */
    private function emitDeferredSnapshot(
        DeferredHookEntryHandler $entry,
        DeferredDebugPhase $phase,
        ?DeferredActivationResultDTO $after = null,
        ?DeferredActivationSource $source = null,
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
            $source,
        );
    }

    /**
     * Activate deferred entries identified by the supplied keys.
     *
     * Registration gates are re-evaluated immediately before activation.
     *
     * @param list<TKey> $keys
     * @param ActivateEntry&ActivateRuntimeEntry $activateEntry Moves an accepted entry to the active pool.
     * @param DeferredActivationSource $source Why activation was attempted.
     * @param object|null $owner Owner bucket for instance managers; null for container managers.
     * @return int Number of newly activated entries.
     */
    private function activateMatchingDeferredEntries(
        array $keys,
        callable $activateEntry,
        DeferredActivationSource $source = DeferredActivationSource::Manual,
        ?object $owner = null,
    ): int {
        $activated = 0;
        if ($keys === []) return $activated;

        foreach ($keys as $key) {
            $entry = $this->deferredGet($key, $owner);
            if ($entry === null) continue;

            $startedAt = \hrtime(true);
            $poolBefore = $this->deferredCount($owner);
            $this->emitDeferredSnapshot($entry, DeferredDebugPhase::ActivationAttempt, source: $source);

            if (!$this->gateDeferredActivation($entry)) {
                $this->emitDeferredSnapshot($entry, DeferredDebugPhase::ActivationRejected, new DeferredActivationResultDTO(
                    gatePassed: false,
                    activated: false,
                    reason: 'register_if_false',
                    elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
                    poolSizeBefore: $poolBefore,
                    poolSizeAfter: $this->deferredCount($owner),
                ), $source);
                continue;
            }
            if (!$activateEntry($entry)) {
                $this->emitDeferredSnapshot($entry, DeferredDebugPhase::ActivationFailed, new DeferredActivationResultDTO(
                    gatePassed: true,
                    activated: false,
                    reason: 'activate_entry_returned_false',
                    elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
                    poolSizeBefore: $poolBefore,
                    poolSizeAfter: $this->deferredCount($owner),
                ), $source);
                continue;
            }
            $activated++;
            $this->deferredRemove($key, $owner);
            $this->emitDeferredSnapshot($entry, DeferredDebugPhase::Activated, new DeferredActivationResultDTO(
                gatePassed: true,
                activated: true,
                reason: 'activated',
                elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
                poolSizeBefore: $poolBefore,
                poolSizeAfter: $this->deferredCount($owner),
            ), $source);
        }
        return $activated;
    }

    /**
     * Sweep the deferred pool and remove every entry matching the predicate.
     *
     * @param list<TKey> $keys
     * @param callable(DeferredHookEntryHandler): bool $matches
     * @param object|null $owner Owner bucket for instance managers; null for container managers.
     */
    private function unregisterMatchingDeferredEntries(array $keys, callable $matches, ?object $owner = null): void
    {
        if ($keys === []) return;

        foreach ($keys as $key) {
            $entry = $this->deferredGet($key, $owner);
            if ($entry === null) continue;
            if (!$matches($entry)) {
                continue;
            }
            $this->deferredRemove($key, $owner);
            $this->emitDeferredSnapshot($entry, DeferredDebugPhase::Released);
        }
    }

    /**
     * Re-evaluate the registration gate for a deferred entry at activation time.
     */
    abstract protected function gateDeferredActivation(DeferredHookEntryHandler $data): bool;
}
