<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use WPLokerBJM\Core\Container\Support\WPHooks\HandlerEntryUniqueKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HookProviderTrait};
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\{HookRegistration, InstanceHookMetadata, HookKey};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker, RuntimeCallableHookInvoker, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker};

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
 *  once: HookType['once']
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
     */
    public function __construct(
        public readonly string $hook,
        public readonly HookKey $key,
        public readonly ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker $handler,
        public readonly string $type,
        public readonly int $priority,
        public readonly int $acceptedArgs,
        public readonly array $tags,
        public readonly ?\Closure $registerIf,
        public readonly array $registerIfParams,
        public readonly ?\Closure $executeIf,
        public readonly array $executeIfParams,
        public readonly bool $once,
    ) {}
    public static function fromDeferredEntry(DeferredHookEntryHandler $deferredInstance): self
    {
        return new static(
            hook: $deferredInstance->hook,
            key: $deferredInstance->key,
            handler: $deferredInstance->handler,
            type: $deferredInstance->type,
            priority: $deferredInstance->priority,
            acceptedArgs: $deferredInstance->acceptedArgs,
            tags: $deferredInstance->tags,
            registerIf: $deferredInstance->registerIf,
            registerIfParams: $deferredInstance->registerIfParams,
            executeIf: $deferredInstance->executeIf,
            executeIfParams: $deferredInstance->executeIfParams,
            once: $deferredInstance->once,
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
 *     handler: RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker,
 *     hook: InstanceHookMetadataData['hook'],
 *     hookKey: HookKey,
 *     priority: InstanceHookMetadataData['priority'],
 *     type: InstanceHookMetadataData['type'],
 *     acceptedArgs: InstanceHookMetadataData['acceptedArgs'],
 *     owner: WeakReference<object>,
 *     callback?: callable
 * }
 */
final class RuntimeRegistryHandlerEntry
{
    /** @use HandlerEntryTrait<RuntimeHandlerEntry> */
    use HandlerEntryTrait;
    /**
     * @param RuntimeHandlerEntry['handler'] $handler
     * @param RuntimeHandlerEntry['hookKey'] $hookKey
     * @param RuntimeHandlerEntry['hook'] $hook
     * @param RuntimeHandlerEntry['priority'] $priority
     * @param RuntimeHandlerEntry['type'] $type
     * @param RuntimeHandlerEntry['acceptedArgs'] $acceptedArgs
     * @param RuntimeHandlerEntry['owner'] $owner
     * @param RuntimeHandlerEntry['callback'] $callback if using manual registerAction() or registerFilter() it will hold the callback, otherwise it will be null.
     */
    public function __construct(
        public readonly RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker|RuntimeCallableHookInvoker $handler,
        public readonly HookKey $hookKey,
        public readonly string $hook,
        public readonly int $priority,
        public readonly string $type,
        public readonly int $acceptedArgs,
        public readonly WeakReference $owner,
        public readonly mixed $callback = null,
    ) {}
}

/*======================================================================
 | Deferred Hook metadata DTOs
 ======================================================================*/
/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type AvailaibleHandlerType ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker
 * @phpstan-type DeferredHookEntry array{
 *     hook: string,
 *     handler: AvailaibleHandlerType,
 *     type: HookType['type'],
 *     priority: HookType['priority'],
 *     acceptedArgs: HookType['acceptedArgs'],
 *     tags: HookType['tags'],
 *     registerIf: HookType['registerIf'],
 *     registerIfParams: HookType['registerIfParams'],
 *     executeIf: HookType['executeIf'],
 *     executeIfParams: HookType['executeIfParams'],
 *     once: HookType['once'],
 *     key: HookKey,
 *     instance: WeakReference<object>
 * }>>
 * @extends parent<DeferredHookEntry>
 */
final readonly class DeferredHookEntryHandler extends AbstractDataObject
{
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
     * @param DeferredHookEntry['key'] $key
     * @param DeferredHookEntry['instance'] $instance Exclusive for @see WPHooksInstanceRegistry
     */
    public function __construct(
        public string $hook,
        public ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker $handler,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public array $tags,
        public ?\Closure $registerIf,
        public array $registerIfParams,
        public ?\Closure $executeIf,
        public array $executeIfParams,
        public bool $once,
        public HookKey $key,
        public ?WeakReference $instance = null
    ) {}
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
        );
    }
}
