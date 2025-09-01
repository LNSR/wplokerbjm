<?php

namespace AstraChild\Core\Definitions;

/**
 *
 * ## Init Service Array Injection
 * The definition for \AstraChild\Core\Init::class demonstrates how to inject an array of service objects
 * into the Init class constructor. This array is constructed by resolving each required service from the container.
 *
 * The Init class (see {@link \AstraChild\Core\Init}) expects an array of services, each of which may implement
 * HooksInterface. When Init::initialize() is called, it will iterate through this array and register
 * WordPress hooks for each service that supports it.
 *
 * This pattern allows you to batch-inject and initialize multiple core services in a single place,
 * keeping your theme's bootstrap logic organized and maintainable.
 *
 * * @see \AstraChild\Core\Init
 */
class Core
{
    public static function getDefinitions(): array
    {
        return [
            // The Init service receives an array of core service objects.
            // See Init.php for how this array is used to register hooks.
            \AstraChild\Core\Init::class => fn($c) =>
                new \AstraChild\Core\Init(
                    [
                        $c->get(\AstraChild\Core\Enqueue::class),
                        $c->get(\AstraChild\Core\Hooks::class),
                        $c->get(\AstraChild\Models\Schema\CustomFields::class),
                        $c->get(\AstraChild\Models\Schema\Taxonomies::class),
                        $c->get(\AstraChild\Models\Schema\PostTypes::class),
                        $c->get(\AstraChild\Services\REST\RESTRoute::class),
                        $c->get(\AstraChild\Services\Job\ArchiveServices::class),
                        $c->get(\AstraChild\Services\PostsManagement\SSG\PostsCRUDListener::class),
                        $c->get(\AstraChild\Services\PostsManagement\SSG\RedirectToSSG::class),
                        $c->get(\AstraChild\Services\Cron\CronService::class),
                        $c->get(\AstraChild\Services\PostsManagement\PostsListener::class),
                        $c->get(\AstraChild\Services\Taxonomy\TaxonomyListener::class)
                    ],
                ),
        ];
    }
}
