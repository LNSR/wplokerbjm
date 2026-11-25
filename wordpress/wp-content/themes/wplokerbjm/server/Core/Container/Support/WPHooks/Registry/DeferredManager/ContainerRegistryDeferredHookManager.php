<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\DeferredActivationSource;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\{Registrations\HookRegistration, Registrations\HookKey, Provider\WPHookPlanProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\{HookPattern, HookTagUtilities};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\{ModuleHookMetadata};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    WPHooksContainerRegistry,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\ContainerRegistryHookTargetResolver;

/**
 * @internal not for external use beyond @see WPHooksContainerRegistry
 * 
 * @template TargetClass
 * @phpstan-type HookTargetResolve callable|string|array{class-string<TargetClass>&object<TargetClass>, callable-string<TargetClass>}
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type DeferredHookEntry from DeferredHooksTrait
 * @phpstan-import-type ActivateEntry from WPHooksContainerRegistry
 */

class ContainerRegistryDeferredHookManager
{
    use DeferredHooksTrait;

    /** @var array<string, DeferredHookEntryHandler> */
    protected array $deferredHandlers = [];
    private ?\Closure $destroyIndexes = null;

    /**
     * @param WPHookPlanProvider  $planProvider Plan provider used for registerIf gates.
     * @param ContainerRegistryHookTargetResolver $resolverTarget 
     * @param EntriesIndexer $entriesIndexer for fast lookup of matching entries
     */
    public function __construct(
        private WPHookPlanProvider $planProvider,
        private ContainerRegistryHookTargetResolver $resolverTarget = new ContainerRegistryHookTargetResolver(),
        private EntriesIndexer $entriesIndexer = new EntriesIndexer(),
        private readonly HookLoggerInterface $logger = new NullHookLogger()
    ) {
        $this->self = \WeakReference::create($this);
    }

    protected function deferredGet(string $key, ?object $owner = null): ?DeferredHookEntryHandler
    {
        return $this->deferredHandlers[$key] ?? null;
    }

    protected function deferredPut(string $key, DeferredHookEntryHandler $entry, ?object $owner = null): void
    {
        $this->deferredHandlers[$key] = $entry;
    }

    protected function deferredRemove(string $key, ?object $owner = null): void
    {
        unset($this->deferredHandlers[$key]);
    }

    protected function deferredCount(?object $owner = null): int
    {
        return \count($this->deferredHandlers);
    }

    protected function deferredEntries(?object $owner = null): array
    {
        return $this->deferredHandlers;
    }

    protected function afterDeferredAdded(DeferredHookEntryHandler $entry): void
    {
        $this->entriesIndexer->setIndexes($entry);
        $entry->setOnEntryRemovalRemoveIndexesCallback(
            $this->destroyIndexes ??= $this->entriesIndexer->removeIndexes(...)
        );
    }

    /**
     * Activate all deferred handlers registered for a specific WordPress hook.
     *
     * @param ActivateEntry $activateEntry Registry callback: moves the
     *                                                             entry to the active pool and
     *                                                             registers it; returns true when
     *                                                             newly activated, false when the
     *                                                             handler was already active.
     */
    public function activateDeferredByHook(string $hook, \Closure $activateEntry): int
    {
        return $this->activateMatchingDeferredEntries(
            $this->entriesIndexer->byHook[$hook] ?? [],
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Activate all deferred handlers belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     */
    public function activateDeferredByClass(string $class, \Closure $activateEntry): int
    {
        return $this->activateMatchingDeferredEntries(
            $this->entriesIndexer->byClass[$class] ?? [],
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Activate all deferred handlers belonging to a service namespace.
     *
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash, so 'App\Core' never matches 'App\CoreExtra\Foo'.
     *
     * @param string $namespace Fully qualified namespace prefix.
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByNamespace(string $namespace, \Closure $activateEntry): int
    {
        $entries = $this->entriesIndexer->byNamespace[$namespace] ?? [];
        $keys = [];
        foreach ($entries as $key) {
            $keys[] = $key;
        }
        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Activate deferred hook handlers matching the specified target callable or identifier.
     *
     * @param HookTargetResolve $target
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     *
     * @throws \InvalidArgumentException If the target cannot be resolved to a valid class and member.
     */
    public function activateDeferredByCallable(callable|string|array $target, \Closure $activateEntry): int
    {
        $key = $this->entriesIndexer->getCallable($this->resolverTarget->resolve($target));

        return $this->activateMatchingDeferredEntries(
            $this->entriesIndexer->byCallable[$key] ?? [],
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Activate all deferred handlers carrying at least one of the given tags.
     *
     * @param array<int, string> $tags Normalized tag list to activate.
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTags(array $tags, \Closure $activateEntry): int
    {
        if ($tags === []) {
            return 0;
        }

        $keys = [];

        foreach ($tags as $tag) {
            $entries = $this->entriesIndexer->byTag[$tag] ?? [];
            foreach ($entries as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if (! isset($entry)) {
                    continue;
                }
                if (\array_intersect($tags, $entry->tags) !== []) {
                    $keys[] = $key;
                }
            }
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Activate all deferred handlers whose hook name matches a wildcard pattern.
     *
     * The pattern must contain exactly one trailing asterisk with a literal
     * prefix of at least two characters, e.g. 'mail_*'. Already-active
     * handlers are silently skipped.
     *
     * @param string $pattern Wildcard hook-name pattern.
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByHookPattern(string $pattern, \Closure $activateEntry): int
    {
        HookPattern::assertValid($pattern);
        $hooks = $this->entriesIndexer->byHook;
        $keys = [];
        foreach ($hooks as $hook) {
            foreach ($hook as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if ($entry === null) {
                    continue;
                }
                if (HookPattern::matches($entry->hook, $pattern)) {
                    $keys[] = $key;
                }
            }
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Activate all deferred handlers carrying at least one tag matching any of
     * the given wildcard patterns.
     *
     * Each pattern is its own tag family to wipe: an entry matches when any of
     * its tags matches any pattern (union of families).
     *
     * @param array<string> $patterns Tag wildcard patterns.
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     * @return int Number of handlers activated.
     */
    public function activateDeferredByTagPattern(array $patterns, \Closure $activateEntry): int
    {
        if ($patterns === []) {
            return 0;
        }
        HookPattern::assertValidAll($patterns);
        $entries = $this->entriesIndexer->byTag;
        // Deduplicate: one entry may live in several matching tag buckets.
        $keys = [];
        foreach ($entries as $value) {
            foreach ($value as $uniqueKey) {
                $entry = $this->deferredHandlers[$uniqueKey] ?? null;
                if ($entry === null) {
                    continue;
                }
                if (HookPattern::matchesAny($entry->tags, $patterns)) {
                    $keys[$uniqueKey] = true;
                }
            }
        }

        return $this->activateMatchingDeferredEntries(
            \array_keys($keys),
            $activateEntry,
            DeferredActivationSource::Manual,
        );
    }

    /**
     * Unregister a deferred hook using a first-class callable, array lookup,
     * or class-string identifier.
     *
     * @param HookTargetResolve $target
     */
    public function unregisterDeferredByCallable(callable|string|array $target): int
    {
        $keyCallable = $this->entriesIndexer->getCallable($this->resolverTarget->resolve($target));
        $entries = $this->entriesIndexer->byCallable[$keyCallable] ?? [];
        $count = 0;
        foreach ($entries as $key) {
            if (!isset($this->deferredHandlers[$key])) {
                continue;
            }
            $handler = $this->deferredHandlers[$key];
            unset($this->deferredHandlers[$key]);
            $count++;
        }
        return $count;
    }

    /**
     * Unregister all deferred handlers for a specific WordPress hook name.
     *
     * @param string $hook WordPress hook name.
     */
    public function unregisterDeferredByHook(string $hook): int
    {
        $entries = $this->entriesIndexer->byHook[$hook] ?? [];
        $count = 0;
        foreach ($entries as $key) {
            if (!isset($this->deferredHandlers[$key])) {
                continue;
            }
            $handler = $this->deferredHandlers[$key];
            unset($this->deferredHandlers[$key]);
            $count++;
        }
        return $count;
    }

    /**
     * Unregister all deferred hooks belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     */
    public function unregisterDeferredByClass(string $class): int
    {
        $entries = $this->entriesIndexer->byClass[$class] ?? [];
        $count = 0;
        foreach ($entries as $key) {
            if (!isset($this->deferredHandlers[$key])) {
                continue;
            }
            $handler = $this->deferredHandlers[$key];
            unset($this->deferredHandlers[$key]);
            $count++;
        }
        return $count;
    }

    /**
     * Unregister all deferred hooks belonging to a service namespace.
     *
     * Only touches the deferred pool — active handlers are never affected.
     * A class matches when it equals the namespace or starts with it followed
     * by a backslash.
     *
     * @param string $namespace Fully qualified namespace prefix.
     */
    public function unregisterDeferredByNamespace(string $namespace): int
    {
        $entries = $this->entriesIndexer->byNamespace[$namespace] ?? [];
        $count = 0;
        foreach ($entries as $key) {
            $handler = $this->deferredHandlers[$key] ?? null;
            if ($handler === null) {
                continue;
            }
            if (!$handler->key->isWithinNamespace($namespace)) {
                continue;
            }
            unset($this->deferredHandlers[$key]);
            $count++;
        }
        return $count;
    }

    /**
     * Unregister all deferred handlers carrying at least one of the given tags.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array<int, string> $tags Normalized tag list to unregister.
     */
    public function unregisterDeferredByTags(array $tags): int
    {
        if ($tags === []) {
            return 0;
        }
        $count = 0;
        foreach ($tags as $tag) {
            $entries = $this->entriesIndexer->byTag[$tag] ?? [];
            if (empty($entries)) {
                continue;
            }

            foreach ($entries as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if ($entry === null) {
                    continue;
                }
                unset($this->deferredHandlers[$key]);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Unregister all deferred handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterDeferredByHookPattern(string $pattern): int
    {
        HookPattern::assertValid($pattern);
        $hooks = $this->entriesIndexer->byHook ?? [];
        $count = 0;
        foreach ($hooks as $hook) {
            foreach ($hook as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if (!($entry instanceof DeferredHookEntryHandler)) continue;
                if (HookPattern::matches($entry->hook, $pattern)) {
                    unset($this->deferredHandlers[$key]);
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Unregister all deferred handlers carrying at least one tag matching any
     * of the given wildcard patterns.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterDeferredByTagPattern(array $patterns): int
    {
        if ($patterns === []) {
            return 0;
        }
        HookPattern::assertValidAll($patterns);
        $count = 0;
        $tags = $this->entriesIndexer->byTag ?? [];
        foreach ($tags as $keys) {
            foreach ($keys as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if (!($entry instanceof DeferredHookEntryHandler)) continue;
                if (HookPattern::matchesAny($entry->tags, $patterns)) {
                    unset($this->deferredHandlers[$key]);
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Schedule one-shot activation of a deferRegisterUntilHook entry when the
     * trigger hook fires. If the trigger already fired (did_action), the entry
     * is activated immediately.
     *
     * The listener is a filter callback so a trigger dispatched through
     * `apply_filters()` keeps its value while activation runs as a side effect.
     * The deferred entry stays attached while the gates keep failing, so a later
     * call to method activateDeferredBy*(...) can still activate them.
     *
     * @param string $triggerHook WordPress hook that gates activation.
     * @param ActivateEntry $activateEntry
     */
    private function scheduleDeferredActivation(string $triggerHook, string $key, \Closure $activateEntry): void
    {
        if (did_action($triggerHook) >= 1 || did_filter($triggerHook) >= 1) {
            $this->activateMatchingDeferredEntries([$key], $activateEntry, DeferredActivationSource::SchedulerImmediate);
            return;
        }

        $cb = null;
        $self = $this->self;
        add_filter($triggerHook, $cb = static function (mixed $value = null) use ($triggerHook, $key, $activateEntry, $self): mixed {
            $self = $self->get() ?? null;
            $entry = $self?->deferredHandlers[$key] ?? null;
            if ($entry === null) {
                remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
                return $value;
            }
            $activated = $self->activateMatchingDeferredEntries([$key], $activateEntry, DeferredActivationSource::Scheduler);
            if ($activated === 0) return $value;
            remove_filter($triggerHook, \Closure::getCurrent(), PHP_INT_MIN);
            unset($self->deferredHandlers[$key]);
            return $value;
        }, PHP_INT_MIN, 1);

        $logger = $this->logger;
        $this->deferredHandlers[$key]->setOnEntryRemovalRemoveSchedulerCallback(static function (DeferredHookEntryHandler $entry) use ($triggerHook, $cb, $logger): void {
            if (!\has_filter($triggerHook, $cb, PHP_INT_MIN)) return;
            \remove_filter($triggerHook, $cb, PHP_INT_MIN);
            SharedUtils::isDevelopment() && $logger->debug(__METHOD__, 'Removing deferred entry scheduler from: ' . $entry->handler->label);
        });
    }

    public function deferRegistration(
        HookRegistration $registration,
        DeferredHookEntryHandler $entry,
        callable $activateEntry,
    ): void {
        $triggerHook = $registration->deferRegisterUntilHook;
        if ($triggerHook instanceof \Closure) {
            try {
                $triggerHook = $this->planProvider->resolveHookName(
                    $registration->deferRegisterUntilHook,
                    $registration->deferRegisterUntilHookParams,
                    $entry->handler->label,
                    $registration->class
                );
            } catch (\RuntimeException $e) {
                $this->logger->error(
                    __METHOD__,
                    'Skipping hook for: ' . $entry->handler->label,
                    [
                        'message' => $e->getMessage(),
                        'trace'   => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'code' => $e->getCode(),
                    ]
                );
            }
        }
        $this->addDeferred($entry);
        if ($triggerHook === null) return;
        $this->scheduleDeferredActivation($triggerHook, $entry->toUniqueKey(), $activateEntry);
    }

    /**
     * Re-evaluate the registerIf registration gate when activating a deferred hook.
     *
     * The gate was already evaluated once in registerAll(); activation is the
     * moment the hook actually registers with WordPress, so it is re-checked
     * here. On failure the entry stays in the deferred pool (a later activation
     * attempt may succeed once the gate flips to true.
     *
     * @param DeferredHookEntryHandler $data Handler entry.
     */
    private function gateDeferredActivation(DeferredHookEntryHandler $data): bool
    {
        if (($data->registerIf ?? null) === null) {
            return true;
        }

        try {
            $allowed = $this->planProvider->evaluateRegistrationGate(
                $data->registerIf,
                $data->registerIfParams ?? [],
                $data->handler->label,
                $data->key->class
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                __METHOD__,
                'Skipping deferred hook activation: ' . $data->handler->label .  ' — error ' . $e->getMessage()
            );
            return false;
        }

        if (!$allowed) {
            $this->logger->info(
                __METHOD__,
                'Skipping deferred hook activation: ' . $data->handler->label .  ' — registerIf gate returned false.'
            );
            return false;
        }

        return true;
    }
}
