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
 *
 * @internal
 */
trait DeferredHooksTrait
{
    /**
     * Deferred handlers pool, keyed by [hook][key-string] like the active pool.
     *
     * @var array<string, array<string, DeferredHookEntryDTO>>
     */
    public private(set) array $deferredHandlers = [];

    /**
     * Store a deferred hook entry under its hook + key.
     */
    public function addDeferred(string $hook, string $key, DeferredHookEntryDTO $entry): void
    {
        $this->deferredHandlers[$hook][$key] = $entry;
    }

    /**
     * Sweep the deferred pool and activate every entry matching the predicate.
     *
     * Each matching entry is re-gated at activation time, then passed to
     * $activateEntry; the key is removed from the pool afterwards and empty
     * hook buckets are dropped.
     * @template T of callable(string, DeferredHookEntryDTO, string): bool
     * @param T $matches      Predicate over ($hook, $entry, $key).
     * @param T $activateEntry Moves the entry to the active pool and
     *                                                             registers it; returns true when newly
     *                                                             activated, false when already active.
     *
     * @return int Number of newly activated entries.
     */
    protected function activateMatchingDeferredEntries(callable $matches, callable $activateEntry): int
    {
        $activated = 0;

        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if (!$matches($hook, $data, $key)) {
                    continue;
                }

                // Registration gate: re-evaluated at activation time.
                if (!$this->gateDeferredActivation($data, $hook, $key)) {
                    continue;
                }

                if ($activateEntry($hook, $data, $key)) {
                    $activated++;
                }
                unset($hookHandlers[$key]);
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);

        return $activated;
    }

    /**
     * Sweep the deferred pool and remove every entry matching the predicate.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param callable(string, DeferredHookEntryDTO, string): bool $matches Predicate over ($hook, $entry, $key).
     */
    protected function unregisterMatchingDeferredEntries(callable $matches): void
    {
        foreach ($this->deferredHandlers as $hook => &$hookHandlers) {
            foreach ($hookHandlers as $key => $data) {
                if ($matches($hook, $data, $key)) {
                    unset($hookHandlers[$key]);
                }
            }
            if (empty($hookHandlers)) {
                unset($this->deferredHandlers[$hook]);
            }
        }
        unset($hookHandlers);
    }

    /**
     * Re-evaluate the registration gate for a deferred entry at activation time.
     *
     * Provider-specific: the container path re-runs registerIf through
     * WPHookPlanProvider; the runtime path evaluates registerIf through its
     * RuntimeHookProvider.
     *
     * @param DeferredHookEntryDTO $data Deferred entry.
     */
    abstract private function gateDeferredActivation(DeferredHookEntryDTO $data, string $hook, string $key): bool;
}
