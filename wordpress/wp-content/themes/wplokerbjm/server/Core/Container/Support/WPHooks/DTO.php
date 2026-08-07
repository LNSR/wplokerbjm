<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;

/**
 * @phpstan-type HookType array{
 *  class: string,
 *  method: string,
 *  type: string,
 *  hook: string|\Closure,
 *  priority: int,
 *  accepted_args: int,
 *  defer: bool,
 *  target: string,
 *  visibility: string,
 *  execute_if: \Closure|null,
 *  execute_if_params: array,
 *  register_if: \Closure|null,
 *  register_if_params: array,
 *  hook_params: array,
 *  tags: array,
 *  tag_callable: \Closure|null,
 *  tag_callable_params: array,
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
        public bool $defer,
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
            defer: $data['defer'] ?? false,
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
        );
    }

    /**
     * Summary of toArray
     * @return HookType
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'method' => $this->method,
            'type' => $this->type,
            'hook' => $this->hook,
            'priority' => $this->priority,
            'accepted_args' => $this->acceptedArgs,
            'defer' => $this->defer,
            'target' => $this->target,
            'visibility' => $this->visibility,
            'execute_if' => $this->executeIf,
            'execute_if_params' => $this->executeIfParams,
            'register_if' => $this->registerIf,
            'register_if_params' => $this->registerIfParams,
            'hook_params' => $this->hookParams,
            'hook_args' => $this->hookArgs,
            'tags' => $this->tags,
            'tag_callable' => $this->tagCallable,
            'tag_callable_params' => $this->tagCallableParams,
        ];
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