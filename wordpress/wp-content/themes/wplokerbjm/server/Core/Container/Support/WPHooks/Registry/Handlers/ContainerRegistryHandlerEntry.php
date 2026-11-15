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
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HandlerEntryUniqueKey;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-type HandlerEntry array{
 *  hook: string,
 *  key: HookKey,
 *  handler: HookInvoker,
 *  type: 'action'|'filter',
 *  priority: int,
 *  acceptedArgs: int,
 *  tags: array,
 *  registerIf: \Closure(...): bool,
 *  registerIfParams: CallablePlan,
 *  executeIf: \Closure(...): bool,
 *  executeIfParams: CallablePlan,
 *  once: bool,
 *  onUnregisterDo: \Closure(...): void,
 *  onUnregisterDoParams: CallablePlan,
 *  removeEntryIndexCallback: (callable(static $entry): void)|null
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
     * @param HandlerEntry['removeEntryIndexCallback'] $removeEntryIndexCallback
     */
    public function __construct(
        public readonly string $hook,
        public readonly HookKey $key,
        HookInvoker $handler,
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
        public readonly array $onUnregisterDoParams,
        private ?\Closure $removeEntryIndexCallback = null
    ) {
        $this->handler = $handler;
        $this->invokerLabel = $handler->label;
    }

    /**
     * Override @see HandlerEntryTrait::__destruct
     */
    public function __destruct()
    {
        if ($this->registered) {
            $this->unregister(\WPLokerBJM\Core\Container\Support\WPHooks\Debug\UnregistrationSource::Destructor);
            SharedUtils::isDevelopment() && Logger::debug(__METHOD__, "Container Registry Handler Entry destroyed before explicit registry unregistration, removing entry indexes: ", [
                'invokerLabel' => $this->invokerLabel,
            ]);
        }
        \is_callable($this->removeEntryIndexCallback) && ($this->removeEntryIndexCallback)($this);
        unset($this->removeEntryIndexCallback);
    }

    /** @param HandlerEntry['removeEntryIndexCallback'] $callback */
    public function setOnEntryRemovalRemoveIndexesCallback(\Closure $callback): void
    {
        $this->removeEntryIndexCallback = $callback;
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
