<?php

namespace WPLokerBJM\Core\Container\Definitions;

use function DI\{autowire, get};

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
