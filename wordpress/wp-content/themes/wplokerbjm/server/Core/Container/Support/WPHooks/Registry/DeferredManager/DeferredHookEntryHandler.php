<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HandlerEntryUniqueKey};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\{HookRegistration, InstanceHookMetadata, HookKey};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    RuntimeRegistryHandlerEntry,
    ContainerRegistryHandlerEntry
};
use WPLokerBJM\Shared\Utilities\DataObject\TraitDataObject;

/*======================================================================
 | Deferred Hook metadata DTOs
 ======================================================================*/

/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-import-type AvailaibleHandlerType from HandlerEntryTrait
 * @phpstan-type DeferredHookEntry array{
 *     hook: string,
 *     handler: AvailaibleHandlerType,
 *     type: 'action'|'filter',
 *     priority: int,
 *     acceptedArgs: int,
 *     tags: ?array,
 *     registerIf: \Closure(...): bool,
 *     registerIfParams: CallablePlan,
 *     executeIf: \Closure(...): bool,
 *     executeIfParams: CallablePlan,
 *     once: bool,
 *     onUnregisterDo: \Closure(...): void,
 *     onUnregisterDoParams: CallablePlan,
 *     key: HookKey,
 *     instance: WeakReference<object>|null,
 *     removeEntryIndexCallback: (\Closure(static $entry): void)|null,
 *     removeAutoSchedulerCallback: (\Closure(static $entry): void)|null,
 * }>>
 */
final class DeferredHookEntryHandler
{
    /** @use TraitDataObject<DeferredHookEntry> */
    use TraitDataObject;
    use HandlerEntryUniqueKey;
    /**
     * @param DeferredHookEntry['hook'] $hook
     * @param DeferredHookEntry['handler'] $handler
     * @param DeferredHookEntry['type'] $type
     * @param DeferredHookEntry['priority'] $priority
     * @param DeferredHookEntry['acceptedArgs'] $acceptedArgs
     * @param DeferredHookEntry['tags'] $tags
     * @param DeferredHookEntry['registerIf'] $registerIf
     * @param DeferredHookEntry['registerIfParams'] $registerIfParams
     * @param DeferredHookEntry['executeIf'] $executeIf
     * @param DeferredHookEntry['executeIfParams'] $executeIfParams
     * @param DeferredHookEntry['once'] $once
     * @param DeferredHookEntry['onUnregisterDo'] $onUnregisterDo
     * @param DeferredHookEntry['onUnregisterDoParams'] $onUnregisterDoParams
     * @param DeferredHookEntry['key'] $key
     * @param DeferredHookEntry['instance'] $instance Exclusive for @see WPHooksInstanceRegistry owner
     * @param DeferredHookEntry['removeEntryIndexCallback'] $removeEntryIndexCallback
     * @param DeferredHookEntry['removeAutoSchedulerCallback'] $removeAutoSchedulerCallback
     */
    public function __construct(
        public readonly string $hook,
        public readonly ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $handler,
        public readonly string $type,
        public readonly int $priority,
        public readonly int $acceptedArgs,
        public readonly ?array $tags,
        public readonly ?\Closure $registerIf,
        public readonly array $registerIfParams,
        public readonly ?\Closure $executeIf,
        public readonly array $executeIfParams,
        public readonly bool $once,
        public readonly ?\Closure $onUnregisterDo,
        public readonly array $onUnregisterDoParams,
        public readonly HookKey $key,
        public readonly ?WeakReference $instance = null,
        private ?\Closure $removeEntryIndexCallback = null,
        private ?\Closure $removeAutoSchedulerCallback = null,
    ) {}

    public function __destruct()
    {
        \is_callable($this->removeEntryIndexCallback) && ($this->removeEntryIndexCallback)($this);
        \is_callable($this->removeAutoSchedulerCallback) && ($this->removeAutoSchedulerCallback)($this);
        SharedUtils::isDevelopment() && Logger::debug(__METHOD__, "Deferred entry destroyed; removing entry indexes: ", [
            'invokerLabel' => $this->handler->label,
        ]);
        unset($this->removeEntryIndexCallback, $this->removeAutoSchedulerCallback);
    }

    /** @param DeferredHookEntry['removeEntryIndexCallback'] $callback */
    public function setOnEntryRemovalRemoveIndexesCallback(\Closure $callback): void
    {
        $this->removeEntryIndexCallback = $callback;
    }

    /** @param DeferredHookEntry['removeAutoSchedulerCallback'] $callback */
    public function setOnEntryRemovalRemoveSchedulerCallback(\Closure $callback): void
    {
        $this->removeAutoSchedulerCallback = $callback;
    }

    public static function fromContainerRegistryHandlerEntry(ContainerRegistryHandlerEntry $entry): self
    {
        return new static(
            hook: $entry->hook,
            key: $entry->key,
            handler: $entry->handler,
            type: $entry->type,
            priority: $entry->priority,
            acceptedArgs: $entry->acceptedArgs,
            tags: $entry->tags,
            registerIf: $entry->registerIf,
            registerIfParams: $entry->registerIfParams,
            executeIf: $entry->executeIf,
            executeIfParams: $entry->executeIfParams,
            once: $entry->once,
            onUnregisterDo: $entry->onUnregisterDo,
            onUnregisterDoParams: $entry->onUnregisterDoParams,
        );
    }

    public static function fromRuntimeRegistryHandlerEntry(RuntimeRegistryHandlerEntry $entry): self
    {
        return new static(
            hook: $entry->hook,
            key: $entry->hookKey,
            handler: $entry->handler ?? null,
            type: $entry->type,
            priority: $entry->priority,
            acceptedArgs: $entry->acceptedArgs,
            tags: [],
            registerIf: $entry->registerIf,
            registerIfParams: $entry->registerIfParams,
            executeIf: $entry->executeIf,
            executeIfParams: $entry->executeIfParams,
            once: $entry->once,
            onUnregisterDo: $entry->onUnregisterDo,
            onUnregisterDoParams: $entry->onUnregisterDoParams,
            instance: $entry->owner
        );
    }
}
