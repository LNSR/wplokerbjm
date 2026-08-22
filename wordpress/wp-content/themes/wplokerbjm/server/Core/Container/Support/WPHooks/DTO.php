<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\HookProviderTrait;

/**
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-type HookType array{
 *  class: string,
 *  method: string,
 *  type: 'action'|'filter',
 *  hook: string|\Closure,
 *  priority: int,
 *  accepted_args: int,
 *  defer_register: bool,
 *  target: 'method'|'property'|'property-hook',
 *  visibility: string,
 *  execute_if: \Closure|null,
 *  execute_if_params: CallablePlan,
 *  register_if: \Closure|null,
 *  register_if_params: CallablePlan,
 *  hook_params: CallablePlan,
 *  hookArgs: array,
 *  tags: array,
 *  tag_callable: \Closure|null,
 *  tag_callable_params: CallablePlan,
 *  defer_register_until_hook: string|\Closure|null,
 *  defer_register_until_hook_params: CallablePlan,
 *  once: bool,
 * }
 */
readonly class HookRegistration
{
    public function __construct(
        public string $class,
        public string $method,
        public string $type,
        public string|\Closure $hook,
        public int $priority,
        public int $acceptedArgs,
        public bool $deferRegister,
        public string $target,
        public string $visibility,
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
    ) {
    }

    /**
     * Summary of fromArray
     * @param HookType $data
     * @return HookRegistration
     */
    public static function fromArray(array $data): self
    {
        return new self(
            class: $data['class'],
            method: $data['method'],
            type: $data['type'],
            hook: $data['hook'],
            priority: $data['priority'],
            acceptedArgs: $data['accepted_args'],
            deferRegister: $data['defer_register'] ?? $data['defer'] ?? false,
            target: $data['target'] ?? 'method',
            visibility: $data['visibility'] ?? 'public',
            executeIf: $data['execute_if'] ?? null,
            executeIfParams: $data['execute_if_params'] ?? [],
            registerIf: $data['register_if'] ?? null,
            registerIfParams: $data['register_if_params'] ?? [],
            hookParams: $data['hook_params'] ?? [],
            hookArgs: $data['hook_args'] ?? [],
            tags: $data['tags'] ?? [],
            tagCallable: $data['tag_callable'] ?? null,
            tagCallableParams: $data['tag_callable_params'] ?? [],
            deferRegisterUntilHook: $data['defer_register_until_hook'] ?? null,
            deferRegisterUntilHookParams: $data['defer_register_until_hook_params'] ?? [],
            once: $data['once'] ?? false,
        );
    }

    /**
     * @param HookRegistration $data
     * @return HookType
     */
    public static function toArray(HookRegistration $data): array
    {
        return [
            'class' => $data->class,
            'method' => $data->method,
            'type' => $data->type,
            'hook' => $data->hook,
            'priority' => $data->priority,
            'accepted_args' => $data->acceptedArgs,
            'defer_register' => $data->deferRegister,
            'target' => $data->target,
            'visibility' => $data->visibility,
            'execute_if' => $data->executeIf,
            'execute_if_params' => $data->executeIfParams,
            'register_if' => $data->registerIf,
            'register_if_params' => $data->registerIfParams,
            'hook_params' => $data->hookParams,
            'hook_args' => $data->hookArgs,
            'tags' => $data->tags,
            'tag_callable' => $data->tagCallable,
            'tag_callable_params' => $data->tagCallableParams,
            'defer_register_until_hook' => $data->deferRegisterUntilHook,
            'defer_register_until_hook_params' => $data->deferRegisterUntilHookParams,
            'once' => $data->once,
        ];
    }

    /**
     * @param HookRegistration[] $properties
     * @return self
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }
}
/**
 * Immutable structural key identifying a hook registration.
 *
 * Generates the canonical string used as the array key in the registry
 * ({@see WPHooksContainerRegistry}) and answers structural match questions
 * (by class, class+method, or namespace) without string prefix parsing.
 */
readonly class HookKey
{
    public function __construct(
        public string $class,
        public string $method,
        public string $target,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
    ) {
    }

    public static function fromRegistration(HookRegistration $registration): self
    {
        return new self(
            class: $registration->class,
            method: $registration->method,
            target: $registration->target,
            type: $registration->type,
            priority: $registration->priority,
            acceptedArgs: $registration->acceptedArgs,
        );
    }

    public function toString(): string
    {
        return $this->class . '::' . $this->method . '::' . $this->target
            . '::' . $this->type . '::' . $this->priority . '::' . $this->acceptedArgs;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function isForClass(string $class): bool
    {
        return $this->class === $class;
    }

    public function isForCallable(string $class, string $method): bool
    {
        return $this->class === $class && $this->method === $method;
    }

    public function isWithinNamespace(string $namespace): bool
    {
        $namespace = rtrim($namespace, '\\');

        return $namespace !== '' && ($this->class === $namespace || str_starts_with($this->class, $namespace . '\\'));
    }
}
/**
 * Immutable metadata for a single runtime-registered hook site.
 *
 * Produced by the WPHooksRuntimeRegistry scanner, cached per
 * (parentClass, parentProperty) site in the file-backed WPHooksRuntimeCache,
 * and re-hydrated into live handlers on subsequent requests. Only scan-derived
 * metadata lives here — per-instance state (owner instance, WeakReference,
 * remove callbacks) is intentionally NOT part of the DTO.
 *
 * @phpstan-import-type CallablePlan from HookProviderTrait
 * @phpstan-type RuntimeHookMetadataData array{
 *  hook: string,
 *  type: 'action'|'filter',
 *  priority: int,
 *  acceptedArgs: int,
 *  once: bool,
 *  executeIf: \Closure|null,
 *  executeIfParams: CallablePlan,
 *  registerIf: \Closure|null,
 *  registerIfParams: CallablePlan,
 *  deferRegisterUntilHook: string|\Closure|null,
 *  deferRegisterUntilHookParams: CallablePlan,
 *  hookArgNames: array<int, string>,
 *  target: 'method'|'property'|'property-hook',
 *  targetName: string,
 *  visibility: string,
 * }
 */
readonly class RuntimeHookMetadata
{
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
    ) {
    }

    /**
     * @param RuntimeHookMetadataData $data
     * @return RuntimeHookMetadata
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hook: $data['hook'],
            type: $data['type'],
            priority: $data['priority'],
            acceptedArgs: $data['acceptedArgs'],
            once: $data['once'] ?? false,
            executeIf: $data['executeIf'] ?? null,
            executeIfParams: $data['executeIfParams'] ?? [],
            registerIf: $data['registerIf'] ?? null,
            registerIfParams: $data['registerIfParams'] ?? [],
            deferRegisterUntilHook: $data['deferRegisterUntilHook'] ?? null,
            deferRegisterUntilHookParams: $data['deferRegisterUntilHookParams'] ?? [],
            hookArgNames: $data['hookArgNames'] ?? [],
            target: $data['target'] ?? 'method',
            targetName: $data['targetName'] ?? '',
            visibility: $data['visibility'] ?? 'public',
        );
    }

    /**
     * @param RuntimeHookMetadata $metadata
     * @return RuntimeHookMetadataData
     */
    public static function toArray(RuntimeHookMetadata $metadata): array
    {
        return [
            'hook' => $metadata->hook,
            'type' => $metadata->type,
            'priority' => $metadata->priority,
            'acceptedArgs' => $metadata->acceptedArgs,
            'once' => $metadata->once,
            'executeIf' => $metadata->executeIf,
            'executeIfParams' => $metadata->executeIfParams,
            'registerIf' => $metadata->registerIf,
            'registerIfParams' => $metadata->registerIfParams,
            'deferRegisterUntilHook' => $metadata->deferRegisterUntilHook,
            'deferRegisterUntilHookParams' => $metadata->deferRegisterUntilHookParams,
            'hookArgNames' => $metadata->hookArgNames,
            'target' => $metadata->target,
            'targetName' => $metadata->targetName,
            'visibility' => $metadata->visibility,
        ];
    }

    /**
     * @param RuntimeHookMetadataData $properties
     * @return self
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }
}