<?php

namespace AstraChild\Core\Definitions;

class Controllers
{
    public static function getDefinitions(): array
    {
        return [
            \AstraChild\Controllers\REST\TaxonomyDepth::class => fn($c) =>
                new \AstraChild\Controllers\REST\TaxonomyDepth(
                    $c->get(\AstraChild\Services\Taxonomy\TaxonomyService::class),
                    $c->get(\AstraChild\Repositories\TaxonomyRepository::class)
                ),
            \AstraChild\Controllers\REST\AutoSuggestionSearch::class => fn($c) =>
                new \AstraChild\Controllers\REST\AutoSuggestionSearch(),
            \AstraChild\Controllers\REST\LoadMore::class => fn($c) =>
                new \AstraChild\Controllers\REST\LoadMore(
                    $c->get(\AstraChild\Services\REST\RESTData::class)
                ),
            \AstraChild\Controllers\REST\Carousel::class => fn($c) =>
                new \AstraChild\Controllers\REST\Carousel(
                    $c->get(\AstraChild\Services\REST\RESTData::class)
                ),
            \AstraChild\Controllers\REST\SingleOverlay::class => fn($c) =>
                new \AstraChild\Controllers\REST\SingleOverlay(
                    $c->get(\AstraChild\Services\REST\RESTData::class)
                ),
            \AstraChild\Controllers\REST\DynamicSearch::class => fn($c) =>
                new \AstraChild\Controllers\REST\DynamicSearch(
                    $c->get(\AstraChild\Services\REST\RESTData::class)
                ),
        ];
    }
}
