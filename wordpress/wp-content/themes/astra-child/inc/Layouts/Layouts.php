<?php
namespace AstraChild\Layouts;

class Layouts
{
    public function getProps(): array
    {
        $logo = function_exists('get_custom_logo') ? get_custom_logo() : '';

        return [
            'header' => $logo,
        ];
    }
}