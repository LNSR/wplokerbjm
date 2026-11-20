<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Invoker;

/**
 * Hook-fire argument shaping for one invoker.
 *
 * Owns the handler's declared parameter names/variadic descriptor and the
 * action/filter pass-through rule, keeping that logic out of the execution
 * pipeline.
 */
final readonly class HookArgumentBag
{
    /**
     * @param array<int, array{name: string, isVariadic: bool}|string> $hookArgNames
     * @param 'action'|'filter' $type
     */
    public function __construct(
        private array $hookArgNames,
        private string $type,
    ) {}

    /**
     * Build named hook arguments for executeIf parameter resolution.
     *
     * @param array<int, mixed> $args
     * @return array<string, mixed>
     */
    public function named(array $args): array
    {
        $names = $this->hookArgNames;
        if ($names === []) {
            return [];
        }

        if (is_string($names[0])) {
            return \array_combine(\array_slice($names, 0, \count($args)), $args) ?: [];
        }

        /** @var list<string> $paramNames */
        $paramNames = array_map(static fn(array $p): string => $p['name'], $names);

        $last = $names[\count($names) - 1];

        // Variadic is always the LAST parameter: forward the whole remainder
        // truthfully under its name — the caller decides what to do with it.
        if (($last['isVariadic'] ?? false)) {
            $headCount = \count($paramNames) - 1;
            $mapped = \array_combine(
                \array_slice($paramNames, 0, \min($headCount, \count($args))),
                \array_slice($args, 0, \min($headCount, \count($args)))
            ) ?: [];
            $mapped[$last['name']] = \array_slice($args, $headCount);

            return $mapped;
        }

        return \array_combine(\array_slice($paramNames, 0, \count($args)), $args) ?: [];
    }

    /**
     * The handler's variadic hook-argument name (plan-B descriptor form only).
     */
    public function variadicName(): ?string
    {
        $names = $this->hookArgNames;
        if ($names === [] || is_string($names[0])) {
            return null;
        }

        $last = $names[\count($names) - 1];
        if (!($last['isVariadic'] ?? false)) {
            return null;
        }

        return $last['name'];
    }

    /**
     * Value a filter must pass through (null for actions) when the handler or
     * its gate fails.
     *
     * @param array<int, mixed> $args
     */
    public function passthrough(array $args): mixed
    {
        return $this->type === 'filter' && \array_key_exists(0, $args) ? $args[0] : null;
    }
}
