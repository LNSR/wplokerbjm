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
     * @note All properties must be public
     * @return TShape
     */
    public function toArray(): array
    {
        return get_object_vars($this);
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
