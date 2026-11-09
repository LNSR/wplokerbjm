<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Janitor;

use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{
    ContainerLazyHookInvoker,
    ContainerLazyPropertyHookInvoker,
    RuntimeInstanceHookInvoker,
    RuntimeCallableHookInvoker,
    RuntimeInstancePropertyHookInvoker,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{ContainerRegistryHandlerEntry, RuntimeRegistryHandlerEntry};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * @template Invokers of ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker
 */
class QueuedHooksCleaner
{
    public private(set) bool $schedulerCleanerDispatched = false;

    /** @var list<ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry> */
    private array $queuedRemovalEntry = [];

    /** @var \WeakMap<Invokers, ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry> */
    private \WeakMap $entries;
    private int $priority = \PHP_INT_MIN;

    public function __construct()
    {
        $this->entries = new \WeakMap();
    }


    /** @param list<ContainerRegistryHandlerEntry> $entries */
    public function addContainerRegistryItsEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            if (!($entry->handler?->once ?? false)) continue;
            $this->entries[$entry->handler] = $entry;
        }
    }

    /** @param list<RuntimeRegistryHandlerEntry> $entries */
    public function addRuntimeRegistryItsEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            if ($entry->handler === null) continue;
            $this->entries[$entry->handler] = $entry;
        }
    }

    public function immediatelyScheduleRemove(ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry $entry): void
    {
        if ($entry->handler === null) return;
        $this->entries[$entry->handler] = $entry;
        $this->cleanUpCallback($entry->handler);
    }

    public function getEntry(ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $invoker): ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry|null
    {
        return $this->entries[$invoker] ?? null;
    }

    public function cleanUpCallback(ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $invoker): void
    {
        $entry = $this->entries[$invoker] ?? null;
        if ($entry === null) {
            return;
        }
        $hasCallbackRegistered = $entry->hasCallbackRegisteredOnWordpress();
        switch ($entry->isCurrentlyExecuting() && $hasCallbackRegistered) {
            case true:
                $this->queuedRemovalEntry[] = $entry;

                /**
                 * 'all' hook is the only place inbetween end of last priority hook stack and beginning of new named hook dispatch
                 * * 'all' run before named hook
                 */
                !$this->schedulerCleanerDispatched && $this->allHookActionCommand('add');

                /**
                 * This ideal during sequential adjacent `once` priorities hook
                 * * Make sure previous priority wiped at next priority order to prevent chance of re-entrancy despite invoker logically guarded
                 */
                ($this->scheduleCleanup)($invoker);
                break;
            default:
                $result = $entry->unregister();
                if (!$result && $hasCallbackRegistered) {
                    $this->queuedRemovalEntry[] = $entry;
                    !$this->schedulerCleanerDispatched && $this->allHookActionCommand('add');
                    break;
                }
                ($this->scheduleCleanup)($invoker);
                $result && SharedUtils::isDevelopment() && Logger::debug(static::class, 'Handler cleaned immediately: ', [
                    'InvokerLabel' => $invoker->label,
                    'CurrentHookContext' => current_filter(),
                ]);
                break;
        }
        unset($this->entries[$invoker]);
    }

    /** 
     * @param 'add'|'remove'|'has' $command 
     * @return bool
     * */
    private function allHookActionCommand(string $command): bool
    {
        switch ($command) {
            case 'add':
                return (bool) \add_action('all', $this->scheduleCleanup, $this->priority, 0);
            case 'remove':
                return (bool) \remove_action('all', $this->scheduleCleanup, $this->priority);
            case 'has':
                return (bool) \has_action('all', $this->scheduleCleanup, $this->priority);
            default:
                throw new \InvalidArgumentException('Argument $command only allow add, remove, has but got: ' . $command);
        }
    }

    private \Closure $scheduleCleanup {
        get {
            return $this->scheduleCleanup ??= function (mixed ...$args): void {
                $this->schedulerCleanerDispatched = $this->allHookActionCommand('has');
                $invoker = null;
                if (
                    array_key_exists(0, $args) && (
                        //Container Invokers
                        $args[0] instanceof ContainerLazyHookInvoker
                        || $args[0] instanceof ContainerLazyPropertyHookInvoker
                        //RuntimeInstance Invokers
                        || $args[0] instanceof RuntimeCallableHookInvoker
                        || $args[0] instanceof RuntimeInstanceHookInvoker
                        || $args[0] instanceof RuntimeInstancePropertyHookInvoker
                    )
                ) {
                    $invoker = $args[0];
                }

                /**
                 * * 'all' hook is in different active stack it's ok to indiscriminately remove invokers immediately
                 */
                foreach ($this->queuedRemovalEntry as $idx => $entry) {
                    if ($invoker !== null && $entry->handler === $invoker) {
                        continue;
                    }
                    $capturedLabel = $entry->handler?->label ?? null;
                    $result = $entry->unregister();

                    SharedUtils::isDevelopment() && Logger::debug(static::class, 'Handler cleaned at: ', [
                        'CleanedEntryHandler' => $capturedLabel ?: "Invoker gone before cleanup. (cachedLabel: {$entry->invokerLabel})",
                        'CleanedAtInvokersLabel' => $invoker?->label
                            ? "via {$invoker?->label} (" . ($invoker?->isDestructed ? 'via destructor' : 'via cleanup') . ")"
                            : 'all-hook scheduler',
                        'CurrentHookContext' => \current_filter(),
                        'resultUnregistration' => $result,
                    ]);

                    if (
                        $entry->handler === null ||
                        $result ||
                        !$entry->hasCallbackRegisteredOnWordpress() ||
                        $entry->unregistrationFailureCount >= 3
                    ) {
                        unset($this->queuedRemovalEntry[$idx]);
                    }
                }
                if ($this->queuedRemovalEntry !== []) return;
                if ($this->schedulerCleanerDispatched) $this->allHookActionCommand('remove') && $this->schedulerCleanerDispatched = false;
                // \gc_collect_cycles(); // make sure circular-reference, or self-reference purged
            };
        }
    }
}
