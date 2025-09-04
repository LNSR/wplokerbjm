<?php

namespace AstraChild\Core\Container\Definitions;

use function DI\autowire;
use function DI\get;

class Factories
{
    public static function getDefinitions(): array
    {
        return [
            \AstraChild\Factories\JobDataFactory::class => autowire()
                ->constructor(
                    get('customFieldsProvider'),
                    get('taxonomiesProvider')
                ),
        ];
    }
}
