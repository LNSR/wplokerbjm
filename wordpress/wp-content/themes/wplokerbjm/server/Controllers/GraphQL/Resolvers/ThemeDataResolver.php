<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;
use WPLokerBJM\Core\Theme\ThemeProp;
use WPLokerBJM\Shared\Log\Logger;
use DI\Attribute\Injectable;

/**
 * Resolver for theme data in GraphQL.
 *
 * Fetches theme-related data for GraphQL queries, including site title,
 * description, and logo information. Caches results internally to optimize
 * repeated requests.
 * @phpstan-import-type ThemeData from ThemeProp
 */
#[Injectable(lazy: true)]
class ThemeDataResolver
{
    /**
     * Resolve theme data for GraphQL endpoint.
     * @return ThemeData
     */
    public function resolveThemeData(): array
    {
        try {
            return \apply_filters(ThemeProp::THEME_HOOK, []); // cached internally
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveThemeData error: ' . $e->getMessage());
            return [];
        }
    }
}