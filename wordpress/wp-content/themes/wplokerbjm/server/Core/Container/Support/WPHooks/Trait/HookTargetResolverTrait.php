<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use ReflectionFunction;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Shared callable → `[class, member]` target resolution.
 *
 * Handles every callable shape a hook registration can expose:
 *
 *  - array callables `[$object|class-string, 'member']`;
 *  - `'Class::method'` strings (static fallback, `__invoke` when omitted);
 *  - invokable objects — including anonymous classes and
 *    {@see ModuleClassHookMetadata} hosts;
 *  - closures — first-class callables and PHP 8.4 property-hook accessor
 *    closures (`{closure:FQCN::$propertyName::get():line}`).
 *
 * The container registry consumes the stable `[class-string, member]` pair for
 * index lookups; the runtime registry maps the same members onto a live
 * instance. Anonymous classes that do not extend `ModuleClassHookMetadata`
 * fall back to a bounded call-stack walk to find the owning property.
 *
 * @phpstan-import-type HookTargetResolve from \WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\ContainerRegistryDeferredHookManager
 * @internal
 */
trait HookTargetResolverTrait
{
    /**
     * Per-request cache of resolved callable → `[FQCN, memberName]`.
     *
     * WeakMap automatically drops entries when their key (the callable
     * object) is garbage-collected, avoiding memory leaks.
     *
     * @var \WeakMap<object, array{class-string, callable-string}>
     */
    private \WeakMap $callableTargetCache {
        get => $this->callableTargetCache ??= new \WeakMap();
    }

    /**
     * @param HookTargetResolve $target
     * @return array{class-string, callable-string}
     */
    public function resolve(object|callable|array|string $target): array
    {
        if (is_object($target) && isset($this->callableTargetCache[$target])) {
            return $this->callableTargetCache[$target];
        }

        $result = $this->doResolveCallableTarget($target);

        if (is_object($target)) {
            $this->callableTargetCache[$target] = $result;
        }

        return $result;
    }

    /**
     * @param HookTargetResolve $target
     * @return array{class-string, callable-string}
     */
    private function doResolveCallableTarget(object|callable|array|string $target): array
    {
        // 1. Array [$object, 'memberName'] — fast path
        if (is_array($target)) {
            if (is_object($target[0])) {
                return [get_class($target[0]), $target[1]];
            } elseif (is_string($target[0])) {
                return [$target[0], $target[1]];
            }
        }

        // 2. String 'Class::method' — static fallback
        if (is_string($target)) {
            $parts = explode('::', $target, 2);
            return [$parts[0], $parts[1] ?? '__invoke'];
        }

        // 3. Invokable Object Instance (anonymous class from a property)
        if (!$target instanceof \Closure && is_object($target)) {
            return $this->analyseObjectTarget($target);
        }

        // 4. Closure (First-class callable or PHP 8.4 property hook accessor)
        /** @var \Closure $target */
        $ref = new ReflectionFunction($target);
        $calledClass = $ref->getClosureThis() ?? null;

        if ($calledClass === null) {
            throw new \InvalidArgumentException('Callable target must be bound to an object instance.');
        }

        $name = $ref->getName();

        // Standard Instance Method ($service->method(...)) from FCC
        if (!str_contains($name, '{closure')) {
            return [$calledClass::class, $name];
        }

        // PHP 8.4 Property Hook Anonymous Closure ("{closure:FQCN::$propertyName::get():line}")
        $memberName = $this->parsePropertyHookMemberName($name);
        if ($memberName !== null) {
            return [$calledClass::class, $memberName];
        }

        throw new \InvalidArgumentException('Unable to resolve hook target for class ' . $calledClass::class . '.');
    }

    /**
     * Resolve an invokable object to its class + callable member.
     *
     * @return array{class-string, callable-string}
     */
    private function analyseObjectTarget(object $target): array
    {
        $className = $target::class;

        // Anonymous class extending ModuleClassHookMetadata —
        // reads parent class & property directly, no backtrace needed.
        if ($target instanceof ModuleClassHookMetadata) {
            return [$target->parentClassString, $target->parentProperty];
        }

        // Best-effort fallback for anonymous classes: walk the call stack
        // to find which object's property holds this instance.
        if (new \ReflectionClass($target)->isAnonymous()) {
            $frames = Backtrace::create()
                ->withArguments(false)
                ->limit(10)
                ->frames();

            /** @var Frame $frame */
            foreach ($frames as $frame) {
                $callerObject = $frame->object;
                if ($callerObject === null) {
                    continue;
                }

                $refClass = new \ReflectionClass($callerObject);
                foreach ($refClass->getProperties() as $property) {
                    if ($property->isInitialized($callerObject) && $property->getValue($callerObject) === $target) {
                        Logger::info(
                            "WPHooksContainerRegistry: ",
                            "Anon class without extending ModuleClassHookMetadata, it's recommended to use 'ModuleClassHookMetadata'." . $refClass->getName() . "::" . $property->getName()
                        );
                        return [$refClass->getName(), $property->getName()];
                    }
                }
            }
        }

        return [$className, '__invoke'];
    }

    /**
     * Extract the property name from a PHP 8.4 property-hook accessor closure.
     *
     * Reflection names those closures `{closure:FQCN::$propertyName::get():line}`.
     *
     * @param string $name Reflection name of the closure.
     * @return string|null Property name, or null when the closure is not a property-hook accessor.
     */
    protected function parsePropertyHookMemberName(string $name): ?string
    {
        $start = strpos($name, '::$');
        if ($start === false) {
            return null;
        }

        $start += 3;
        $end = strpos($name, '::', $start);
        if ($end === false) {
            return null;
        }

        $memberName = substr($name, $start, $end - $start);

        return $memberName !== '' ? $memberName : null;
    }
}
