<?php

namespace WPLokerBJM\Core\Container\Definitions;

use function DI\autowire;
use function DI\get;

class Factories
{
    public static function getDefinitions(): array
    {
        return [
            \WPLokerBJM\Factories\JobDataFactory::class => autowire()
                ->constructor(
                    get('customFieldsProvider'),
                    get('taxonomiesProvider')
                ),
        ];
    }
}
