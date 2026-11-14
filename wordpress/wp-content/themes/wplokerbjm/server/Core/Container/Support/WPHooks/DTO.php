<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{HandlerEntryTrait, HookProviderTrait};
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{ContainerLazyHookInvoker, ContainerLazyPropertyHookInvoker, RuntimeCallableHookInvoker, RuntimeInstanceHookInvoker, RuntimeInstancePropertyHookInvoker};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{ContainerRegistryHandlerEntry, WPHooksContainerRegistry, WPHooksInstanceRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HandlerEntryUniqueKey;


/**
 * Immutable structural shared structure keys.
 *
 * Generates the canonical string used as the array key in the registries
 * and answers structural match questions
 * (by class, class+method, or namespace) without string prefix parsing.
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-type HookKeyShape array{
 *    class: class-string,
 *    method: method-string|property-string|property-hook-string|'#manual-registration',
 *    target: 'method'|'property'|'property-hook'|'#manual',
 *    type: 'action'|'filter',
 *    priority: int,
 *    acceptedArgs: int,
 *    attributeIndex: int,
 * }
 * @extends parent<HookKeyShape>
 */
final readonly class HookKey extends AbstractDataObject
{
    /**
     * @param HookKeyShape['class'] $class
     * @param HookKeyShape['method'] $method
     * @param HookKeyShape['target'] $target
     * @param HookKeyShape['type'] $type
     * @param HookKeyShape['priority'] $priority
     * @param HookKeyShape['acceptedArgs'] $acceptedArgs
     * @param HookKeyShape['attributeIndex'] $attributeIndex
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $target,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public int $attributeIndex = 0,
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
            attributeIndex: $registration->attributeIndex,
        );
    }

    public function toString(): string
    {
        return $this->class . '::' . $this->method . '::' . $this->target
            . '::' . $this->type . '::' . $this->priority . '::' . $this->acceptedArgs
            . '::' . $this->attributeIndex;
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
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * *Master Hook Type*
 * @phpstan-type HookType array{
 *  class: class-string,
 *  method: method-string|property-string|property-hook-string|'#manual-registration',
 *  type: 'action'|'filter',
 *  hook: string|\Closure(...): string,
 *  priority: int,
 *  acceptedArgs: int,
 *  attributeIndex: int,
 *  deferRegister: bool,
 *  target: 'method'|'property'|'property-hook'|'#manual',
 *  visibility: 'public'|'protected'|'private',
 *  executeIf: null|\Closure(...): bool,
 *  executeIfParams: CallablePlan,
 *  registerIf: null|(\Closure(...): bool),
 *  registerIfParams: CallablePlan,
 *  hookParams: CallablePlan,
 *  hookArgs: list<CallableHookParams>,
 *  tags: array,
 *  tagCallable: null|\Closure(...): array,
 *  tagCallableParams: CallablePlan,
 *  deferRegisterUntilHook: null|string|\Closure(...): string,
 *  deferRegisterUntilHookParams: CallablePlan,
 *  once: bool,
 *  onUnregisterDo: null|\Closure(...): void,
 *  onUnregisterDoParams: CallablePlan
 * }
 * @extends parent<HookType>
 */
final readonly class HookRegistration extends AbstractDataObject
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
     * @param HookType['onUnregisterDo'] $onUnregisterDo
     * @param HookType['onUnregisterDoParams']
     * @param HookType['attributeIndex'] $attributeIndex
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
        public ?\Closure $onUnregisterDo = null,
        public array $onUnregisterDoParams = [],
        public int $attributeIndex = 0,
    ) {}
}
#endregion

#region Runtime Registry Metadata DTOs
/*======================================================================
 | Runtime Registry Metadata DTOs
 ======================================================================*/

/**
 * Immutable metadata for a single runtime-registered hook site.
 *
 * Produced by the WPHooksInstanceRegistry scanner, cached per
 * (parentClass, parentProperty) site in the file-backed WPHooksInstanceObjectCache,
 * and re-hydrated into live handlers on subsequent requests. Only scan-derived
 * metadata lives here — per-instance state (owner instance, WeakReference,
 * remove callbacks) is intentionally NOT part of the DTO.
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-import-type CallableHookParams from HookProviderTrait
 * @phpstan-type InstanceHookMetadataData array{
 *  hook: string,
 *  type: 'action'|'filter',
 *  priority: int,
 *  acceptedArgs: int,
 *  once: bool,
 *  executeIf: ?\Closure(...):bool,
 *  executeIfParams: CallablePlan,
 *  registerIf: ?\Closure(...):bool,
 *  registerIfParams: CallablePlan,
 *  deferRegisterUntilHook: HookType['deferRegisterUntilHook'],
 *  deferRegisterUntilHookParams: HookType['deferRegisterUntilHookParams'],
 *  hookArgNames: list<CallableHookParams>,
 *  target: 'method'|'property'|'property-hook'|'#manual',
 *  targetName: method-string|property-string|property-hook-string|'#manual-registration',
 *  visibility: 'public'|'protected'|'private',
 *  onUnregisterDo: ?\Closure(...):void,
 *  onUnregisterDoParams: CallablePlan,
 * }
 * @extends parent<InstanceHookMetadataData>
 */
final readonly class InstanceHookMetadata extends AbstractDataObject
{
    /**
     * @param InstanceHookMetadataData['hook'] $hook
     * @param InstanceHookMetadataData['type'] $type
     * @param InstanceHookMetadataData['priority'] $priority
     * @param InstanceHookMetadataData['acceptedArgs'] $acceptedArgs
     * @param InstanceHookMetadataData['once'] $once
     * @param InstanceHookMetadataData['executeIf'] $executeIf
     * @param InstanceHookMetadataData['executeIfParams'] $executeIfParams
     * @param InstanceHookMetadataData['registerIf'] $registerIf
     * @param InstanceHookMetadataData['registerIfParams'] $registerIfParams
     * @param InstanceHookMetadataData['deferRegisterUntilHook'] $deferRegisterUntilHook
     * @param InstanceHookMetadataData['deferRegisterUntilHookParams'] $deferRegisterUntilHookParams
     * @param InstanceHookMetadataData['hookArgNames'] $hookArgNames
     * @param InstanceHookMetadataData['target'] $target
     * @param InstanceHookMetadataData['targetName'] $targetName
     * @param InstanceHookMetadataData['visibility'] $visibility
     * @param InstanceHookMetadataData['onUnregisterDo'] $onUnregisterDo
     * @param InstanceHookMetadataData['onUnregisterDoParams'] $onUnregisterDoParams
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
        public ?\Closure $onUnregisterDo = null,
        public array $onUnregisterDoParams = []
    ) {}
}
#endregion