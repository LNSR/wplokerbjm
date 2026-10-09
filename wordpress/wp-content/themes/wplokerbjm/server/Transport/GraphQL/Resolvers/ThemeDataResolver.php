<?php

namespace WPLokerBJM\Transport\GraphQL\Resolvers;

use WPLokerBJM\Core\Wordpress\Theme\ThemeHooks;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Services\GraphQL\GraphQLThemeData;;

/**
 * Resolver for theme data in GraphQL.
 *
 * Fetches theme-related data for GraphQL queries, including site title,
 * description, and logo information. Caches results internally to optimize
 * repeated requests.
 * @phpstan-import-type ThemeData from ThemeHooks
 */
#[Injectable(lazy: true)]
class ThemeDataResolver
{

    public function __construct(private GraphQLThemeData $graphqlThemeData) {}

    /**
     * Resolve theme data for GraphQL endpoint.
     * @return ThemeData
     */
    public function resolveThemeData(): array
    {
        try {
            return $this->graphqlThemeData->createThemeData();
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveThemeData error: ' . $e->getMessage());
            return [];
        }
    }
}
