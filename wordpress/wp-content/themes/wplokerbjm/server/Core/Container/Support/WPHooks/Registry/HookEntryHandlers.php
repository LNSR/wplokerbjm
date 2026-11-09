<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HandlerEntryUniqueKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HookProviderTrait};
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\{HookRegistration, InstanceHookMetadata, HookKey};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker, RuntimeCallableHookInvoker, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\DeferredHookEntryHandler;

/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-type HandlerEntry array{
 *  hook: string,
 *  key: HookKey,
 *  handler: ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker,
 *  type: HookType['type'],
 *  priority: HookType['priority'],
 *  acceptedArgs: HookType['acceptedArgs'],
 *  tags: HookType['tags'],
 *  registerIf: HookType['registerIf'],
 *  registerIfParams: HookType['registerIfParams'],
 *  executeIf: HookType['executeIf'],
 *  executeIfParams: HookType['executeIfParams'],
 *  once: HookType['once'],
 *  onUnregisterDo: HookType['onUnregisterDo'],
 *  onUnregisterDoParams: HookType['onUnregisterDoParams'],
 * }
 */
final class ContainerRegistryHandlerEntry
{
    /** @use HandlerEntryTrait<HandlerEntry> */
    use HandlerEntryTrait;
    use HandlerEntryUniqueKey;

    /**
     * @param HandlerEntry['hook'] $hook
     * @param HandlerEntry['key'] $key
     * @param HandlerEntry['handler'] $handler
     * @param HandlerEntry['type'] $type
     * @param HandlerEntry['priority'] $priority
     * @param HandlerEntry['acceptedArgs'] $acceptedArgs
     * @param HandlerEntry['tags'] $tags
     * @param HandlerEntry['registerIf'] $registerIf
     * @param HandlerEntry['registerIfParams'] $registerIfParams
     * @param HandlerEntry['executeIf'] $executeIf
     * @param HandlerEntry['executeIfParams'] $executeIfParams
     * @param HandlerEntry['once'] $once
     * @param HandlerEntry['onUnregisterDo'] $onUnregisterDo
     */
    public function __construct(
        public readonly string $hook,
        public readonly HookKey $key,
        ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker $handler,
        public readonly string $type,
        public readonly int $priority,
        public readonly int $acceptedArgs,
        public readonly array $tags,
        public readonly ?\Closure $registerIf,
        public readonly array $registerIfParams,
        public readonly ?\Closure $executeIf,
        public readonly array $executeIfParams,
        public readonly bool $once,
        public readonly ?\Closure $onUnregisterDo,
        public readonly array $onUnregisterDoParams
    ) {
        $this->handler = $handler;
        $this->invokerLabel = $handler->label;
    }
    public static function fromDeferredEntry(DeferredHookEntryHandler $deferredEntry): self
    {
        return new static(
            hook: $deferredEntry->hook,
            key: $deferredEntry->key,
            handler: $deferredEntry->handler,
            type: $deferredEntry->type,
            priority: $deferredEntry->priority,
            acceptedArgs: $deferredEntry->acceptedArgs,
            tags: $deferredEntry->tags,
            registerIf: $deferredEntry->registerIf,
            registerIfParams: $deferredEntry->registerIfParams,
            executeIf: $deferredEntry->executeIf,
            executeIfParams: $deferredEntry->executeIfParams,
            once: $deferredEntry->once,
            onUnregisterDo: $deferredEntry->onUnregisterDo,
            onUnregisterDoParams: $deferredEntry->onUnregisterDoParams,
        );
    }
    public function toDeferredEntryDTO(): DeferredHookEntryHandler
    {
        return DeferredHookEntryHandler::fromContainerRegistryHandlerEntry($this);
    }
}

/**
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type RuntimeHandlerEntry array{
 *     hook: InstanceHookMetadataData['hook'],
 *     handler: RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker,
 *     type: InstanceHookMetadataData['type'],
 *     priority: InstanceHookMetadataData['priority'],
 *     acceptedArgs: InstanceHookMetadataData['acceptedArgs'],
 *     registerIf: InstanceHookMetadataData['registerIf'],
 *     registerIfParams: InstanceHookMetadataData['registerIfParams'],
 *     executeIf: InstanceHookMetadataData['executeIf'],
 *     executeIfParams: InstanceHookMetadataData['executeIfParams'],
 *     once: InstanceHookMetadataData['once'],
 *     onUnregisterDo: InstanceHookMetadataData['onUnregisterDo'],
 *     onUnregisterDoParams: InstanceHookMetadataData['onUnregisterDoParams'],
 *     hookKey: HookKey,
 *     owner: WeakReference<object>,
 *     callback?: callable
 * }
 */
final class RuntimeRegistryHandlerEntry
{
    /** @use HandlerEntryTrait<RuntimeHandlerEntry> */
    use HandlerEntryTrait;
    /**
     * @param RuntimeHandlerEntry['hook'] $hook
     * @param RuntimeHandlerEntry['handler'] $handler
     * @param RuntimeHandlerEntry['type'] $type
     * @param RuntimeHandlerEntry['priority'] $priority
     * @param RuntimeHandlerEntry['acceptedArgs'] $acceptedArgs
     * @param RuntimeHandlerEntry['registerIf'] $registerIf
     * @param RuntimeHandlerEntry['registerIfParams'] $registerIfParams
     * @param RuntimeHandlerEntry['executeIf'] $executeIf
     * @param RuntimeHandlerEntry['executeIfParams'] $executeIfParams
     * @param RuntimeHandlerEntry['once'] $once
     * @param RuntimeHandlerEntry['onUnregisterDo'] $onUnregisterDo
     * @param RuntimeHandlerEntry['onUnregisterDoParams'] $onUnregisterDoParams
     * @param RuntimeHandlerEntry['hookKey'] $hookKey
     * @param RuntimeHandlerEntry['owner'] $owner
     * @param RuntimeHandlerEntry['callback'] $callback if using manual registerAction() or registerFilter() it will hold the callback, otherwise it will be null.
     */
    public function __construct(
        public readonly string $hook,
        RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker $handler,
        public readonly string $type,
        public readonly int $priority,
        public readonly int $acceptedArgs,
        public readonly ?\Closure $registerIf,
        public readonly array $registerIfParams,
        public readonly ?\Closure $executeIf,
        public readonly array $executeIfParams,
        public readonly bool $once,
        public readonly ?\Closure $onUnregisterDo,
        public readonly array $onUnregisterDoParams,
        public readonly HookKey $hookKey,
        public readonly ?WeakReference $owner = null,
        public readonly mixed $callback = null,
    ) {
        $this->handler = $handler;
        $this->invokerLabel = $handler->label;
    }

    public function toDeferredEntryDTO(): DeferredHookEntryHandler
    {
        return DeferredHookEntryHandler::fromRuntimeRegistryHandlerEntry($this);
    }
    public static function fromDeferredEntryDTO(DeferredHookEntryHandler $entry): self
    {
        return new static(
            hook: $entry->hook,
            handler: $entry->handler,
            type: $entry->type,
            priority: $entry->priority,
            acceptedArgs: $entry->acceptedArgs,
            registerIf: $entry->registerIf,
            registerIfParams: $entry->registerIfParams,
            executeIf: $entry->executeIf,
            executeIfParams: $entry->executeIfParams,
            once: $entry->once,
            onUnregisterDo: $entry->onUnregisterDo,
            onUnregisterDoParams: $entry->onUnregisterDoParams,
            hookKey: $entry->key,
            owner: $entry->instance
        );
    }
}

