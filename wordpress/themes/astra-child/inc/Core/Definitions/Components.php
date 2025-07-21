<?php

namespace AstraChild\Core\Definitions;

class Components
{
    public static function getDefinitions(): array
    {
        return [
            \AstraChild\Components\JobCard::class => fn($c) =>
                new \AstraChild\Components\JobCard(
                    $c->get(\AstraChild\Repositories\JobRepository::class),
                ),
            \AstraChild\Components\JobGrid::class => fn($c) =>
                new \AstraChild\Components\JobGrid(
                    $c->get(\AstraChild\Components\JobCard::class),
                    $c->get(\AstraChild\Services\Job\JobServices::class),
                    $c->get(\AstraChild\Services\REST\RESTData::class),
                ),
            \AstraChild\Components\JobCarousel::class => fn($c) =>
                new \AstraChild\Components\JobCarousel(),
            \AstraChild\Components\Hero::class => fn($c) =>
                new \AstraChild\Components\Hero(
                    $c->get(\AstraChild\Repositories\TaxonomyRepository::class),
                ),
        ];
    }
}