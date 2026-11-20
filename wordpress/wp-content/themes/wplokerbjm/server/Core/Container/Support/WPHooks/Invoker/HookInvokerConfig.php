<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\DebugProbes;
use WPLokerBJM\Core\Container\Support\WPHooks\Plan\CallablePlanFactory;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\{CallableHookParams, WPHookPlanProvider};

/**
 * Immutable constructor payload for {@see HookInvoker}.
 * @phpstan-import-type CallablePlan from CallablePlanFactory
 * Collapses the former ~22-parameter invoker constructors into one value
 * object; registries build it (through the HookInvoker factories) and the
 * invoker only consumes it.
 */
final readonly class HookInvokerConfig
{
    /**
     * @param 'action'|'filter' $type
     * @param int $priority
     * @param int $acceptedArgs
     * @param bool $once
     * @param class-string $class
     * @param string $member
     * @param 'method'|'property'|'property-hook' $target
     * @param 'public'|'protected'|'private' $visibility
     * @param 'container'|'runtime' $path
     * @param string $label
     * @param callable|null $executeIf
     * @param CallablePlan $executeIfParams
     * @param list<CallableHookParams> $hookArgNames
     * @param callable|null $onUnregisterDo
     * @param CallablePlan $onUnregisterDoParams
     * @param callable|null $debugDeferredRegistration
     * @param CallablePlan $debugDeferredRegistrationParams
     * @param callable|null $debugLiveRegistration
     * @param CallablePlan $debugLiveRegistrationParams
     * @param callable|null $debugLiveUnregistration
     * @param CallablePlan $debugLiveUnregistrationParams
     * @param WPHookPlanProvider|null $planProvider
     */
    public function __construct(
        public string $hookName,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public bool $once,
        public string $class,
        public string $member,
        public string $target,
        public string $visibility,
        public string $path,
        public string $label,
        public ?\Closure $executeIf = null,
        public array $executeIfParams = [],
        public array $hookArgNames = [],
        public ?\Closure $onUnregisterDo = null,
        public array $onUnregisterDoParams = [],
        public ?\Closure $debugDeferredRegistration = null,
        public array $debugDeferredRegistrationParams = [],
        public ?\Closure $debugLiveRegistration = null,
        public array $debugLiveRegistrationParams = [],
        public ?\Closure $debugLiveUnregistration = null,
        public array $debugLiveUnregistrationParams = [],
        public ?WPHookPlanProvider $planProvider = null,
    ) {}

    public function probes(): DebugProbes
    {
        return new DebugProbes(
            deferred: $this->debugDeferredRegistration,
            deferredParams: $this->debugDeferredRegistrationParams,
            live: $this->debugLiveRegistration,
            liveParams: $this->debugLiveRegistrationParams,
            unregistration: $this->debugLiveUnregistration,
            unregistrationParams: $this->debugLiveUnregistrationParams,
        );
    }
}
