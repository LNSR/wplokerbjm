<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks;

readonly class HookRegistration
{
    public function __construct(
        public string $class,
        public string $method,
        public string $type,
        public string $hook,
        public int $priority,
        public int $acceptedArgs,
        public bool $defer,
        public string $target,
        public string $visibility,
        public ?\Closure $condition = null,
        public array $conditionParams = [],
        public array $tags = [],
    ) {
    }

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
            condition: $data['condition'] ?? null,
            conditionParams: $data['condition_params'] ?? [],
            tags: $data['tags'] ?? [],
        );
    }

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
            'condition' => $this->condition,
            'condition_params' => $this->conditionParams,
            'tags' => $this->tags,
        ];
    }
}
/**
 * Immutable structural key identifying a hook registration.
 *
 * Generates the canonical string used as the array key in the registry
 * ({@see WPHooksRegistry}) and answers structural match questions
 * (by class, or class+method) without string prefix parsing.
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
}