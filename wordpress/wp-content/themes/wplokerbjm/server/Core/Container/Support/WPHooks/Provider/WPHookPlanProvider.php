<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Provider;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{DeferredRegistrationInfoDTO, HookDebugDispatcher, LiveRegistrationInfoDTO, LiveUnregistrationInfoDTO};
use WPLokerBJM\Core\Container\Support\WPHooks\Exceptions\NonNullableSelfParameterException;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Plan\CallablePlanFactory;
use WPLokerBJM\Core\Container\Support\WPHooks\Resolution\ArgumentResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Resolution\ConditionEvaluator;

/**
 * Backwards-compatible facade over the hook resolution collaborators.
 *
 * Responsibilities now live in:
 *  - {@see CallablePlanFactory} — reflection → plan arrays and their caches,
 *  - {@see ArgumentResolver} — callable parameter resolution and scope binding,
 *  - {@see ConditionEvaluator} — hook names, executeIf/registerIf, tags and
 *    onUnregisterDo cleanup closures,
 *  - {@see HookDebugDispatcher} — debug-probe dispatch and owner-optional
 *    parameter policy.
 *
 * New code should inject the collaborator it needs instead of this facade.
 *
 * @phpstan-type CallablePlan array{
 *      isStatic: bool,
 *      params: list<CallableHookParams>
 * }
 */
class WPHookPlanProvider
{
    private readonly CallablePlanFactory $planFactory;
    private readonly ArgumentResolver $resolver;
    private readonly ConditionEvaluator $conditions;
    private readonly HookDebugDispatcher $debug;

    public function __construct(ContainerInterface $container)
    {
        $this->planFactory = new CallablePlanFactory();
        $this->resolver = new ArgumentResolver($container);
        $this->conditions = new ConditionEvaluator($this->resolver);
        $this->debug = new HookDebugDispatcher($container, $this->resolver);
    }

    /**
     * Instance-registry closures are bound to a WeakReference owner, so any
     * `self`/`static` parameter must be nullable: null means the owner is gone
     * or mid-teardown.
     * ! Cached plan treated as trusted.
     * ! Clear cache or use @see self::registerHooksOn() (param $forceNoCache)
     * ! if you want to revalidate closures.
     */
    public function assertNullableSelfClosures(InstanceHookMetadata $metadata, object $instance): void
    {
        $ownerLabel = $instance instanceof ModuleClassHookMetadata
            ? $instance->parentClassString . '->' . $instance->parentProperty
            : $instance::class;

        $hookLabel = $metadata->hook instanceof \Closure ? '(closure)' : $metadata->hook;
        $label = $ownerLabel . '->' . $metadata->targetName . ' on ' . $hookLabel;

        foreach (get_object_vars($metadata) as $propertyName => $closure) {
            if (!$closure instanceof \Closure) {
                continue;
            }

            foreach ($this->buildCallablePlan($closure)['params'] as $param) {
                if ($param->isSelf && !$param->allowsNull) {
                    throw NonNullableSelfParameterException::forParameter($propertyName, $label, $param->name);
                }
            }
        }
    }

    /**
     * @return CallablePlan
     */
    public function buildCallablePlan(?\Closure $callable): array
    {
        return $this->planFactory->buildCallablePlan($callable);
    }

    /**
     * @return CallablePlan
     */
    public function buildCallablePlanFromReflector(\ReflectionFunctionAbstract $reflect): array
    {
        return $this->planFactory->buildCallablePlanFromReflector($reflect);
    }

    /**
     * @return CallablePlan
     */
    public function callablePlan(mixed $callable): array
    {
        return $this->planFactory->callablePlan($callable);
    }

    /**
     * @return CallablePlan
     */
    public function extractPropertyCallablePlan(\ReflectionProperty $property, ?object $instance = null): array
    {
        return $this->planFactory->extractPropertyCallablePlan($property, $instance);
    }

    /**
     * @return array<int, string>
     */
    public function extractPropertyCallableParamNames(\ReflectionProperty $property, ?object $instance = null): array
    {
        return $this->planFactory->extractPropertyCallableParamNames($property, $instance);
    }

    public function resolveHookName(
        string|\Closure $hook,
        array $hookParams = [],
        string $label = '',
        object|string|null $targetClass = null
    ): string {
        return $this->conditions->resolveHookName($hook, $hookParams, $label, $targetClass);
    }

    /**
     * Summary of evaluateExecuteIf
     * @param CallablePlan $executeIfParams
     * @return bool
     */
    public function evaluateExecuteIf(
        ?\Closure $executeIf,
        array $executeIfParams,
        string $label = '',
        object|null|string $targetClass = null,
        array $hookArgs = [],
        ?string $variadicHookArg = null,
    ): bool {
        return $this->conditions->evaluateExecuteIf($executeIf, $executeIfParams, $label, $targetClass, $hookArgs, $variadicHookArg);
    }

    /**
     * @param CallablePlan $onUnregisterDoParams
     * @return callable|null
     */
    public function resolveOnUnregisterDo(
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        string $label = '',
        \WeakReference|null|string $targetClass = null,
    ): \Closure|null {
        return $this->conditions->resolveOnUnregisterDo($onUnregisterDo, $onUnregisterDoParams, $label, $targetClass);
    }

    /**
     * @param CallablePlan $plan
     * @return string[]
     */
    public function resolveTagCallable(
        \Closure $tagCallable,
        array $plan,
        string $label = '',
        null|object|string $targetClass = null,
    ): array {
        return $this->conditions->resolveTagCallable($tagCallable, $plan, $label, $targetClass);
    }

    /**
     * @param CallablePlan $registerIfParams
     * @return bool
     */
    public function evaluateRegistrationGate(
        ?\Closure $registerIf,
        array $registerIfParams,
        string $label = '',
        null|object|string $targetClass = null,
    ): bool {
        return $this->conditions->evaluateRegistrationGate($registerIf, $registerIfParams, $label, $targetClass);
    }

    /**
     * @param CallablePlan $plan precomputed plan for the debug closure
     * @param DeferredRegistrationInfoDTO|LiveRegistrationInfoDTO|LiveUnregistrationInfoDTO $dto
     */
    public function invokeHookDebug(
        ?\Closure $debug,
        array $plan,
        object $dto,
        string $label = '',
        ?object $owner = null,
        bool $ownerOptional = false,
    ): void {
        $this->debug->invokeHookDebug($debug, $plan, $dto, $label, $owner, $ownerOptional);
    }
}
