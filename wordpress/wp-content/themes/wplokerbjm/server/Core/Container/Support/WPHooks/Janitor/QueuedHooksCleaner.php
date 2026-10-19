<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Janitor;

use InvokersID;
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
    public array $queuedRemovalEntry = [];

    /** @var \WeakMap<Invokers, ContainerRegistryHandlerEntry&RuntimeRegistryHandlerEntry> */
    private \WeakMap $entries;
    private int $priority;

    public function __construct()
    {
        $this->entries = new \WeakMap();
    }


    /** @param list<ContainerRegistryHandlerEntry> $entries */
    public function addContainerRegistryItsEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            if (!$entry->handler->once) continue;
            $this->entries[$entry->handler] = $entry;
        }
    }

    /** @param list<RuntimeRegistryHandlerEntry> $entries */
    public function addRuntimeRegistryItsEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            $this->entries[$entry->handler] = $entry;
        }
    }

    public function immediatelyScheduleRemove(ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry $entry): void
    {
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

        switch ($entry->isCurrentlyExecuting()) {
            case true:
                $this->queuedRemovalEntry[] = $entry;

                /**
                 * 'all' hook is the only place inbetween end of last priority hook stack and beginning of new named hook dispatch
                 * * 'all' run before named hook
                 */
                !$this->schedulerCleanerDispatched && \add_action('all', $this->scheduleCleanup, $this->priority = \mt_rand(\PHP_INT_MIN, -20000));

                /**
                 * This ideal during sequential adjacent `once` priorities hook
                 * * Make sure previous priority wiped at next priority order to prevent chance of re-entrancy despite invoker logically guarded
                 */
                ($this->scheduleCleanup)($invoker);
                break;
            default:
                $entry->unregister();
                break;
        }
        unset($this->entries[$invoker]);
    }

    private \Closure $scheduleCleanup {
        get => $this->scheduleCleanup ??= function (mixed ...$args): void {
            $this->schedulerCleanerDispatched = true;
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

            $numberCleaned = 0;
            foreach ($this->queuedRemovalEntry as $idx => $entry) {
                /**
                 * * 'all' hook is in different active inner call stack it's ok to remove previous handler(invoker) immediately
                 */
                if ($invoker !== null && $entry->handler === $invoker) {
                    continue;
                }
                $entry->unregister();
                SharedUtils::isDevelopment() && Logger::info(__FUNCTION__, 'Unregistering queued entry: ', [
                    'invokerLabel' => $invoker?->label ?? 'No label, this mean run from ALL hook',
                    'currentHookNameRunInAllContext' => \current_filter(),
                    'unregisteredEntry' => $entry->handler->label
                ]);
                unset($this->queuedRemovalEntry[$idx]);
                $numberCleaned++;
            }
            $numberCleaned > 0 && SharedUtils::isDevelopment() && Logger::info(__FUNCTION__, 'Cleaned ' . $numberCleaned . ' queued entries.');
            if ($this->queuedRemovalEntry !== []) return;
            \remove_action('all', \Closure::getCurrent(), $this->priority);
            $this->schedulerCleanerDispatched = false;
        };
    }
}
