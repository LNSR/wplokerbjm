<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers;

use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookRegistration;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HandlerEntryTrait;
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * @phpstan-import-type InstanceHookMetadataData from InstanceHookMetadata
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-type RuntimeHandlerEntry array{
 *     hook: string,
 *     handler: HookInvoker,
 *     type: 'action'|'filter',
 *     priority: int,
 *     acceptedArgs: int,
 *     registerIf: \Closure(...): bool,
 *     registerIfParams: CallablePlan,
 *     executeIf: \Closure(...): bool,
 *     executeIfParams: CallablePlan,
 *     once: bool,
 *     onUnregisterDo: \Closure(...): void,
 *     onUnregisterDoParams: CallablePlan,
 *     hookKey: HookKey,
 *     owner: WeakReference<object>,
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
     */
    public function __construct(
        public readonly string $hook,
        HookInvoker $handler,
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
