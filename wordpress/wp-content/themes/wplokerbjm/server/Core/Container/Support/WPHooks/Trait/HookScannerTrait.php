<?php
declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\{WPHooksScanner, WPHooksRuntimeRegistry};

/**
 * Shared method-scanning logic for hook attribute discovery.
 *
 * Iterates non-static methods of a ReflectionClass and calls a user-provided
 * callback for each #[Action] or #[Filter] attribute found.
 *
 * Used by:
 * - @see WPHooksScanner (compiled to HookRegistration arrays)
 * - @see WPHooksRuntimeRegistry (immediate add_action / add_filter)
 */
trait HookScannerTrait
{
    /**
     * Scan all non-static methods DECLARED on the given class for hook attributes.
     *
     * Inherited methods are deliberately excluded — hook registration is
     * explicit: a subclass re-declares the method (with its own #[Action] /
     * #[Filter] attribute and `parent::method()` call) to opt in.
     *
     * For each #[Action] or #[Filter] attribute found, the callback receives:
     *   (ReflectionMethod $method, Action|Filter $attr, string $visibility, 'action'|'filter' $type)
     *
     * @param ReflectionClass $reflection Class to scan.
     * @param callable(ReflectionMethod $method, Action|Filter $attr, string $visibility, 'action'|'filter' $type): void $callback
     */
    private function scanMethodHooks(ReflectionClass $reflection, callable $callback): void
    {
        /** @var ReflectionMethod $method */
        foreach ($reflection->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE
        ) as $method) {
            // Declared-only: inherited hook methods are deliberately excluded.
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            // Static methods are skipped — hooks must be instance methods.
            if ($method->isStatic()) {
                continue;
            }

            // Magic methods (except __invoke) cannot be hooked: the lazy
            // handler would call them on a container-built instance, which
            // misfires for __construct (double construction) and is nonsense
            // for __get/__set/__call/__toString/... Fail fast with a clear
            // message instead of silently registering something broken.
            $methodName = $method->getName();
            if (
                str_starts_with($methodName, '__')
                && $methodName !== '__invoke'
                && ($method->getAttributes(Action::class) !== [] || $method->getAttributes(Filter::class) !== [])
            ) {
                throw new \RuntimeException(
                    'Hook attribute on magic method ' . $reflection->getName() . '::' . $methodName
                    . ' is not allowed — only __invoke can be hooked'
                );
            }

            $visibility = $method->isPublic()
                ? 'public'
                : ($method->isProtected() ? 'protected' : 'private');

            foreach ($method->getAttributes(Action::class) as $attribute) {
                /** @var Action $action */
                $action = $attribute->newInstance();
                $callback($method, $action, $visibility, 'action');
            }

            foreach ($method->getAttributes(Filter::class) as $attribute) {
                /** @var Filter $filter */
                $filter = $attribute->newInstance();
                $callback($method, $filter, $visibility, 'filter');
            }
        }
    }
    /**
     * Iterate all non-static properties DECLARED on a class and invoke $callback
     * once per #[Action] / #[Filter] discovered.
     *
     * Inherited properties are deliberately excluded — same explicitness
     * contract as scanMethodHooks.
     *
     * @param ReflectionClass $reflection   Class to scan
     * @param callable(ReflectionProperty $property, Action|Filter $attr, string $visibility, 'action'|'filter' $type, 'property'|'property-hook' $target): void $callback
     */
    private function scanPropertyHooks(
        ReflectionClass $reflection,
        callable $callback,
    ): void {
        foreach ($reflection->getProperties(
            ReflectionProperty::IS_PUBLIC
            | ReflectionProperty::IS_PROTECTED
            | ReflectionProperty::IS_PRIVATE,
        ) as $property) {
            // Declared-only: inherited hook properties are deliberately excluded.
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            if ($property->isStatic()) {
                continue;
            }

            $visibility = $property->isPublic()
                ? 'public'
                : ($property->isProtected() ? 'protected' : 'private');

            $target = $property->hasHooks()
                ? 'property-hook'
                : 'property';

            /** @var ReflectionProperty $property */
            foreach ($property->getAttributes(Action::class) as $attribute) {
                /** @var Action $action */
                $action = $attribute->newInstance();
                $callback($property, $action, $visibility, 'action', $target);
            }

            foreach ($property->getAttributes(Filter::class) as $attribute) {
                /** @var Filter $filter */
                $filter = $attribute->newInstance();
                $callback($property, $filter, $visibility, 'filter', $target);
            }
        }
    }
}
