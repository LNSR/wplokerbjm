<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Shared\Utilities\DTO\AbstractDTO;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\DeferredHookEntryDTO;
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\HookRegistration;
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
 * @template TKey of string
 * @internal
 */
trait DeferredHooksTrait
{
    /**
     * Deferred handlers pool, keyed directly by unique entry key (TKey).
     *
     * @var array<TKey, DeferredHookEntryDTO>
     */
    public private(set) array $deferredHandlers = [];

    /**
     * Store a deferred hook entry under its unique key.
     */
    public function addDeferred(DeferredHookEntryDTO $entry): void
    {
        $this->deferredHandlers[$entry->toUniqueKey()] = $entry;
    }

    /**
     * Sweep the deferred pool and activate every entry matching the predicate.
     *
     * @param callable(DeferredHookEntryDTO): bool $matches
     * @param ActivateEntry $activateEntry Moves the entry to the active pool.
     *
     * @return int Number of newly activated entries.
     */
    protected function activateMatchingDeferredEntries(callable $matches, callable $activateEntry): int
    {
        $activated = 0;

        foreach ($this->deferredHandlers as $key => $data) {
            if (!$matches($data)) {
                continue;
            }

            if (!$this->gateDeferredActivation($data)) {
                continue;
            }

            if ($activateEntry($data)) {
                $activated++;
                unset($this->deferredHandlers[$data->toUniqueKey()]);
            }
        }

        return $activated;
    }

    /**
     * Sweep the deferred pool and remove every entry matching the predicate.
     *
     * @param callable(DeferredHookEntryDTO): bool $matches Predicate over ($hook, $entry, $key).
     */
    protected function unregisterMatchingDeferredEntries(callable $matches): void
    {
        foreach ($this->deferredHandlers as $key => $data) {
            if ($matches($data)) {
                unset($this->deferredHandlers[$data->toUniqueKey()]);
            }
        }
    }

    /**
     * Re-evaluate the registration gate for a deferred entry at activation time.
     */
    abstract private function gateDeferredActivation(DeferredHookEntryDTO $data): bool;
}