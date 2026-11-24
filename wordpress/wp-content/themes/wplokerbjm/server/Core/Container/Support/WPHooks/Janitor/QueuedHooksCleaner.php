<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Janitor;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\UnregistrationSource;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\{ContainerRegistryHandlerEntry, RuntimeRegistryHandlerEntry};
use WPLokerBJM\Shared\Utilities\SharedUtils;

class QueuedHooksCleaner
{
    public private(set) bool $schedulerCleanerDispatched = false;

    /** @var \WeakMap<HookInvoker, ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry> */
    protected \WeakMap $queuedRemovalEntry;
    private int $priority = \PHP_INT_MIN;
    /** @var \WeakReference<self> */
    private \WeakReference $self;

    public function __construct(
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {
        $this->queuedRemovalEntry = new \WeakMap();
        $this->self = \WeakReference::create($this);
    }

    public function __destruct()
    {
        $this->allHookActionCommand('remove');
        $this->scheduleCleanup = null;
    }

    public function scheduleCleanUpCallback(ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry $entry): void
    {
        if ($entry->handler === null) return;
        $hasCallbackRegistered = $entry->hasCallbackRegisteredOnWordpress();
        switch ($entry->isCurrentlyExecuting() && $hasCallbackRegistered) {
            case true:
                $this->queuedRemovalEntry[$entry->handler] = $entry;

                /**
                 * 'all' hook is the only place inbetween end of last priority hook stack and beginning of new named hook dispatch
                 * * 'all' run before named hook
                 */
                !$this->schedulerCleanerDispatched && $this->allHookActionCommand('add');

                /**
                 * This is ideal for sequential adjacent `once` priorities on the same hook:
                 * wipe the previous priority's callback at the next priority, closing the
                 * re-entrancy window even though the invoker is already logically guarded.
                 */
                ($this->scheduleCleanup)($entry->handler);
                break;
            default:
                $result = $entry->unregister(UnregistrationSource::Cleaner);
                if (!$result && $hasCallbackRegistered) {
                    $this->queuedRemovalEntry[$entry->handler] = $entry;
                    !$this->schedulerCleanerDispatched && $this->allHookActionCommand('add');
                    break;
                }
                ($this->scheduleCleanup)($entry->handler);
                $result && SharedUtils::isDevelopment() && $this->logger->debug(static::class, 'Handler cleaned immediately: ', [
                    'InvokerLabel' => $entry->handler?->label,
                    'CurrentHookContext' => current_filter(),
                ]);
                break;
        }
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

    private ?\Closure $scheduleCleanup = null {
        get {
            if ($this->scheduleCleanup !== null && $this->self->get() === $this) return $this->scheduleCleanup;
            $self = $this->self;
            return $this->scheduleCleanup = static function (mixed ...$args) use ($self): void {
                $self = $self->get();
                if ($self === null) return;
                $self->schedulerCleanerDispatched = $self->allHookActionCommand('has');
                $invoker = null;
                if (
                    array_key_exists(0, $args)
                    && $args[0] instanceof HookInvoker
                ) {
                    $invoker = $args[0];
                }

                /**
                 * * 'all' hook is in different active stack it's ok to indiscriminately remove invokers immediately
                 */
                foreach ($self->queuedRemovalEntry as $ivk => $entry) {
                    if ($ivk === $invoker) {
                        continue;
                    }
                    $result = $entry->unregister(UnregistrationSource::Cleaner);

                    SharedUtils::isDevelopment() && $self->logger->debug(static::class, 'Handler cleaned at: ', [
                        'CleanedEntryHandler' => $ivk->label,
                        'CleanedAtInvokersLabel' => $invoker?->label
                            ? "via {$invoker?->label} (" . ($invoker?->isDestructed ? 'via destructor' : 'via cleanup') . ")"
                            : 'all-hook scheduler',
                        'unregistrationFailureCount' => $entry->unregistrationFailureCount,
                        'CurrentHookContext' => \current_filter(),
                        'resultUnregistration' => $result,
                    ]);

                    if (
                        $result ||
                        !$entry->hasCallbackRegisteredOnWordpress() ||
                        $entry->unregistrationFailureCount >= 3
                    ) {
                        unset($self->queuedRemovalEntry[$ivk]);
                    }
                }
                if ($self->queuedRemovalEntry !== []) return;
                if ($self->schedulerCleanerDispatched) $self->allHookActionCommand('remove') && $self->schedulerCleanerDispatched = false;
                // \gc_collect_cycles(); // make sure circular-reference, or self-reference purged
            };
        }
    }
}
