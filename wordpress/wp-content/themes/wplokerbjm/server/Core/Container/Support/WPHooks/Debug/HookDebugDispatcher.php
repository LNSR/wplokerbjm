<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Exceptions\HookDebugProbeException;
use WPLokerBJM\Core\Container\Support\WPHooks\Resolution\ArgumentResolver;
use WPLokerBJM\Shared\Log\Logger;

/**
 * Dispatches opt-in hook debug probes with an already built DTO.
 *
 * Owns the probe-specific parameter policy:
 *  - DTO by type,
 *  - `self`/`static` from the live owner — never fabricated from the container
 *    in owner-optional (lifecycle) mode,
 *  - other dependencies from the container (with defaults as a mid-teardown
 *    fallback),
 *  - then declared defaults.
 *
 * Failures are logged and swallowed: a probe can never interrupt the hook
 * lifecycle.
 *
 * @phpstan-import-type CallableHookParams from \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider
 * @phpstan-import-type CallablePlan from \WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider
 */
final class HookDebugDispatcher
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ArgumentResolver $resolver,
    ) {}

    /**
     * Invoke an opt-in hook debug probe with the emitted DTO.
     *
     * In owner-optional mode (lifecycle emissions that may fire during
     * teardown), a `self`/`static` parameter is NEVER satisfied from the
     * container: it receives the live owner, `null` when declared `?self`, or
     * the probe is skipped with a logged contract error. Other dependencies
     * still resolve through the container, but a mid-teardown failure falls
     * back to defaults instead of aborting the probe.
     *
     * @param CallablePlan $plan precomputed plan for the debug closure
     */
    public function invokeHookDebug(
        ?\Closure $debug,
        array $plan,
        object $dto,
        string $label = '',
        ?object $owner = null,
        bool $ownerOptional = false,
    ): void {
        if ($debug === null) {
            return;
        }

        try {
            $callable = $owner !== null ? $this->resolver->bindToTarget($debug, $plan, $owner::class) : $debug;

            $values = [];
            foreach ($plan['params'] ?? [] as $param) {
                if ($param['isVariadic']) {
                    continue;
                }
                $values[] = $this->resolveDebugParameter($param, $dto, $label, $owner, $ownerOptional);
            }

            $callable(...$values);
        } catch (\Throwable $e) {
            Logger::error(self::class, 'Hook debug probe failed for ' . $label, [
                'dto' => $dto::class,
                'ownerOptional' => $ownerOptional,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * @param CallableHookParams $param
     */
    private function resolveDebugParameter(
        array $param,
        object $dto,
        string $label,
        ?object $owner,
        bool $ownerOptional = false,
    ): mixed {
        $type = $param['type'] ?? null;

        if (\is_string($type) && $type !== '' && \is_a($dto, $type)) {
            return $dto;
        }

        $isSelf = (bool) ($param['isSelf'] ?? false);
        $allowsNull = (bool) ($param['allowsNull'] ?? false);

        if ($isSelf && $owner !== null) {
            return $owner;
        }

        if (
            $owner !== null
            && (
                $type === 'self'
                || $type === 'static'
                || (\is_string($type) && $type !== '' && \is_a($owner, $type))
            )
        ) {
            return $owner;
        }

        if ($ownerOptional) {
            if ($isSelf) {
                if ($allowsNull) {
                    return null;
                }

                throw HookDebugProbeException::nonNullableSelf($label, $param['name'] ?? 'unknown');
            }

            if (\is_string($type) && $type !== '') {
                try {
                    if ($this->container->has($type)) {
                        return $this->container->get($type);
                    }
                } catch (\Throwable) {
                    // Container may be mid-teardown; fall through to defaults.
                }
            }

            if (!empty($param['hasDefault'])) {
                return $param['default'] ?? null;
            }

            if ($allowsNull) {
                return null;
            }

            throw HookDebugProbeException::unresolved($label, $param['name'] ?? 'unknown', $type);
        }

        return $this->resolver->resolveCallableParam($param, $label);
    }
}
