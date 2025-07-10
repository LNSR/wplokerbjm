<?php

namespace AstraChild\Core\Definitions;

class Services
{
    public static function getDefinitions(): array
    {
        return [
            // Bind services
            \AstraChild\Services\CustomField\CustomFieldsService::class => fn($c) =>
                new \AstraChild\Services\CustomField\CustomFieldsService(),
            \AstraChild\Services\Taxonomy\TaxonomyService::class => fn($c) =>
                new \AstraChild\Services\Taxonomy\TaxonomyService(),
            \AstraChild\Services\CustomField\SocialMediaService::class => fn($c) =>
                new \AstraChild\Services\CustomField\SocialMediaService(),
            \AstraChild\Services\REST\RestRoute::class => fn($c) =>
                new \AstraChild\Services\REST\RestRoute(
                    $c->get(\AstraChild\Controllers\REST\TaxonomyDepth::class),
                    $c->get(\AstraChild\Controllers\REST\AutoSuggestionSearch::class),
                    $c->get(\AstraChild\Controllers\REST\LoadMore::class),
                    $c->get(\AstraChild\Controllers\REST\DynamicSearch::class),
                    $c->get(\AstraChild\Controllers\REST\Carousel::class),
                    $c->get(\AstraChild\Controllers\REST\SingleOverlay::class)
                ),
            \AstraChild\Services\REST\RESTData::class => fn($c) =>
                new \AstraChild\Services\REST\RESTData(
                    $c->get(\AstraChild\Repositories\JobRepository::class),
                    $c->get(\AstraChild\Factories\JobDataFactory::class)
                ),
            \AstraChild\Services\Job\JobServices::class => fn($c) =>
                new \AstraChild\Services\Job\JobServices(
                    $c->get(\AstraChild\Repositories\JobRepository::class),
                    $c->get(\AstraChild\Factories\JobDataFactory::class),
                    $c->get(\AstraChild\Services\CustomField\SocialMediaService::class)
                ),
            \AstraChild\Services\Job\ArchiveServices::class => fn($c) =>
                new \AstraChild\Services\Job\ArchiveServices(),
            \AstraChild\Services\PostsManagement\PostsManagement::class => fn($c) =>
                new \AstraChild\Services\PostsManagement\PostsManagement(),
        ];
    }
}
