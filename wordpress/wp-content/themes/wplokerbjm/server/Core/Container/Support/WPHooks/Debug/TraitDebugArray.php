<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Recursively flattens debug DTOs into arrays.
 *
 * `AbstractDataObject::toArray()` is intentionally shallow (`get_object_vars`),
 * but debug payloads nest snapshots/results and carry backed enums. Consumers
 * commonly persist the result (`$self->debug[] = $dto->toArray()`), so the
 * nested graph must already be scalar.
 *
 * @internal
 */
trait TraitDebugArray
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> */
        return self::debugNormalize($this);
    }

    private static function debugNormalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof AbstractDataObject) {
            /** @var \Closure(object): array<string, mixed> $extract */
            static $extract = \Closure::bind(get_object_vars(...), null, null);

            return \array_map(self::debugNormalize(...), $extract($value));
        }

        if (\is_array($value)) {
            return \array_map(self::debugNormalize(...), $value);
        }

        return $value;
    }
}
