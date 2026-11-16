<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    WPHooksInstanceRegistry,
    RuntimeRegistryHandlerEntry
};

use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;

/**
 * @phpstan-import-type ActivateRuntimeEntry from WPHooksInstanceRegistry
 */
class InstanceRegistryDeferredHookManager
{
    use DeferredHooksTrait;

    public function __construct(
        private RuntimeWPHookProvider $provider = new RuntimeWPHookProvider(null),
        private InstanceRegistryRuntimeHookResolver $runtimeResolver = new InstanceRegistryRuntimeHookResolver(),
        private EntriesIndexer $entriesIndexer = new EntriesIndexer(),
    ) {}

    public function unregisterInstanceMatchingDeferredEntries(object $instance): void
    {
        $this->unregisterMatchingDeferredEntries(
            $this->entriesIndexer->byClass[(string) \spl_object_id($instance)] ?? [],
            static fn(DeferredHookEntryHandler $data): bool => $data->instance->get() === $instance,
        );
        unset($this->entriesIndexer->byClass[(string) \spl_object_id($instance)]);
    }

    /**
     * Remove deferred entries for one callable target of a live instance.
     *
     * Mirrors {@see WPHooksInstanceRegistry::unregisterByCallableInLiveInstance()}:
     * deferred entries are not in the active pool yet, so without this they
     * would resurrect the hook when their trigger fires.
     */
    public function unregisterInstanceCallableDeferredEntries(object $instance, string $targetName): int
    {
        $before = \count($this->deferredHandlers);

        $this->unregisterMatchingDeferredEntries(
            $this->entriesIndexer->byClass[(string) \spl_object_id($instance)] ?? [],
            fn(DeferredHookEntryHandler $data): bool => $data->instance?->get() === $instance
                && $this->runtimeResolver->matchesCallableTarget($data->key, $targetName),
        );

        return $before - \count($this->deferredHandlers);
    }

    /**
     * Activate deferred entries for one callable target of a live instance.
     *
     * Mirrors {@see WPHooksInstanceRegistry::activateDeferredByCallableInLiveInstance()}:
     * entries are re-gated through {@see gateDeferredActivation()} immediately
     * before activation, so a `registerIf` that still rejects keeps them
     * parked for a later attempt.
     *
     * @param callable(DeferredHookEntryHandler): bool $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceCallableDeferredEntries(object $instance, string $targetName, callable $activateEntry): int
    {
        $keys = [];
        $instanceId = (string) \spl_object_id($instance);

        foreach ($this->entriesIndexer->byClass[$instanceId] ?? [] as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if ($entry->instance?->get() !== $instance) continue;
            if (!$this->runtimeResolver->matchesCallableTarget($entry->key, $targetName)) continue;
            $keys[$key] = $key;
        }

        return $this->activateMatchingDeferredEntries(array_values($keys), $activateEntry);
    }

    /**
     * Register a deferRegisterUntilHook entry on the
     * runtime path: the handler is held in the deferred pool until the
     * trigger hook fires, then activated automatically — no manual
     * activation API.
     * @param string|\Closure|null $deferRegisterUntilHook Trigger hook name or closure resolving to one.
     * @param ActivateRuntimeEntry $activateEntry
     */
    public function deferManualUntilHook(
        string|\Closure|null $deferRegisterUntilHook,
        DeferredHookEntryHandler $entry,
        callable $activateEntry
    ): void {
        $triggerHook = $deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            $triggerHook = $this->provider->resolveRuntimeHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook instanceof \Closure ? $triggerHook : null), 'manual', $entry->instance?->get());
        }

        $this->addDeferred($entry);

        $deferredKey = $entry->toUniqueKey();

        if (did_action($triggerHook)) {
            $this->activateMatchingDeferredEntries(
                [$deferredKey],
                $activateEntry,
            );
            return;
        }
        $cb = null;
        \add_action($triggerHook, $cb = function () use ($triggerHook, $deferredKey, $activateEntry): void {
            $entry = $this->deferredHandlers[$deferredKey] ?? null;
            if ($entry === null) {
                \remove_action($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                return;
            }

            if ($entry->instance?->get() === null) {
                unset($this->deferredHandlers[$deferredKey]);
                \remove_action($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                return;
            }

            $activated = $this->activateMatchingDeferredEntries(
                [$deferredKey],
                $activateEntry,
            );

            if ($activated === 0) return;

            \remove_action($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
            unset($this->deferredHandlers[$deferredKey]);
        }, PHP_INT_MIN, 0);

        $entry->setOnEntryRemovalRemoveSchedulerCallback(static fn () => \remove_action($triggerHook, $cb, PHP_INT_MIN));
    }

    /**
     * Hold a deferred entry for manual activation.
     *
     * With a trigger this delegates to {@see deferManualUntilHook()}; without
     * one the entry simply sits in the pool until
     * {@see WPHooksInstanceRegistry::activateDeferredByCallableInLiveInstance()}
     * (or another deferred activation API) picks it up.
     */
    public function deferManualRegistration(
        DeferredHookEntryHandler $entry,
        string|\Closure|null $deferRegisterUntilHook,
        callable $activateEntry
    ): void {
        if ($deferRegisterUntilHook === null) {
            $this->addDeferred($entry);
            return;
        }

        $this->deferManualUntilHook($deferRegisterUntilHook, $entry, $activateEntry);
    }

    /**
     * Re-evaluate the registerIf registration gate when activating a
     * deferred runtime entry. Without a provider there is no way to evaluate
     * the gate, so the entry is allowed (mirrors the container path's defer
     * semantics).
     */
    private function gateDeferredActivation(DeferredHookEntryHandler $data): bool
    {
        $registerIf = $data->registerIf ?? null;
        if ($registerIf === null) {
            return true;
        }

        $owner = $data->instance?->get();
        if ($owner === null) {
            unset($this->deferredHandlers[$data->toUniqueKey()]);
            return false;
        }

        try {
            $allowed = $this->provider !== null
                ? $this->provider->evaluateRuntimeRegisterIf(
                    $registerIf,
                    $data->registerIfParams ?? [],
                    $data->handler->label,
                    $owner,
                )
                : $this->runtimeResolver->evaluateRegisterIf($registerIf, $owner, $data->hook);
        } catch (\Throwable $e) {
            Logger::warning(self::class, 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate threw: ' . $e->getMessage());
            return false;
        }

        if (!$allowed) {
            Logger::warning(self::class, 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate returned false.');
            return false;
        }

        return true;
    }
}
