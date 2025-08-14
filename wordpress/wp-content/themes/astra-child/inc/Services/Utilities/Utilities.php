<?php
namespace AstraChild\Services\Utilities;

class Utilities
{
    public static function parseMulti($param)
    {
        if (is_array($param))
            return $param;
        if (is_string($param) && strpos($param, ',') !== false) {
            return array_filter(array_map('trim', explode(',', $param)));
        }
        return $param ? [$param] : [];
    }
}