<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Provider;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Exceptions\HookDebugProbeException;
use WPLokerBJM\Core\Container\Support\WPHooks\Plan\CallablePlanFactory;
use WPLokerBJM\Core\Container\Support\WPHooks\Resolution\ArgumentResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Resolution\ConditionEvaluator;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Backwards-compatible facade over the hook resolution collaborators.
 *
 * Responsibilities now live in:
 *  - {@see CallablePlanFactory} — reflection → plan arrays and their caches,
 *  - {@see ArgumentResolver} — callable parameter resolution and scope binding,
 *  - {@see ConditionEvaluator} — hook names, executeIf/registerIf, tags and
 *    onUnregisterDo cleanup closures,
 *  - this class retains the debug dispatcher until it is extracted in a later
 *    phase.
 *
 * New code should inject the collaborator it needs instead of this facade.
 *
 * @phpstan-type CallableHookParams array{
 *      name: string,
 *      type: string|class-string|null,
 *      hasDefault: bool,
 *      default: mixed,
 *      isVariadic: bool,
 *      isSelf: bool,
 *      allowsNull: bool
 * }
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

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->planFactory = new CallablePlanFactory();
        $this->resolver = new ArgumentResolver($container);
        $this->conditions = new ConditionEvaluator($this->resolver);
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

    public function resolveOnUnregisterDo(
        ?\Closure $onUnregisterDo = null,
        array $onUnregisterDoParams = [],
        string $label = '',
        \WeakReference|null|string $targetClass = null,
    ): \Closure|null {
        return $this->conditions->resolveOnUnregisterDo($onUnregisterDo, $onUnregisterDoParams, $label, $targetClass);
    }

    public function resolveTagCallable(
        \Closure $tagCallable,
        array $plan,
        string $label = '',
        null|object|string $targetClass = null,
    ): array {
        return $this->conditions->resolveTagCallable($tagCallable, $plan, $label, $targetClass);
    }

    public function evaluateRegistrationGate(
        ?\Closure $registerIf,
        array $registerIfParams,
        string $label = '',
        null|object|string $targetClass = null,
    ): bool {
        return $this->conditions->evaluateRegistrationGate($registerIf, $registerIfParams, $label, $targetClass);
    }

    /**
     * Invoke an opt-in hook debug probe with the emitted DTO.
     *
     * In owner-optional mode (lifecycle emissions that may fire during
     * teardown), a `self`/`static` parameter is NEVER satisfied from the
     * container: it receives the live owner, `null` when declared `?self`, or
     * the probe is skipped with a logged contract error. Other dependencies
     * still resolve through the container, but a mid-teardown failure falls
     * back to defaults instead of aborting the probe.
     *
     * @param CallablePlan $plan precomputed plan for the debug closure
     */
    public function invokeHookDebug(
        ?\Closure $debug,
        array $plan,
        object $dto,
        string $label = '',
        ?object $owner = null,
        bool $ownerOptional = false,
    ): void {
        if ($debug === null) {
            return;
        }

        try {
            $callable = $owner !== null ? $this->resolver->bindToTarget($debug, $plan, $owner::class) : $debug;

            $values = [];
            foreach ($plan['params'] ?? [] as $param) {
                if ($param['isVariadic']) {
                    continue;
                }
                $values[] = $this->resolveDebugParameter($param, $dto, $label, $owner, $ownerOptional);
            }

            $callable(...$values);
        } catch (\Throwable $e) {
            Logger::error(self::class, 'Hook debug probe failed for ' . $label, [
                'dto' => $dto::class,
                'ownerOptional' => $ownerOptional,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * @param CallableHookParams $param
     */
    private function resolveDebugParameter(
        array $param,
        object $dto,
        string $label,
        ?object $owner,
        bool $ownerOptional = false,
    ): mixed {
        $type = $param['type'] ?? null;

        if (\is_string($type) && $type !== '' && \is_a($dto, $type)) {
            return $dto;
        }

        $isSelf = (bool) ($param['isSelf'] ?? false);
        $allowsNull = (bool) ($param['allowsNull'] ?? false);

        if ($isSelf && $owner !== null) {
            return $owner;
        }

        if (
            $owner !== null
            && (
                $type === 'self'
                || $type === 'static'
                || (\is_string($type) && $type !== '' && \is_a($owner, $type))
            )
        ) {
            return $owner;
        }

        if ($ownerOptional) {
            if ($isSelf) {
                if ($allowsNull) {
                    return null;
                }

                throw HookDebugProbeException::nonNullableSelf($label, $param['name'] ?? 'unknown');
            }

            if (\is_string($type) && $type !== '') {
                try {
                    if ($this->container->has($type)) {
                        return $this->container->get($type);
                    }
                } catch (\Throwable) {
                    // Container may be mid-teardown; fall through to defaults.
                }
            }

            if (!empty($param['hasDefault'])) {
                return $param['default'] ?? null;
            }

            if ($allowsNull) {
                return null;
            }

            throw HookDebugProbeException::unresolved($label, $param['name'] ?? 'unknown', $type);
        }

        return $this->resolver->resolveCallableParam($param, $label);
    }
}
