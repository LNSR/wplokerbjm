<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use WPLokerBJM\Core\Container\Support\WPHooks\Debug\DebugProbes;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;

/**
 * Immutable constructor payload for {@see HookInvoker}.
 *
 * Collapses the former ~22-parameter invoker constructors into one value
 * object; registries build it (through the HookInvoker factories) and the
 * invoker only consumes it.
 */
final readonly class HookInvokerConfig
{
    public function __construct(
        public string $hookName,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public bool $once,
        public string $class,
        public string $member,
        /** @var 'method'|'property'|'property-hook' */
        public string $target,
        /** @var 'public'|'protected'|'private' */
        public string $visibility,
        /** @var 'container'|'runtime' */
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
