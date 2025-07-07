<?php

namespace AstraChild\Core\Definitions;

class Models {
    public static function getDefinitions(): array {
        return [
            // Default
            \AstraChild\Contracts\DataProviderInterface::class => fn() =>
                new \AstraChild\Repositories\CustomFieldRepository(),

            // Named definitions for multiple implementations
            'customFieldsProvider' => \DI\get(\AstraChild\Repositories\CustomFieldRepository::class),
            'taxonomiesProvider'   => \DI\get(\AstraChild\Repositories\TaxonomyRepository::class),
        ];
    }
}