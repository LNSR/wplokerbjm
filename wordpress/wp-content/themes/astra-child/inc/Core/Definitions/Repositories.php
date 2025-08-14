<?php

namespace AstraChild\Core\Definitions;
use function DI\get;

class Repositories
{
    public static function getDefinitions(): array
    {
        return [
            'customFieldsProvider' => get(\AstraChild\Repositories\CustomFieldRepository::class),
            'taxonomiesProvider' => get(\AstraChild\Repositories\TaxonomyRepository::class),
        ];
    }
}
