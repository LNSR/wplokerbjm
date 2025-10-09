<?php

namespace WPLokerBJM\Core\Container\Definitions;
use function DI\get;

class Repositories
{
    public static function getDefinitions(): array
    {
        return [
            'customFieldsProvider' => get(\WPLokerBJM\Repositories\CustomFieldRepository::class),
            'taxonomiesProvider' => get(\WPLokerBJM\Repositories\TaxonomyRepository::class),
        ];
    }
}
