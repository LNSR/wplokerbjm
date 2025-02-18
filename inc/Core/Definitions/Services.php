<?php

namespace AstraChild\Core\Definitions;

class Services
{
    public static function getDefinitions(): array
    {
        return [
            // Bind services
            \AstraChild\Services\CustomField\CustomFieldsService::class => \DI\create(),
            \AstraChild\Services\Taxonomy\TaxonomyService::class => \DI\create(),
            \AstraChild\Services\CustomField\SocialMediaService::class => \DI\create(),
            \AstraChild\Services\REST\RESTServices::class => \DI\create(),
            \AstraChild\Services\Job\JobServices::class => \DI\create(),
            \AstraChild\Services\Job\ArchiveServices::class => \DI\create(),
            \AstraChild\Services\PostsManagement\PostsManagement::class => \DI\create(),
        ];
    }
}
