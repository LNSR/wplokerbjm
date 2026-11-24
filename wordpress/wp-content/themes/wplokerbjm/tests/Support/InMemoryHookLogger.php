<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use WPLokerBJM\Core\Container\Support\WPHooks\Logger\HookLoggerInterface;

/**
 * Test logger that keeps records in memory for assertions.
 */
final class InMemoryHookLogger implements HookLoggerInterface
{
    /**
     * @var list<array{level: string, category: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function debug(string $category, string $message, array $context = []): void
    {
        $this->records[] = compact('category', 'message', 'context') + ['level' => 'debug'];
    }

    public function info(string $category, string $message, array $context = []): void
    {
        $this->records[] = compact('category', 'message', 'context') + ['level' => 'info'];
    }

    public function warning(string $category, string $message, array $context = []): void
    {
        $this->records[] = compact('category', 'message', 'context') + ['level' => 'warning'];
    }

    public function error(string $category, string $message, array $context = []): void
    {
        $this->records[] = compact('category', 'message', 'context') + ['level' => 'error'];
    }

    public function flush(): void {}
}
