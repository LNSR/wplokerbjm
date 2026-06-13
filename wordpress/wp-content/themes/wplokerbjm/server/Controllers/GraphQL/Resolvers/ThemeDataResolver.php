<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;
use WPLokerBJM\Shared\Log\Logger;

class ThemeDataResolver
{
    public function __construct(
        private readonly \WPLokerBJM\Services\GraphQL\GraphQLData $graphqlData,
    ) {
    }
    /**
     * Resolve theme data for GraphQL endpoint.
     *
     * @return array{logo: array{logoUrl: string, logoSrcset: string, logoSizes: string, logoDecoding: string, logoWidth: int, logoHeight: int}, wpRestNonce: string, siteIconTags: string}|array{data: null}
     */
    public function resolveThemeData(): array
    {
        try {
            $themeData = $this->graphqlData->getThemeData(); // cached internally

            return $themeData;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveThemeData error: ' . $e->getMessage());
            return [
                'data' => null,
            ];
        }
    }
}