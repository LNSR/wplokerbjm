<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Plan;

use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use WPLokerBJM\Core\Container\Attributes\Inject;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\CallableHookParams;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Reflection → callable-plan factory.
 *
 * Pure plan building plus per-callable memoization. No container access and no
 * invocation: plans are computed once at scan time and exported to the hooks
 * cache, so fire-time resolution never reflects.
 *
 * @phpstan-import-type CallablePlan from \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider
 */
final class CallablePlanFactory
{
    /** @var \WeakMap<object, CallablePlan> */
    private \WeakMap $callablePlanCache;

    public function __construct()
    {
        $this->callablePlanCache = new \WeakMap();
    }

    /**
     * Build the resolution plan for a callable closure.
     *
     * @return CallablePlan
     */
    public function buildCallablePlan(?\Closure $callable): array
    {
        /** @var CallablePlan $empty */
        $empty = ['isStatic' => true, 'params' => []];

        if ($callable === null) {
            return $empty;
        }

        try {
            return $this->callablePlanCache[$callable] ??= $this->buildCallablePlanFromReflector(new ReflectionFunction($callable));
        } catch (ReflectionException) {
            return $empty;
        }
    }

    /**
     * Build the resolution plan for ANY reflected callable (closure or method).
     *
     * @return CallablePlan
     */
    public function buildCallablePlanFromReflector(\ReflectionFunctionAbstract $reflect): array
    {
        if (isset($this->callablePlanCache[$reflect])) return $this->callablePlanCache[$reflect];

        /** @var list<CallableHookParams> $params */
        $params = [];
        foreach ($reflect->getParameters() as $param) {
            $type = $param->getType();

            $attribute = $param->getAttributes(Inject::class)[0] ?? null;
            $injectInstance = $attribute?->newInstance();

            $paramType = $injectInstance?->name ?? null;
            if ($type instanceof ReflectionNamedType && $type->getName() === 'self' && $paramType === null) {
                $reflectClass = $reflect->getClosureScopeClass();
                $paramType = $reflectClass?->isAnonymous()
                    ? 'self'
                    : $reflectClass?->getName();
            } else {
                $paramType = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : $paramType;
            }

            $hasDefault = $param->isDefaultValueAvailable();
            $default = $hasDefault ? $param->getDefaultValue() : null;

            $isSelf = $type instanceof ReflectionNamedType
                && \in_array($type->getName(), ['self', 'static'], true);

            // Non-exportable defaults (objects/resources) -> drop to null.
            if ($hasDefault && (is_object($default) || is_resource($default))) {
                $hasDefault = false;
                $default = null;
            }

            $params[] = new CallableHookParams(
                name: $param->getName(),
                type: $paramType,
                hasDefault: $hasDefault,
                default: $default,
                isVariadic: $param->isVariadic(),
                isSelf: $isSelf,
                allowsNull: $param->allowsNull(),
            );
        }

        return $this->callablePlanCache[$reflect] ??= [
            'isStatic' => $reflect->isStatic(),
            'params' => $params,
        ];
    }

    /**
     * Build the resolution plan for any callable VALUE (closure, invokable
     * object, array callable, 'Class::method' string).
     *
     * @return CallablePlan
     */
    public function callablePlan(mixed $callable): array
    {
        if (!is_callable($callable)) {
            return ['isStatic' => true, 'params' => []];
        }

        try {
            $reflect = match (true) {
                $callable instanceof \Closure => new \ReflectionFunction($callable),
                \is_object($callable) => ReflectionMethod::createFromMethodName($callable::class . '::__invoke'),
                \is_array($callable) => ReflectionMethod::createFromMethodName(
                    (\is_object($callable[0]) ? $callable[0]::class : $callable[0]) . '::' . $callable[1]
                ),
                \is_string($callable) && str_contains($callable, '::') => ReflectionMethod::createFromMethodName($callable),
                \is_string($callable) => new ReflectionFunction($callable),
                default => null,
            };

            if ($reflect === null) {
                return ['isStatic' => true, 'params' => []];
            }

            return $this->buildCallablePlanFromReflector($reflect);
        } catch (\ReflectionException) {
            return ['isStatic' => true, 'params' => []];
        }
    }

    /**
     * Build the resolution plan for a property's callable value or default value.
     *
     * @param \ReflectionProperty $property from attributes
     * @param object|null $instance Optional already **instantiated** instance to execute getters/hooks.
     *
     * @return CallablePlan
     */
    public function extractPropertyCallablePlan(\ReflectionProperty $property, ?object $instance = null): array
    {
        if (isset($this->callablePlanCache[$property])) return $this->callablePlanCache[$property];

        /** @suppress PHP0406 */
        if ($instance !== null && $property->hasHook(\PropertyHookType::Get) && $property->isInitialized($instance)) {
            // For hooked properties, reading the property executes the `get` hook.
            try {
                $value = $property->getValue($instance);
                if (is_callable($value)) {
                    return $this->callablePlanCache[$property] ??= $this->callablePlan($value);
                }
            } catch (ReflectionException $e) {
                Logger::error(__METHOD__, 'Error getting property value', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            };
        }

        if ($property->hasDefaultValue()) {
            return $this->callablePlanCache[$property] ??= $this->callablePlan($property->getDefaultValue());
        }

        return $this->callablePlanCache[$property] ??= ['isStatic' => true, 'params' => []];
    }

    /**
     * Extract parameter names from a property's callable value or default value.
     *
     * @param \ReflectionProperty $property from Attribute #Action/#Filter
     * @param object|null $instance Optional runtime instance to execute getters/hooks.
     *
     * @return array<int, string>
     */
    public function extractPropertyCallableParamNames(\ReflectionProperty $property, ?object $instance = null): array
    {
        return array_map(
            static fn(CallableHookParams $param): string => $param->name,
            $this->extractPropertyCallablePlan($property, $instance)['params'],
        );
    }
}
