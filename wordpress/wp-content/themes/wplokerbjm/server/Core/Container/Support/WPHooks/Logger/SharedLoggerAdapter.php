<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Logger;

/**
 * Adapter from the WPHooks logger contract to the project's shared logger.
 *
 * Replace this binding with another `HookLoggerInterface` implementation to
 * swap the backend without touching WPHooks call sites.
 */
final class SharedLoggerAdapter implements HookLoggerInterface
{
    public function debug(string $category, string $message, array $context = []): void
    {
        \WPLokerBJM\Shared\Log\Logger::debug($category, $message, $context);
    }

    public function info(string $category, string $message, array $context = []): void
    {
        \WPLokerBJM\Shared\Log\Logger::info($category, $message, $context);
    }

    public function warning(string $category, string $message, array $context = []): void
    {
        \WPLokerBJM\Shared\Log\Logger::warning($category, $message, $context);
    }

    public function error(string $category, string $message, array $context = []): void
    {
        \WPLokerBJM\Shared\Log\Logger::error($category, $message, $context);
    }

    public function flush(): void
    {
        \WPLokerBJM\Shared\Log\Logger::flush();
    }
}
