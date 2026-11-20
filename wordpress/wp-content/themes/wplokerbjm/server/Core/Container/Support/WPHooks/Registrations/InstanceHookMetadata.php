<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registrations;

use WPLokerBJM\Core\Container\Support\WPHooks\Provider\CallableHookParams;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Immutable metadata for a single runtime-registered hook site.
 *
 * Produced by the WPHooksInstanceRegistry scanner, cached per
 * (parentClass, parentProperty) site in the file-backed WPHooksInstanceObjectCache,
 * and re-hydrated into live handlers on subsequent requests. Only scan-derived
 * metadata lives here — per-instance state (owner instance, WeakReference,
 * remove callbacks) is intentionally NOT part of the DTO.
 * @phpstan-import-type HookType from HookRegistration
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 * @phpstan-type InstanceHookMetadataData array{
 *  hook: string,
 *  type: 'action'|'filter',
 *  priority: int,
 *  acceptedArgs: int,
 *  once: bool,
 *  deferRegister: bool,
 *  executeIf: ?\Closure(...):bool,
 *  executeIfParams: CallablePlan,
 *  registerIf: ?\Closure(...):bool,
 *  registerIfParams: CallablePlan,
 *  deferRegisterUntilHook: null|string|\Closure(...): string,
 *  deferRegisterUntilHookParams: CallablePlan,
 *  hookArgNames: list<CallableHookParams>,
 *  target: 'method'|'property'|'property-hook'|'#manual',
 *  targetName: method-string|property-string|property-hook-string|'#manual-registration',
 *  visibility: 'public'|'protected'|'private',
 *  onUnregisterDo: ?\Closure(...):void,
 *  onUnregisterDoParams: CallablePlan,
 *  debugDeferredRegistration: null|\Closure(...): void,
 *  debugDeferredRegistrationParams: CallablePlan,
 *  debugLiveRegistration: null|\Closure(...): void,
 *  debugLiveRegistrationParams: CallablePlan,
 *  debugLiveUnregistration: null|\Closure(...): void,
 *  debugLiveUnregistrationParams: CallablePlan,
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
     * @param InstanceHookMetadataData['deferRegister'] $deferRegister
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
     * @param InstanceHookMetadataData['debugDeferredRegistration'] $debugDeferredRegistration
     * @param InstanceHookMetadataData['debugDeferredRegistrationParams'] $debugDeferredRegistrationParams
     * @param InstanceHookMetadataData['debugLiveRegistration'] $debugLiveRegistration
     * @param InstanceHookMetadataData['debugLiveRegistrationParams'] $debugLiveRegistrationParams
     * @param InstanceHookMetadataData['debugLiveUnregistration'] $debugLiveUnregistration
     * @param InstanceHookMetadataData['debugLiveUnregistrationParams'] $debugLiveUnregistrationParams
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
        public array $onUnregisterDoParams = [],
        public bool $deferRegister = false,
        public ?\Closure $debugDeferredRegistration = null,
        public array $debugDeferredRegistrationParams = [],
        public ?\Closure $debugLiveRegistration = null,
        public array $debugLiveRegistrationParams = [],
        public ?\Closure $debugLiveUnregistration = null,
        public array $debugLiveUnregistrationParams = [],
    ) {}
}
