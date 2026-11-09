<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;
use WPLokerBJM\Shared\Log\Logger;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\{HookRegistration, HookKey, Provider\WPHookPlanProvider};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\{HookPattern, HookTagUtilities};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\{ModuleClassHookMetadata};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait};
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    WPHooksContainerRegistry,
    HookTargetResolver,
};

/**
 * @internal not for external use beyond @see WPHooksContainerRegistry
 * 
 * @template TargetClass
 * @phpstan-type HookTargetResolve callable|string|array{class-string<TargetClass>&object<TargetClass>, callable-string<TargetClass>}
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type DeferredHookEntry from DeferredHooksTrait
 * @phpstan-import-type ActivateEntry from WPHooksContainerRegistry
 */

class ContainerRegistryDeferredHookManager
{
    use DeferredHooksTrait;

    /**
     * @param WPHookPlanProvider  $planProvider Plan provider used for registerIf gates.
     * @param ContainerInterface $container    Container used by gate closures.
     * @param HookTargetResolver $resolverTarget 
     * @param EntriesIndexer $entriesIndexer for fast lookup of matching entries
     */
    public function __construct(
        private WPHookPlanProvider $planProvider,
        private ContainerInterface $container,
        private HookTargetResolver $resolverTarget = new HookTargetResolver(),
        private EntriesIndexer $entriesIndexer = new EntriesIndexer()
    ) {}

