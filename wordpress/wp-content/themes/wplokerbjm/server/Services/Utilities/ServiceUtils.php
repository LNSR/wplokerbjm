<?php

declare(strict_types=1);

namespace WPLokerBJM\Services\Utilities;

class ServiceUtils
{
    /**
     * @param mixed $value
     * @return bool
     */
    public static function hasNonEmptyValue($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasNonEmptyValue($item)) {
                    return true;
                }
            }
            return false;
        }

        return trim((string) $value) !== '';
    }
}