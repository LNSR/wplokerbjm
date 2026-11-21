<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use ActivateRuntimeEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    WPHooksInstanceRegistry,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\RuntimeRegistryHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\DeferredActivationSource;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\HookPattern;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;

/**
 * @phpstan-import-type ActivateRuntimeEntry from WPHooksInstanceRegistry
 */
class InstanceRegistryDeferredHookManager
{
    use DeferredHooksTrait;

    public function __construct(
        private WPHookPlanProvider $provider,
        private InstanceRegistryRuntimeHookResolver $runtimeResolver = new InstanceRegistryRuntimeHookResolver(),
        private EntriesIndexer $entriesIndexer = new EntriesIndexer(),
    ) {}

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
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceCallableDeferredEntries(object $instance, string $targetName, callable $activateEntry): int
    {
        $keys = [];
        $instanceId = (string) \spl_object_id($instance);

        foreach ($this->entriesIndexer->byClass[$instanceId] ?? [] as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if ($entry->instance?->get() !== $instance) {
                unset($this->deferredHandlers[$key]);
                continue;
            }
            if (!$this->runtimeResolver->matchesCallableTarget($entry->key, $targetName)) continue;
            $keys[$key] = $key;
        }

        return $this->activateMatchingDeferredEntries(array_values($keys), $activateEntry, DeferredActivationSource::Manual);
    }

    /**
     * Activate every deferred entry owned by one live instance (all members).
     *
     * "Class-scoped" on the runtime path means the registration site of the
     * given live object: runtime ownership is per instance (`spl_object_id`),
     * so sibling instances of the same class stay untouched. Entries are
     * re-gated through {@see gateDeferredActivation()} immediately before
     * activation; a rejecting `registerIf` keeps them parked.
     *
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceClassDeferredEntries(object $instance, callable $activateEntry): int
    {
        $keys = [];
        $instanceId = (string) \spl_object_id($instance);

        foreach ($this->entriesIndexer->byClass[$instanceId] ?? [] as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if ($entry->instance?->get() !== $instance) {
                unset($this->deferredHandlers[$key]);
                continue;
            }
            $keys[$key] = $key;
        }

        return $this->activateMatchingDeferredEntries(array_values($keys), $activateEntry, DeferredActivationSource::Manual);
    }

    /**
     * Remove every deferred entry owned by one live instance (all members).
     *
     * Counterpart of {@see activateInstanceClassDeferredEntries()}: the whole
     * deferred scope of the instance is dropped, while already-active
     * registrations are untouched.
     *
     * @return int Number of removed deferred entries.
     */
    public function unregisterInstanceClassDeferredEntries(object $instance): int
    {
        $before = \count($this->deferredHandlers);

        $this->unregisterMatchingDeferredEntries(
            $this->entriesIndexer->byClass[(string) \spl_object_id($instance)] ?? [],
            static fn(DeferredHookEntryHandler $data): bool => $data->instance?->get() === $instance,
        );

        return $before - \count($this->deferredHandlers);
    }

    /**
     * Activate deferred entries owned by one live instance for a specific
     * WordPress hook name.
     *
     * The owner filter keeps sibling instances deferred even when they expose
     * the same hook. Entries are re-gated through
     * {@see gateDeferredActivation()} immediately before activation.
     *
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceHookDeferredEntries(object $instance, string $hook, callable $activateEntry): int
    {
        $keys = [];

        foreach ($this->entriesIndexer->byHook[$hook] ?? [] as $key) {
            $entry = $this->deferredHandlers[$key] ?? null;
            if ($entry === null) continue;
            if ($entry->instance?->get() !== $instance) continue;
            $keys[$key] = $key;
        }

        return $this->activateMatchingDeferredEntries(
            array_values($keys),
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Remove deferred entries owned by one live instance for a specific
     * WordPress hook name.
     *
     * Only touches the deferred pool — active registrations are kept.
     *
     * @return int Number of removed deferred entries.
     */
    public function unregisterInstanceHookDeferredEntries(object $instance, string $hook): int
    {
        $before = \count($this->deferredHandlers);

        $this->unregisterMatchingDeferredEntries(
            $this->entriesIndexer->byHook[$hook] ?? [],
            fn(DeferredHookEntryHandler $data): bool => $data->instance?->get() === $instance,
        );

        return $before - \count($this->deferredHandlers);
    }

