<?php

declare(strict_types=1);

namespace WPLokerBJM\Shared\Utilities\DTO;

/**
 * @template TShape
 * @suppress PHP0441
 */
abstract readonly class AbstractDTO
{
    /**
     * @note All properties must be public, this only expose public variables
     * @return TShape
     */
    public function toArray(): array
    {
        /** @var \Closure(static): TShape $template */
        static $template = static fn(object $object): array => get_object_vars($object);
        return $template->bindTo(null, null)($this);
    }

    /**
     * @param TShape $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(...$data);
    }

    /**
     * @param TShape $properties
     * @return static
     */
    public static function __set_state(array $properties): static
    {
        return new static(...$properties);
    }
}
