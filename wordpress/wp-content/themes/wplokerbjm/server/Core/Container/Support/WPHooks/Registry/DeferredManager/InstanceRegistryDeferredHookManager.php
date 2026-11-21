<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use ActivateRuntimeEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    WPHooksInstanceRegistry,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\DeferredActivationSource;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\DeferredHooksTrait;
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\{HookPattern, HookTagUtilities};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;

/**
 * @phpstan-import-type ActivateRuntimeEntry from WPHooksInstanceRegistry
 */
class InstanceRegistryDeferredHookManager
{
    use DeferredHooksTrait;

    /** @var \WeakMap<object, array<string, DeferredHookEntryHandler>> */
    protected \WeakMap $deferredHandlers;

    public function __construct(
        private WPHookPlanProvider $provider,
        private InstanceRegistryRuntimeHookResolver $runtimeResolver = new InstanceRegistryRuntimeHookResolver(),
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {
        $this->deferredHandlers = new \WeakMap();
        $this->self = \WeakReference::create($this);
    }

    protected function deferredGet(string $key, ?object $owner = null): ?DeferredHookEntryHandler
    {
        if ($owner === null || !isset($this->deferredHandlers[$owner])) return null;

        return $this->deferredHandlers[$owner][$key] ?? null;
    }

    protected function deferredPut(string $key, DeferredHookEntryHandler $entry, ?object $owner = null): void
    {
        if ($owner === null) return;

        $bucket = isset($this->deferredHandlers[$owner]) ? $this->deferredHandlers[$owner] : [];
        $bucket[$key] = $entry;
        $this->deferredHandlers[$owner] = $bucket;
    }

    protected function deferredRemove(string $key, ?object $owner = null): void
    {
        if ($owner === null || !isset($this->deferredHandlers[$owner])) return;

        $bucket = $this->deferredHandlers[$owner];
        unset($bucket[$key]);

        if ($bucket === []) {
            unset($this->deferredHandlers[$owner]);
            return;
        }

        $this->deferredHandlers[$owner] = $bucket;
    }

    protected function deferredCount(?object $owner = null): int
    {
        if ($owner === null || !isset($this->deferredHandlers[$owner])) return 0;

        return \count($this->deferredHandlers[$owner]);
    }

    protected function deferredEntries(?object $owner = null): array
    {
        if ($owner === null || !isset($this->deferredHandlers[$owner])) return [];

        return $this->deferredHandlers[$owner];
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
        $before = $this->deferredCount($instance);
        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (!$this->runtimeResolver->matchesCallableTarget($entry->key, $targetName)) continue;
            $keys[] = $key;
        }

        $this->unregisterMatchingDeferredEntries(
            $keys,
            static fn(DeferredHookEntryHandler $data): bool => true,
            $instance,
        );

        return $before - $this->deferredCount($instance);
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

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (!$this->runtimeResolver->matchesCallableTarget($entry->key, $targetName)) continue;
            $keys[] = $key;
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
            $instance,
        );
    }

    /**
     * Activate every deferred entry owned by one live instance (all members).
     *
     * "Class-scoped" on the runtime path means the registration site of the
     * given live object: runtime ownership is per instance, so sibling
     * instances of the same class stay untouched. Entries are re-gated through
     * {@see gateDeferredActivation()} immediately before activation; a
     * rejecting `registerIf` keeps them parked.
     *
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceClassDeferredEntries(object $instance, callable $activateEntry): int
    {
        return $this->activateMatchingDeferredEntries(
            \array_keys($this->ownedEntries($instance)),
            $activateEntry,
            DeferredActivationSource::Manual,
            $instance,
        );
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
        $before = $this->deferredCount($instance);
        $keys = \array_keys($this->ownedEntries($instance));

        $this->unregisterMatchingDeferredEntries(
            $keys,
            static fn(DeferredHookEntryHandler $data): bool => true,
            $instance,
        );

        return $before - $this->deferredCount($instance);
    }

    /**
     * Activate deferred entries owned by one live instance for a specific
     * WordPress hook name.
     *
     * The owner bucket keeps sibling instances deferred even when they expose
     * the same hook. Entries are re-gated through
     * {@see gateDeferredActivation()} immediately before activation.
     *
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceHookDeferredEntries(object $instance, string $hook, callable $activateEntry): int
    {
        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if ($entry->hook !== $hook) continue;
            $keys[] = $key;
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
            $instance,
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
        $before = $this->deferredCount($instance);
        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if ($entry->hook !== $hook) continue;
            $keys[] = $key;
        }

        $this->unregisterMatchingDeferredEntries(
            $keys,
            static fn(DeferredHookEntryHandler $data): bool => true,
            $instance,
        );

        return $before - $this->deferredCount($instance);
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

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (!HookPattern::matches($entry->hook, $pattern)) continue;
            $keys[] = $key;
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
            $instance,
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
        $before = $this->deferredCount($instance);
        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (!HookPattern::matches($entry->hook, $pattern)) continue;
            $keys[] = $key;
        }

        $this->unregisterMatchingDeferredEntries(
            $keys,
            static fn(DeferredHookEntryHandler $data): bool => true,
            $instance,
        );

        return $before - $this->deferredCount($instance);
    }

    /**
     * Activate deferred entries owned by one live instance carrying at least
     * one of the supplied tags.
     *
     * @param array<int, string|\BackedEnum> $tags Tag list; matching is OR.
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceTagsDeferredEntries(object $instance, array $tags, callable $activateEntry): int
    {
        $tags = HookTagUtilities::normalizeTags($tags);
        if ($tags === []) return 0;

        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (\array_intersect($tags, (array) ($entry->tags ?? [])) === []) continue;
            $keys[] = $key;
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
            $instance,
        );
    }

    /**
     * Remove deferred entries owned by one live instance carrying at least
     * one of the supplied tags.
     *
     * @param array<int, string|\BackedEnum> $tags Tag list; matching is OR.
     * @return int Number of removed deferred entries.
     */
    public function unregisterInstanceTagsDeferredEntries(object $instance, array $tags): int
    {
        $tags = HookTagUtilities::normalizeTags($tags);
        if ($tags === []) return 0;

        $before = $this->deferredCount($instance);
        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (\array_intersect($tags, (array) ($entry->tags ?? [])) === []) continue;
            $keys[] = $key;
        }

        $this->unregisterMatchingDeferredEntries(
            $keys,
            static fn(DeferredHookEntryHandler $data): bool => true,
            $instance,
        );

        return $before - $this->deferredCount($instance);
    }

    /**
     * Activate deferred entries owned by one live instance carrying at least
     * one tag matching any supplied wildcard pattern.
     *
     * @param array<int, string> $patterns Tag wildcard patterns.
     * @param ActivateRuntimeEntry $activateEntry
     * @return int Number of newly activated entries.
     */
    public function activateInstanceTagPatternDeferredEntries(object $instance, array $patterns, callable $activateEntry): int
    {
        if ($patterns === []) return 0;
        HookPattern::assertValidAll($patterns);

        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (!HookPattern::matchesAny((array) ($entry->tags ?? []), $patterns)) continue;
            $keys[] = $key;
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
            $instance,
        );
    }

    /**
     * Remove deferred entries owned by one live instance carrying at least
     * one tag matching any supplied wildcard pattern.
     *
     * @param array<int, string> $patterns Tag wildcard patterns.
     * @return int Number of removed deferred entries.
     */
    public function unregisterInstanceTagPatternDeferredEntries(object $instance, array $patterns): int
    {
        if ($patterns === []) return 0;
        HookPattern::assertValidAll($patterns);

        $before = $this->deferredCount($instance);
        $keys = [];

        foreach ($this->ownedEntries($instance) as $key => $entry) {
            if (!HookPattern::matchesAny((array) ($entry->tags ?? []), $patterns)) continue;
            $keys[] = $key;
        }

        $this->unregisterMatchingDeferredEntries(
            $keys,
            static fn(DeferredHookEntryHandler $data): bool => true,
            $instance,
        );

        return $before - $this->deferredCount($instance);
    }

    /**
     * Register a deferRegisterUntilHook entry on the runtime path: the handler
     * is held in the owner's deferred bucket until the trigger hook fires, then
     * activated automatically. The trigger listener is a filter callback, so
     * apply_filters() keeps its value during activation.
     *
     * @param string|\Closure|null $deferRegisterUntilHook Trigger hook name or closure resolving to one.
     * @param ActivateRuntimeEntry $activateEntry
     */
    public function deferManualUntilHook(
        string|\Closure|null $deferRegisterUntilHook,
        DeferredHookEntryHandler $entry,
        callable $activateEntry
    ): void {
        $owner = $entry->instance?->get();
        if ($owner === null) return;

        $triggerHook = $deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            $triggerHook = $this->provider->resolveHookName(
                $triggerHook,
                $this->provider->buildCallablePlan($triggerHook),
                'manual',
                $owner,
            );
        }

        $this->addDeferred($entry);

        $deferredKey = $entry->toUniqueKey();

        if (did_action($triggerHook) >= 1 || did_filter($triggerHook) >= 1) {
            $this->activateMatchingDeferredEntries(
                [$deferredKey],
                $activateEntry,
                DeferredActivationSource::SchedulerImmediate,
                $owner,
            );
            return;
        }

        $ownerRef = $entry->instance;
        $cb = null;
        $self = $this->self;
        \add_filter($triggerHook, $cb = static function (mixed $value = null) use ($triggerHook, $deferredKey, $activateEntry, $ownerRef, $self): mixed {
            static $dispatchDepth = 0;
            if ($dispatchDepth > 0) return $value;
            try {
                $dispatchDepth++;

                $owner = $ownerRef?->get();
                $self = $self?->get();
                if ($owner === null || $self === null) {
                    \remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                    return $value;
                }

                $entry = $self->deferredGet($deferredKey, $owner);
                if ($entry === null) {
                    \remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                    return $value;
                }

                $activated = $self->activateMatchingDeferredEntries(
                    [$deferredKey],
                    $activateEntry,
                    DeferredActivationSource::Scheduler,
                    $owner,
                );

                if ($activated === 0) return $value;

                \remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                return $value;
            } finally {
                $dispatchDepth--;
            }
        }, PHP_INT_MIN, 1);

        $logger = $this->logger;
        $entry->setOnEntryRemovalCallback(static function (DeferredHookEntryHandler $entry) use ($triggerHook, $cb, $logger): void {
            if (!\has_filter($triggerHook, $cb, PHP_INT_MIN)) return;
            \remove_filter($triggerHook, $cb, PHP_INT_MIN);
            SharedUtils::isDevelopment() && $logger->debug(__METHOD__, 'Removing deferred entry scheduler from: ' . $entry->handler->label);
        });
    }

    /**
     * Hold a deferred entry for manual activation.
     *
     * With a trigger this delegates to {@see deferManualUntilHook()}; without
     * one the entry simply sits in the owner's pool until
     * {@see WPHooksInstanceRegistry::activateDeferredByCallableInLiveInstance()}
     * (or another deferred activation API) picks it up.
     *
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
     * Re-evaluate the registerIf registration gate when activating a deferred
     * runtime entry. A dead owner can never activate.
     */
    private function gateDeferredActivation(DeferredHookEntryHandler $data): bool
    {
        $owner = $data->instance?->get();
        if ($owner === null) return false;

        $registerIf = $data->registerIf ?? null;
        if ($registerIf === null) {
            return true;
        }

        try {
            $allowed = $this->provider->evaluateRegistrationGate(
                $registerIf,
                $data->registerIfParams ?? [],
                $data->handler->label,
                $owner,
            );
        } catch (\Throwable $e) {
            $this->logger->warning(__METHOD__, 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate threw: ' . $e->getMessage());
            return false;
        }

        if (!$allowed) {
            $this->logger->warning(__METHOD__, 'Skipping deferred hook activation ' . $data->handler->label . ' — registerIf gate returned false.');
            return false;
        }

        return true;
    }

    /**
     * Return the owner's live bucket, pruning entries whose owner reference
     * no longer points at this instance.
     *
     * @return array<string, DeferredHookEntryHandler>
     */
    private function ownedEntries(object $instance): array
    {
        $entries = $this->deferredEntries($instance);

        foreach ($entries as $key => $entry) {
            if ($entry->instance?->get() === $instance) continue;
            $this->deferredRemove($key, $instance);
            unset($entries[$key]);
        }

        return $entries;
    }
}
