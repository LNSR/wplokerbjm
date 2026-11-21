<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers;

use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\{ContainerRegistryHandlerEntry, RuntimeRegistryHandlerEntry};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HandlerEntryUniqueKey};
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\{HookRegistration, InstanceHookMetadata, HookKey};
use WPLokerBJM\Shared\Utilities\DataObject\TraitDataObject;

/*======================================================================
 | Deferred Hook metadata DTOs
 ======================================================================*/

/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-type DeferredHookEntry array{
 *     hook: string,
 *     handler: HookInvoker,
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
     * @param DeferredHookEntry['onEntryRemovalCallback'] $onEntryRemovalCallback
     */
    public function __construct(
        public readonly string $hook,
        public readonly HookInvoker $handler,
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
        private ?\Closure $onEntryRemovalCallback = null,
        public readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {}

    public function __destruct()
    {
        \is_callable($this->onEntryRemovalCallback) && ($this->onEntryRemovalCallback)($this);
        unset($this->onEntryRemovalCallback);
    }

    /** @param DeferredHookEntry['removeAutoSchedulerCallback'] $callback */
    public function setOnEntryRemovalCallback(\Closure $callback): void
    {
        $this->onEntryRemovalCallback = $callback;
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
            logger: $entry->logger,
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
            tags: $entry->tags,
            registerIf: $entry->registerIf,
            registerIfParams: $entry->registerIfParams,
            executeIf: $entry->executeIf,
            executeIfParams: $entry->executeIfParams,
            once: $entry->once,
            onUnregisterDo: $entry->onUnregisterDo,
            onUnregisterDoParams: $entry->onUnregisterDoParams,
            instance: $entry->owner,
            logger: $entry->logger,
        );
    }
}
