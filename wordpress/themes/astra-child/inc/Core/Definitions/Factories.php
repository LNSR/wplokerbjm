<?php

namespace AstraChild\Core\Definitions;

class Factories
{
    public static function getDefinitions(): array
    {
        return [
            // Bind JobDataFactory with specific providers
            \AstraChild\Factories\JobDataFactory::class => fn($c) =>
                new \AstraChild\Factories\JobDataFactory(
                    $c->get('customFieldsProvider'),
                    $c->get('taxonomiesProvider'),
                    $c->get(\AstraChild\Services\CustomField\CustomFieldsService::class),
                    $c->get(\AstraChild\Services\Taxonomy\TaxonomyService::class),
                    $c->get(\AstraChild\Services\CustomField\SocialMediaService::class)
                )
        ];
    }
}
