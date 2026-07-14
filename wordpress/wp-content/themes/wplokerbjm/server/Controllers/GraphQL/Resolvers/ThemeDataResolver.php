<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;
use ThemeData;
use WPLokerBJM\Shared\Log\Logger;
/**
 * Resolver for theme data in GraphQL.
 *
 * Fetches theme-related data for GraphQL queries, including site title,
 * description, and logo information. Caches results internally to optimize
 * repeated requests.
 * @phpstan-import-type ThemeData from \WPLokerBJM\Core\Theme\ThemeInject
 */
class ThemeDataResolver
{
    public function __construct(
        private readonly \WPLokerBJM\Services\GraphQL\GraphQLData $graphqlData,
    ) {
    }
    /**
     * Resolve theme data for GraphQL endpoint.
     * @return ThemeData
     */
    public function resolveThemeData(): array
    {
        try {
            return $this->graphqlData->getThemeData(); // cached internally
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveThemeData error: ' . $e->getMessage());
            return [
                'data' => null,
            ];
        }
    }
}