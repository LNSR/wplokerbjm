<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registrations;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

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