    public function addDeferredEntry(DeferredHookEntryHandler $entry): void
    {
        $this->addDeferred($entry);
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
    public function activateDeferredByHook(string $hook, \Closure $activateEntry): void
    {
        $this->activateMatchingDeferredEntries(
            $this->entriesIndexer->byHook[$hook] ?? [],
            $activateEntry,
        );
    }

    /**
     * Activate all deferred handlers belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     */
    public function activateDeferredByClass(string $class, \Closure $activateEntry): void
    {
        $this->activateMatchingDeferredEntries(
            $this->entriesIndexer->byClass[$class] ?? [],
            $activateEntry,
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
    public function activateDeferredByCallable(callable|string|array $target, \Closure $activateEntry): void
    {
        $key = $this->entriesIndexer->getCallable($this->resolverTarget->resolve($target));

        $this->activateMatchingDeferredEntries(
            $this->entriesIndexer->byCallable[$key] ?? [],
            $activateEntry,
        );
    }

    /**
     * Activate all deferred handlers carrying at least one of the given tags.
     *
     * @param array<string> $tags Tag or list of tags to activate.
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
        $keys = [];
        foreach ($entries as $key => $value) {
            foreach ($value as $entry) {
                if (HookPattern::matchesAny($this->deferredHandlers[$entry]->tags, $patterns)) {
                    $keys[] = $entry;
                }
            }
        }

        return $this->activateMatchingDeferredEntries(
            $keys,
            $activateEntry,
        );
    }

    /**
     * Activate a single deferred handler by exact hook + key.
     *
     * One-shot entry point for deferRegisterUntilHook entries: the trigger
     * hook listener calls this when the trigger fires. registerIf is
     * re-evaluated as with every activation.
     * @internal
     * @param ActivateEntry $activateEntry Registry callback (see activateDeferredByHook).
     * @return bool True when the entry was newly activated; false when the
     *              entry is unknown, already active, or a gate rejected it.
     */
    public function activateDeferredByKey(DeferredHookEntryHandler $deferredHookEntry, \Closure $activateEntry): bool
    {
        $uniqueKey = $deferredHookEntry->toUniqueKey();
        if (!isset($this->deferredHandlers[$uniqueKey])) {
            return false;
        }

        $data = $this->deferredHandlers[$uniqueKey];

        // Registration gate: re-evaluated at activation time.
        if (!$this->gateDeferredActivation($data)) {
            return false;
        }

        $activated = $activateEntry($data);
        if (!$activated) return false;

        $this->entriesIndexer->removeIndexes($data);
        unset($this->deferredHandlers[$uniqueKey]);

        return true;
    }

    /**
     * Unregister a deferred hook using a first-class callable, array lookup,
     * or class-string identifier.
     *
     * @param HookTargetResolve $target
     */
    public function unregisterDeferredByCallable(callable|string|array $target): void
    {
        $keyCallable = $this->entriesIndexer->getCallable($this->resolverTarget->resolve($target));
        $entries = $this->entriesIndexer->byCallable[$keyCallable] ?? [];
        foreach ($entries as $key) {
            if (!isset($this->deferredHandlers[$key])) {
                continue;
            }
            $handler = $this->deferredHandlers[$key];
            $this->entriesIndexer->removeIndexes($handler);
            unset($this->deferredHandlers[$key]);
        }
    }

    /**
     * Unregister all deferred handlers for a specific WordPress hook name.
     *
     * @param string $hook WordPress hook name.
     */
    public function unregisterDeferredByHook(string $hook): void
    {
        $entries = $this->entriesIndexer->byHook[$hook] ?? [];
        foreach ($entries as $key) {
            if (!isset($this->deferredHandlers[$key])) {
                continue;
            }
            $handler = $this->deferredHandlers[$key];
            $this->entriesIndexer->removeIndexes($handler);
            unset($this->deferredHandlers[$key]);
        }
    }

    /**
     * Unregister all deferred hooks belonging to a specific service class.
     *
     * @param class-string $class Fully qualified class name.
     */
    public function unregisterDeferredByClass(string $class): void
    {
        $entries = $this->entriesIndexer->byClass[$class] ?? [];
        foreach ($entries as $key) {
            if (!isset($this->deferredHandlers[$key])) {
                continue;
            }
            $handler = $this->deferredHandlers[$key];
            $this->entriesIndexer->removeIndexes($handler);
            unset($this->deferredHandlers[$key]);
        }
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
    public function unregisterDeferredByNamespace(string $namespace): void
    {
        $entries = $this->entriesIndexer->byNamespace[$namespace] ?? [];
        foreach ($entries as $key) {
            $handler = $this->deferredHandlers[$key] ?? null;
            if ($handler === null) {
                continue;
            }
            if (!$handler->key->isWithinNamespace($namespace)) {
                continue;
            }
            $this->entriesIndexer->removeIndexes($handler);
            unset($this->deferredHandlers[$key]);
        }
    }

    /**
     * Unregister all deferred handlers carrying at least one of the given tags.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array<string> $tags Tag or list of tags to unregister.
     */
    public function unregisterDeferredByTags(array $tags): void
    {
        if ($tags === []) {
            return;
        }
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
                $this->entriesIndexer->removeIndexes($entry);
                unset($this->deferredHandlers[$key]);
            }
            unset($this->entriesIndexer->byTag[$tag]);
        }
    }

    /**
     * Unregister all deferred handlers whose hook name matches a wildcard pattern.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param string $pattern Wildcard hook-name pattern (see HookPattern).
     */
    public function unregisterDeferredByHookPattern(string $pattern): void
    {
        HookPattern::assertValid($pattern);
        $hooks = $this->entriesIndexer->byHook ?? [];
        foreach ($hooks as $hook) {
            foreach ($hook as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if (!($entry instanceof DeferredHookEntryHandler)) continue;
                if (HookPattern::matches($entry->hook, $pattern)) {
                    unset($this->deferredHandlers[$key]);
                    $this->entriesIndexer->removeIndexes($entry);
                    unset($this->entriesIndexer->byHook[$entry->hook]);
                }
            }
        }
    }

    /**
     * Unregister all deferred handlers carrying at least one tag matching any
     * of the given wildcard patterns.
     *
     * Only touches the deferred pool — active handlers are never affected.
     *
     * @param array<string> $patterns Tag wildcard patterns.
     */
    public function unregisterDeferredByTagPattern(array $patterns): void
    {
        if ($patterns === []) {
            return;
        }
        HookPattern::assertValidAll($patterns);
        $tags = $this->entriesIndexer->byTag ?? [];
        foreach ($tags as $tag => $keys) {
            foreach ($keys as $key) {
                $entry = $this->deferredHandlers[$key] ?? null;
                if (!($entry instanceof DeferredHookEntryHandler)) continue;
                if (HookPattern::matchesAny($entry->tags, $patterns)) {
                    unset($this->deferredHandlers[$key]);
                    $this->entriesIndexer->removeIndexes($entry);
                    unset($this->entriesIndexer->byTag[$tag]);
                }
            }
        }
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
                $this->container,
                $data->cachedHandlerLabel,
                $data->key->class
            );
        } catch (\Throwable $e) {
            Logger::error(
                self::class,
                'Skipping deferred hook activation: ' . $data->cachedHandlerLabel .  ' — error ' . $e->getMessage()
            );
            return false;
        }

        if (!$allowed) {
            Logger::info(
                self::class,
                'Skipping deferred hook activation: ' . $data->cachedHandlerLabel .  ' — registerIf gate returned false.'
            );
            return false;
        }

        return true;
    }
}
