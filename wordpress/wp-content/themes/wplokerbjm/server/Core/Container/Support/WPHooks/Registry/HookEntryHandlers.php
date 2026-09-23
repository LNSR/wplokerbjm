<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HookProviderTrait};
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\{HookRegistration, DeferredHookEntryDTO, InstanceHookMetadata, HookKey};
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookHandler, ContainerLazyPropertyHookHandler, RuntimeCallableHookHandler, RuntimeInstanceHookHandler, RuntimeInstancePropertyHookHandler};

/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-type HandlerEntry array{
 *  hook: string,
 *  key: HookKey,
 *  handler: ContainerLazyHookHandler|ContainerLazyPropertyHookHandler,
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
 * @extends parent<HandlerEntry>
 */
final readonly class ContainerRegistryHandlerEntry extends AbstractDataObject
{
    use HandlerEntryTrait;

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
        public string $hook,
        public HookKey $key,
        public ContainerLazyHookHandler|ContainerLazyPropertyHookHandler $handler,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public array $tags,
        public ?\Closure $registerIf,
        public array $registerIfParams,
        public ?\Closure $executeIf,
        public array $executeIfParams,
        public bool $once,
    ) {}
    public function __toString(): string
    {
        return $this->toUniqueKey();
    }
    public static function fromDeferredEntry(DeferredHookEntryDTO $deferredInstance): self
    {
        return new self(
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
    public function toDeferredEntryDTO(): DeferredHookEntryDTO
    {
        return DeferredHookEntryDTO::fromContainerRegistryHandlerEntry($this);
    }
    public function toUniqueKey(): string
    {
        return $this->hook . ':' . $this->key->toString();
    }
}

/**
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type RuntimeHandlerEntry array{
 *     handler: RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler,
 *     hook: InstanceHookMetadataData['hook'],
 *     priority: InstanceHookMetadataData['priority'],
 *     type: InstanceHookMetadataData['type'],
 *     acceptedArgs: InstanceHookMetadataData['acceptedArgs'],
 *     owner: WeakReference<object>,
 *     callback?: callable
 * }
 * @extends parent<RuntimeHandlerEntry>
 */
final readonly class RuntimeRegistryHandlerEntry extends AbstractDataObject
{
    use HandlerEntryTrait;
    /**
     * @param RuntimeHandlerEntry['handler'] $handler
     * @param RuntimeHandlerEntry['hook'] $hook
     * @param RuntimeHandlerEntry['priority'] $priority
     * @param RuntimeHandlerEntry['type'] $type
     * @param RuntimeHandlerEntry['acceptedArgs'] $acceptedArgs
     * @param RuntimeHandlerEntry['owner'] $owner
     * @param RuntimeHandlerEntry['callback'] $callback if using manual registerAction() or registerFilter() it will hold the callback, otherwise it will be null.
     */
    public function __construct(
        public RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler $handler,
        public string $hook,
        public int $priority,
        public string $type,
        public int $acceptedArgs,
        public ?WeakReference $owner,
        public mixed $callback = null,
    ) {}
}