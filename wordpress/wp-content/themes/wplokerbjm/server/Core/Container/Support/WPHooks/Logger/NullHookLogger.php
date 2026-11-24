<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Logger;

/**
 * No-op logger for standalone usage, CLI tools, and tests that do not care
 * about WPHooks diagnostics.
 */
final class NullHookLogger implements HookLoggerInterface
{
    public function debug(string $category, string $message, array $context = []): void {}

    public function info(string $category, string $message, array $context = []): void {}

    public function warning(string $category, string $message, array $context = []): void {}

    public function error(string $category, string $message, array $context = []): void {}

    public function flush(): void {}
}
