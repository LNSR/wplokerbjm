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
        private ?RuntimeWPHookProvider $provider = null,
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
            fn(DeferredHookEntryHandler $data): bool => $data->instance->get() === $instance
                && $this->runtimeResolver->matchesCallableTarget($data->key, $targetName),
        );

        return $before - \count($this->deferredHandlers);
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
            $triggerHook = $this->provider !== null
                ? $this->provider->resolveRuntimeHookName($triggerHook, $this->provider->buildCallablePlan($triggerHook instanceof \Closure ? $triggerHook : null), 'manual', $entry->instance?->get())
                : $this->runtimeResolver->resolveClosureHook($triggerHook, $entry->instance?->get(), 'manual');
            if ($triggerHook === null) {
                return;
            }
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

        \add_action($triggerHook, function () use ($triggerHook, $deferredKey, $activateEntry): void {
            $this->activateMatchingDeferredEntries(
                [$deferredKey],
                $activateEntry,
            );

            \remove_action($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
            $entry = $this->deferredHandlers[$deferredKey] ?? null;
            if ($entry === null) return;
            unset($this->deferredHandlers[$deferredKey]);
        }, PHP_INT_MIN, 0);

        Logger::debug(self::class, 'Deferred ' . $entry->handler->label . ' until trigger ' . $triggerHook);
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
