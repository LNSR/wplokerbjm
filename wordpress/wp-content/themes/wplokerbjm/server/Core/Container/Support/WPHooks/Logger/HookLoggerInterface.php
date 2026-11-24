<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Logger;

/**
 * Framework-scoped logger contract for the WPHooks subsystem.
 *
 * The first argument is the log category (usually `__METHOD__`), not a
 * Monolog channel, so existing call sites keep their shape while the backend
 * stays swappable through container injection.
 */
interface HookLoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $category, string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $category, string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $category, string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $category, string $message, array $context = []): void;

    /**
     * Flush buffered records for backends that batch writes.
     */
    public function flush(): void;
}
