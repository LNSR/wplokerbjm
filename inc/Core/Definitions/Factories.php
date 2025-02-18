<?php

namespace AstraChild\Core\Definitions;

class Factories
{
    public static function getDefinitions(): array
    {
        return [
            // Bind JobDataFactory with specific providers
            \AstraChild\Factories\JobDataFactory::class => \DI\create()->constructor(
                \DI\get(entryName: 'customFieldsProvider'),
                \DI\get(entryName: 'taxonomiesProvider'),
                \DI\get(entryName: \AstraChild\Services\CustomField\CustomFieldsService::class),
                \DI\get(entryName: \AstraChild\Services\Taxonomy\TaxonomyService::class),
                \DI\get(entryName: \AstraChild\Services\CustomField\SocialMediaService::class)
            )
        ];
    }
}
