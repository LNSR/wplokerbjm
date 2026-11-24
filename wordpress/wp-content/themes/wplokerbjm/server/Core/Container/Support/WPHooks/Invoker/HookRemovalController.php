<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};

/**
 * Owns the removal/cleanup lifecycle of a hook invoker.
 *
 * Responsibilities:
 *  - hold the registry-supplied removal callback (once per invoker),
 *  - track the `removed` idempotency flag,
 *  - run the once-consume and lifetime-collect removal paths,
 *  - orchestrate the destructor release: onUnregisterDo → removal callback →
 *    terminal `proxy_released` emission, with every step isolated so a
 *    throwing callback can never skip the terminal event.
 *
 * The invoker keeps its public `isDestructed` flag for BC/observability; this
 * controller keeps its own `released` guard so a manually invoked destructor
 * cannot double-run the cleanup pipeline.
 */
final class HookRemovalController
{
    private ?\Closure $removeCallback = null;
    private bool $removed = false;
    private bool $released = false;

    public function __construct(
        private readonly string $label,
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {}

    /**
     * @param (\Closure(object, bool = false): void)|null $callback
     */
    public function setCleanupCallback(?\Closure $callback): void
    {
        $this->removeCallback ??= $callback;
    }

    public function isRemoved(): bool
    {
        return $this->removed;
    }

    /**
     * Consume a once-hook: fire the removal callback exactly once.
     *
     * @param callable(bool $removed, int $elapsedUs): void $onEmit
     */
    public function consumeOnce(bool $once, object $invoker, callable $onEmit): void
    {
        if (!$once || $this->removed || $this->removeCallback === null) {
            return;
        }

        $startedAt = \hrtime(true);

        try {
            $this->removed = true;
            ($this->removeCallback)($invoker);
        } catch (\Throwable $e) {
            $this->logger->error(__METHOD__, 'Error removing once-hook ' . $this->label . ': ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            $onEmit($this->removed, (int) ((\hrtime(true) - $startedAt) / 1000));
        }
    }

    /**
     * Nuke the registration when its owning instance has been collected.
     *
     * @param callable(bool $removed, int $elapsedUs): void $onEmit
     */
    public function consumeLifetime(object $invoker, callable $onEmit): void
    {
        if ($this->removed || $this->removeCallback === null) {
            return;
        }

        $startedAt = \hrtime(true);

        try {
            $this->removed = true;
            ($this->removeCallback)($invoker);
        } catch (\Throwable $e) {
            $this->logger->error(__METHOD__, 'Error removing lifetime hook ' . $this->label . ': ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            $onEmit($this->removed, (int) ((\hrtime(true) - $startedAt) / 1000));
        }
    }

    /**
     * Destructor release pipeline. Idempotent via the `released` guard.
     *
     * @param callable(bool $removed, int $elapsedUs): void $onEmit
     */
    public function release(object $invoker, ?\Closure $onUnregisterDo, callable $onEmit): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;

        $startedAt = \hrtime(true);

        try {
            if ($onUnregisterDo !== null) {
                $onUnregisterDo();
            }
        } catch (\Throwable $e) {
            $this->logger->error(__METHOD__, 'onUnregisterDo failed for ' . $this->label, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            try {
                if ($this->removeCallback !== null) {
                    ($this->removeCallback)($invoker, true);
                }
            } catch (\Throwable $e) {
                $this->logger->error(__METHOD__, 'Removal callback failed for ' . $this->label, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            } finally {
                $onEmit($this->removed, (int) ((\hrtime(true) - $startedAt) / 1000));
                $this->removeCallback = null;
            }
        }
    }
}
