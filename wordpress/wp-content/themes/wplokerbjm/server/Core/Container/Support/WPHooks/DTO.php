<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HookProviderTrait};
use WPLokerBJM\Shared\Utilities\DTO\AbstractDTO;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookHandler, ContainerLazyPropertyHookHandler, RuntimeCallableHookHandler, RuntimeInstanceHookHandler, RuntimeInstancePropertyHookHandler};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksRuntimeRegistry};

/**
 * Immutable structural shared structure keys.
 *
 * Generates the canonical string used as the array key in the registries
 * and answers structural match questions
 * (by class, class+method, or namespace) without string prefix parsing.
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type HookKeyShape array{
 *    class: HookType['class'],
 *    method: HookType['method'],
 *    target: HookType['target'],
 *    type: HookType['type'],
 *    priority: HookType['priority'],
 *    acceptedArgs: HookType['acceptedArgs'],
 * }
 * @extends parent<HookKeyShape>
 */
final readonly class HookKey extends AbstractDTO
{
    /**
     * @param HookKeyShape['class'] $class
     * @param HookKeyShape['method'] $method
     * @param HookKeyShape['target'] $target
     * @param HookKeyShape['type'] $type
     * @param HookKeyShape['priority'] $priority
     * @param HookKeyShape['acceptedArgs'] $acceptedArgs
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $target,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
    ) {}

    public static function fromRegistration(HookRegistration $registration): self
    {
        return new self(
            class: $registration->class,
            method: $registration->method,
            target: $registration->target,
            type: $registration->type,
            priority: $registration->priority,
            acceptedArgs: $registration->acceptedArgs,
        );
    }

    public function toString(): string
    {
        return $this->class . '::' . $this->method . '::' . $this->target
            . '::' . $this->type . '::' . $this->priority . '::' . $this->acceptedArgs;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function isForClass(string $class): bool
    {
        return $this->class === $class;
    }

    public function isForCallable(string $class, string $method): bool
    {
        return $this->class === $class && $this->method === $method;
    }

    public function isWithinNamespace(string $namespace): bool
    {
        $namespace = rtrim($namespace, '\\');

        return $namespace !== '' && ($this->class === $namespace || str_starts_with($this->class, $namespace . '\\'));
    }
}

#region Container Registry metadata DTOs
/*======================================================================
 | Container Registry metadata DTOs
 ======================================================================*/

/**
 * @template TObject
 * @template TClass
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * *Master Hook Type*
 * @phpstan-type HookType array{
 *  class: class-string<TClass>,
 *  method: method-string<TClass>|property-string<TClass>|property-hook-string<TClass>|'#manual-registration',
 *  type: 'action'|'filter',
 *  hook: string|\Closure(TObject...): string,
 *  priority: int,
 *  acceptedArgs: int,
 *  deferRegister: bool,
 *  target: 'method'|'property'|'property-hook'|'#manual',
 *  visibility: 'public'|'protected'|'private',
 *  executeIf: null|\Closure(TObject...): bool,
 *  executeIfParams: CallablePlan,
 *  registerIf: null|(\Closure(TObject...): bool),
 *  registerIfParams: CallablePlan,
 *  hookParams: CallablePlan,
 *  hookArgs: array,
 *  tags: array,
 *  tagCallable: null|\Closure(TObject...): array,
 *  tagCallableParams: CallablePlan,
 *  deferRegisterUntilHook: null|string|\Closure(TObject...): string,
 *  deferRegisterUntilHookParams: CallablePlan,
 *  once: bool,
 * }
 * @extends parent<HookType>
 */
final readonly class HookRegistration extends AbstractDTO
{
    /**
     * @param HookType['class'] $class
     * @param HookType['method'] $method
     * @param HookType['type'] $type
     * @param HookType['hook'] $hook
     * @param HookType['priority'] $priority
     * @param HookType['acceptedArgs'] $acceptedArgs
     * @param HookType['deferRegister'] $deferRegister
     * @param HookType['target'] $target
     * @param HookType['visibility'] $visibility
     * @param HookType['executeIf'] $executeIf
     * @param HookType['executeIfParams'] $executeIfParams
     * @param HookType['registerIf'] $registerIf
     * @param HookType['registerIfParams'] $registerIfParams
     * @param HookType['hookParams'] $hookParams
     * @param HookType['hookArgs'] $hookArgs
     * @param HookType['tags'] $tags
     * @param HookType['tagCallable'] $tagCallable
     * @param HookType['tagCallableParams'] $tagCallableParams
     * @param HookType['deferRegisterUntilHook'] $deferRegisterUntilHook
     * @param HookType['deferRegisterUntilHookParams'] $deferRegisterUntilHookParams
     * @param HookType['once'] $once
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $type,
        public string|\Closure $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1,
        public bool $deferRegister = false,
        public string $target = 'method',
        public string $visibility = 'public',
        public ?\Closure $executeIf = null,
        public array $executeIfParams = [],
        public ?\Closure $registerIf = null,
        public array $registerIfParams = [],
        public array $hookParams = [],
        public array $hookArgs = [],
        public array $tags = [],
        public ?\Closure $tagCallable = null,
        public array $tagCallableParams = [],
        public string|\Closure|null $deferRegisterUntilHook = null,
        public array $deferRegisterUntilHookParams = [],
        public bool $once = false,
    ) {}
}
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
final readonly class ContainerRegistryHandlerEntry extends AbstractDTO
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
#endregion

#region Runtime Registry Metadata DTOs
/*======================================================================
 | Runtime Registry Metadata DTOs
 ======================================================================*/

/**
 * Immutable metadata for a single runtime-registered hook site.
 *
 * Produced by the WPHooksRuntimeRegistry scanner, cached per
 * (parentClass, parentProperty) site in the file-backed WPHooksRuntimeCache,
 * and re-hydrated into live handlers on subsequent requests. Only scan-derived
 * metadata lives here — per-instance state (owner instance, WeakReference,
 * remove callbacks) is intentionally NOT part of the DTO.
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-type RuntimeHookMetadataData array{
 *  hook: string,
 *  type: HookType['type'],
 *  priority: HookType['priority'],
 *  acceptedArgs: HookType['acceptedArgs'],
 *  once: HookType['once'],
 *  executeIf: HookType['executeIf'],
 *  executeIfParams: HookType['executeIfParams'],
 *  registerIf: HookType['registerIf'],
 *  registerIfParams: HookType['registerIfParams'],
 *  deferRegisterUntilHook: HookType['deferRegisterUntilHook'],
 *  deferRegisterUntilHookParams: HookType['deferRegisterUntilHookParams'],
 *  hookArgNames: HookType['hookArgs'],
 *  target: HookType['target'],
 *  targetName: HookType['method'],
 *  visibility: HookType['visibility'],
 * }
 * @extends parent<RuntimeHookMetadataData>
 */
readonly class RuntimeHookMetadata extends AbstractDTO
{
    /**
     * @param RuntimeHookMetadataData['hook'] $hook
     * @param RuntimeHookMetadataData['type'] $type
     * @param RuntimeHookMetadataData['priority'] $priority
     * @param RuntimeHookMetadataData['acceptedArgs'] $acceptedArgs
     * @param RuntimeHookMetadataData['once'] $once
     * @param RuntimeHookMetadataData['executeIf'] $executeIf
     * @param RuntimeHookMetadataData['executeIfParams'] $executeIfParams
     * @param RuntimeHookMetadataData['registerIf'] $registerIf
     * @param RuntimeHookMetadataData['registerIfParams'] $registerIfParams
     * @param RuntimeHookMetadataData['deferRegisterUntilHook'] $deferRegisterUntilHook
     * @param RuntimeHookMetadataData['deferRegisterUntilHookParams'] $deferRegisterUntilHookParams
     * @param RuntimeHookMetadataData['hookArgNames'] $hookArgNames
     * @param RuntimeHookMetadataData['target'] $target
     * @param RuntimeHookMetadataData['targetName'] $targetName
     * @param RuntimeHookMetadataData['visibility'] $visibility
     */
    public function __construct(
        public string $hook,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public bool $once,
        public ?\Closure $executeIf = null,
        public array $executeIfParams = [],
        public ?\Closure $registerIf = null,
        public array $registerIfParams = [],
        public string|\Closure|null $deferRegisterUntilHook = null,
        public array $deferRegisterUntilHookParams = [],
        public array $hookArgNames = [],
        public string $target = 'method',
        public string $targetName = '',
        public string $visibility = 'public',
    ) {}
}
/**
 * @phpstan-import-type RuntimeHookMetadataData from RuntimeHookMetadata
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type RuntimeHandlerEntry array{
 *     handler: RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler|RuntimeCallableHookHandler,
 *     hook: RuntimeHookMetadataData['hook'],
 *     priority: RuntimeHookMetadataData['priority'],
 *     type: RuntimeHookMetadataData['type'],
 *     acceptedArgs: RuntimeHookMetadataData['acceptedArgs'],
 *     owner: WeakReference<object>,
 *     callback?: callable
 * }
 * @extends parent<RuntimeHandlerEntry>
 */
final readonly class RuntimeRegistryHandlerEntry extends AbstractDTO
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
#endregion

#region Deferred Hook metadata DTOs
/*======================================================================
 | Deferred Hook metadata DTOs
 ======================================================================*/
/**
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type AvailaibleHandlerType ContainerLazyHookHandler|ContainerLazyPropertyHookHandler|RuntimeCallableHookHandler|RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler
 * @phpstan-type DeferredHookEntry array{
 *     hook: string,
 *     key: HookKey,
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
 *     instance: WeakReference<object>
 * }>>
 * @extends parent<DeferredHookEntry>
 */
final readonly class DeferredHookEntryDTO extends AbstractDTO
{
    /**
     * @param DeferredHookEntry['hook'] $hook
     * @param DeferredHookEntry['key'] $key Exclusive for @see WPHooksContainerRegistry
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
     * @param DeferredHookEntry['instance'] $instance Exclusive for @see WPHooksRuntimeRegistry
     */
    public function __construct(
        public string $hook,
        public ContainerLazyHookHandler|ContainerLazyPropertyHookHandler|RuntimeCallableHookHandler|RuntimeInstanceHookHandler|RuntimeInstancePropertyHookHandler $handler,
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
        return new self(
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
    public function toUniqueKey(): string
    {
        return $this->hook . ':' . $this->key->toString();
    }
}
#endregion