<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HookProviderTrait, HandlerEntryUniqueKey};
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\{HookRegistration, InstanceHookMetadata, HookKey};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker, RuntimeCallableHookInvoker, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    RuntimeRegistryHandlerEntry,
    ContainerRegistryHandlerEntry
};

/*======================================================================
 | Deferred Hook metadata DTOs
 ======================================================================*/

/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type AvailaibleHandlerType from HandlerEntryTrait
 * @phpstan-type DeferredHookEntry array{
 *     hook: string,
 *     handler: AvailaibleHandlerType,
 *     type: HookType['type'],
 *     priority: HookType['priority'],
 *     acceptedArgs: HookType['acceptedArgs'],
 *     tags: HookType['tags']|null,
 *     registerIf: HookType['registerIf'],
 *     registerIfParams: HookType['registerIfParams'],
 *     executeIf: HookType['executeIf'],
 *     executeIfParams: HookType['executeIfParams'],
 *     once: HookType['once'],
 *     onUnregisterDo: HookType['onUnregisterDo'],
 *     onUnregisterDoParams: HookType['onUnregisterDoParams'],
 *     key: HookKey,
 *     instance: WeakReference<object>|null
 * }>>
 * @extends parent<DeferredHookEntry>
 */
final readonly class DeferredHookEntryHandler extends AbstractDataObject
{
    public string $cachedHandlerLabel;

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
     */
    public function __construct(
        public string $hook,
        public ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $handler,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public ?array $tags,
        public ?\Closure $registerIf,
        public array $registerIfParams,
        public ?\Closure $executeIf,
        public array $executeIfParams,
        public bool $once,
        public ?\Closure $onUnregisterDo,
        public array $onUnregisterDoParams,
        public HookKey $key,
        public ?WeakReference $instance = null
    ) {
        $this->cachedHandlerLabel = $handler->label;
    }
    public static function fromContainerRegistryHandlerEntry(ContainerRegistryHandlerEntry $entry): self
    {
        return new static(
            hook: $entry->hook,
            key: $entry->key,
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
