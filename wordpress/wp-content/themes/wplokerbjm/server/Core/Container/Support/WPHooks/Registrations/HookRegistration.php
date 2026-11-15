<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registrations;

use WPLokerBJM\Core\Container\Support\WPHooks\Provider\CallableHookParams;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * @phpstan-import-type CallablePlan from WPHookPlanProvider
 *  * *Master Hook Type*
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
 *  onUnregisterDoParams: CallablePlan,
 *  debugDeferredRegistration: null|\Closure(...): void,
 *  debugDeferredRegistrationParams: CallablePlan,
 *  debugLiveRegistration: null|\Closure(...): void,
 *  debugLiveRegistrationParams: CallablePlan,
 *  debugLiveUnregistration: null|\Closure(...): void,
 *  debugLiveUnregistrationParams: CallablePlan
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
     * @param HookType['debugDeferredRegistration'] $debugDeferredRegistration
     * @param HookType['debugDeferredRegistrationParams'] $debugDeferredRegistrationParams
     * @param HookType['debugLiveRegistration'] $debugLiveRegistration
     * @param HookType['debugLiveRegistrationParams'] $debugLiveRegistrationParams
     * @param HookType['debugLiveUnregistration'] $debugLiveUnregistration
     * @param HookType['debugLiveUnregistrationParams'] $debugLiveUnregistrationParams
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
        public ?\Closure $debugDeferredRegistration = null,
        public array $debugDeferredRegistrationParams = [],
        public ?\Closure $debugLiveRegistration = null,
        public array $debugLiveRegistrationParams = [],
        public ?\Closure $debugLiveUnregistration = null,
        public array $debugLiveUnregistrationParams = [],
    ) {}
}