    /**
     * Activate deferred entries owned by one live instance whose hook name
     * matches a wildcard pattern.
     *
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceHookPatternDeferredEntries(object $instance, string $pattern, callable $activateEntry): int
    {
        HookPattern::assertValid($pattern);

        $keys = [];

        foreach ($this->entriesIndexer->byHook as $hook => $hookKeys) {
            if (!HookPattern::matches($hook, $pattern)) continue;

            foreach ($hookKeys as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if ($entry === null) continue;
                if ($entry->instance?->get() !== $instance) continue;
                $keys[$key] = $key;
            }
        }

        return $this->activateMatchingDeferredEntries(
            array_values($keys),
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Remove deferred entries owned by one live instance whose hook name
     * matches a wildcard pattern.
     *
     * @return int Number of removed deferred entries.
     */
    public function unregisterInstanceHookPatternDeferredEntries(object $instance, string $pattern): int
    {
        HookPattern::assertValid($pattern);

        $keys = [];

        foreach ($this->entriesIndexer->byHook as $hook => $hookKeys) {
            if (!HookPattern::matches($hook, $pattern)) continue;

            foreach ($hookKeys as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if ($entry === null) continue;
                if ($entry->instance?->get() !== $instance) continue;
                $keys[$key] = $key;
            }
        }

        $before = \count($this->deferredHandlers);

        $this->unregisterMatchingDeferredEntries(
            array_values($keys),
            fn(DeferredHookEntryHandler $data): bool => $data->instance?->get() === $instance,
        );

        return $before - \count($this->deferredHandlers);
    }

    /**
     * Register a deferRegisterUntilHook entry on the runtime path: the handler
     * is held in the deferred pool until the trigger hook fires, then activated
     * automatically — no manual activation API. The trigger listener is a
     * filter callback, so apply_filters() keeps its value during activation.
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
            $triggerHook = $this->provider->resolveHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook), 'manual', $entry->instance?->get() ?? null);
        }

        $this->addDeferred($entry);

        $deferredKey = $entry->toUniqueKey();

        if (did_action($triggerHook) >= 1 || did_filter($triggerHook) >= 1) {
            $this->activateMatchingDeferredEntries(
                [$deferredKey],
                $activateEntry,
                DeferredActivationSource::SchedulerImmediate,
            );
            return;
        }
        $cb = null;
        \add_filter($triggerHook, $cb = function (mixed $value = null) use ($triggerHook, $deferredKey, $activateEntry): mixed {
            $entry = $this->deferredHandlers[$deferredKey] ?? null;
            if ($entry === null) {
                \remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                return $value;
            }

            if ($entry->instance?->get() === null) {
                unset($this->deferredHandlers[$deferredKey]);
                \remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                return $value;
            }

            $activated = $this->activateMatchingDeferredEntries(
                [$deferredKey],
                $activateEntry,
                DeferredActivationSource::Scheduler,
            );

            if ($activated === 0) return $value;

            \remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
            unset($this->deferredHandlers[$deferredKey]);
            return $value;
        }, PHP_INT_MIN, 1);

        $entry->setOnEntryRemovalRemoveSchedulerCallback(static function (DeferredHookEntryHandler $entry) use ($triggerHook, $cb): void {
            if (!\has_filter($triggerHook, $cb, PHP_INT_MIN)) return;
            \remove_filter($triggerHook, $cb, PHP_INT_MIN);
            SharedUtils::isDevelopment() && Logger::debug(__METHOD__, 'Removing deferred entry scheduler from: ' . $entry->handler->label);
        });
    }

    /**
     * Hold a deferred entry for manual activation.
     *
     * With a trigger this delegates to {@see deferManualUntilHook()}; without
     * one the entry simply sits in the pool until
     * {@see WPHooksInstanceRegistry::activateDeferredByCallableInLiveInstance()}
     * (or another deferred activation API) picks it up.
     * @param ActivateRuntimeEntry $activateEntry
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
            $allowed = $this->provider->evaluateRegistrationGate(
                $registerIf,
                $data->registerIfParams ?? [],
                $data->handler->label,
                $owner,
            );
        } catch (\Throwable $e) {
            Logger::warning(__METHOD__, 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate threw: ' . $e->getMessage());
            return false;
        }

        if (!$allowed) {
            Logger::warning(__METHOD__, 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate returned false.');
            return false;
        }

        return true;
    }
}
