<?php

namespace WPLokerBJM\Core\Container\Support\WPHooks\Janitor;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\UnregistrationSource;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\{ContainerRegistryHandlerEntry, RuntimeRegistryHandlerEntry};
use WPLokerBJM\Shared\Utilities\SharedUtils;

class QueuedHooksCleaner
{
    public bool $schedulerCleanerDispatched { get => $this->scheduler?->schedulerCleanerDispatched ?? false; }

    public function __construct(
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {}

    public function scheduleCleanUpCallback(ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry $entry): void
    {
        if ($entry->handler === null) return;
        $hasCallbackRegistered = $entry->hasCallbackRegisteredOnWordpress();
        switch ($entry->isCurrentlyExecuting() && $hasCallbackRegistered) {
            case true:
                $this->scheduler::$queuedRemovalEntry[$entry->handler] = $entry;

                /**
                 * 'all' hook is the only place inbetween end of last priority hook stack and beginning of new named hook dispatch
                 * * 'all' run before named hook
                 */
                !$this->scheduler->schedulerCleanerDispatched && $this->scheduler->allHookActionCommand('add');

                /**
                 * This is ideal for sequential adjacent `once` priorities on the same hook:
                 * wipe the previous priority's callback at the next priority, closing the
                 * re-entrancy window even though the invoker is already logically guarded.
                 */
                ($this->scheduler)($entry->handler);
                break;
            default:
                $handler = $entry->handler; // hold temporary until out of scope
                $result = $entry->unregister(UnregistrationSource::Cleaner);

                if ($result) {
                    SharedUtils::isDevelopment() && $this->logger->debug(static::class, 'Handler cleaned immediately: ', [
                        'InvokerLabel' => $handler->label,
                        'CurrentHookContext' => current_filter(),
                        'resultUnregistration' => $result,
                        'hasCallbackRegisteredOnWordpress' => $hasCallbackRegistered,
                    ]);
                } elseif (!$result && $hasCallbackRegistered) {
                    $this->scheduler::$queuedRemovalEntry[$handler] = $entry;
                    !$this->scheduler->schedulerCleanerDispatched && $this->scheduler->allHookActionCommand('add');
                }

                ($this->scheduler)($handler); // drain opportunity
                break;
        }
    }

    /** 
     * @var class@anonymous::class
     * Independent scheduler to prevent reference cycle back to cleaner
     */
    protected private(set) ?object $scheduler = null {
        get {
            return $this->scheduler ??= new class($this->logger, __CLASS__, __PROPERTY__) {
                /** @var \WeakMap<HookInvoker, ContainerRegistryHandlerEntry|RuntimeRegistryHandlerEntry> */
                public static \WeakMap $queuedRemovalEntry;
                public private(set) bool $schedulerCleanerDispatched = false;
                public const int HOOK_PRIORITY = \PHP_INT_MIN;

                private int $dispatchDepth = 0;

                public function __construct(private HookLoggerInterface $logger, private string $outerclass, private string $outerProperty)
                {
                    self::$queuedRemovalEntry ??= new \WeakMap();
                }

                public function __destruct()
                {
                    // last line of defense, when transient registry and its services cease to exist
                    // $wp_filter will become sole owner of this scheduler refcount.
                    // released after self-unregistration at all-hook's end.
                    // normally unneeded and negligble on normal DI singleton path.
                    \gc_collect_cycles();
                }

                public function __invoke(mixed ...$args): void
                {
                    if ($this->dispatchDepth > 0) return;
                    try {
                        $this->dispatchDepth++;
                        $this->schedulerCleanerDispatched = $this->allHookActionCommand('has');
                        if (\count(self::$queuedRemovalEntry) === 0) return;
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
                        foreach (self::$queuedRemovalEntry as $ivk => $entry) {
                            if ($invoker instanceof HookInvoker && $ivk === $invoker) {
                                continue;
                            }
                            $hasCallbackRegistered = $entry->hasCallbackRegisteredOnWordpress();
                            $result = $entry->unregister(UnregistrationSource::Cleaner);

                            SharedUtils::isDevelopment() && $this->logger->debug($this->outerclass . '::$' . $this->outerProperty . '::' . __FUNCTION__, 'Handler cleaned at: ', [
                                'CleanedEntryHandler' => $ivk->label,
                                'CleanedAtInvokersLabel' => $invoker?->label
                                    ? "via {$invoker?->label} (" . ($invoker?->isDestructed ? 'via destructor' : 'via cleanup') . ")"
                                    : 'all-hook scheduler',
                                'unregistrationFailureCount' => $entry->unregistrationFailureCount,
                                'hasCallbackRegisteredOnWordpressBeforeClean' => $hasCallbackRegistered,
                                'CurrentHookContext' => \current_filter(),
                                'resultUnregistration' => $result,
                            ]);

                            if (
                                $result ||
                                !$hasCallbackRegistered ||
                                $entry->unregistrationFailureCount >= 3
                            ) {
                                unset(self::$queuedRemovalEntry[$ivk]);
                            }
                        }
                        if (\count(self::$queuedRemovalEntry) !== 0) return;
                        if ($this->schedulerCleanerDispatched) $this->allHookActionCommand('remove') && $this->schedulerCleanerDispatched = false;
                    } finally {
                        $this->dispatchDepth--;
                    }
                }
                /** 
                 * @param 'add'|'remove'|'has' $command 
                 * @return bool
                 * */
                public function allHookActionCommand(string $command): bool
                {
                    switch ($command) {
                        case 'add':
                            return (bool) \add_action('all', $this, self::HOOK_PRIORITY, 0);
                        case 'remove':
                            return (bool) \remove_action('all', $this, self::HOOK_PRIORITY);
                        case 'has':
                            return (bool) \has_action('all', $this, self::HOOK_PRIORITY);
                        default:
                            throw new \InvalidArgumentException('Argument $command only allow add, remove, has but got: ' . $command);
                    }
                }
            };
        }
    }
}
