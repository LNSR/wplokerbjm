<?php
namespace WPLokerBJM\Controllers\GraphQL\Resolvers;
use WPLokerBJM\Shared\Log\Logger;

class ThemeDataResolver
{
    public function __construct(
        private readonly \WPLokerBJM\Services\GraphQL\GraphQLData $graphqlData,
    ) {
    }
    public function resolveThemeData(): array
    {
        try {
            $themeData = $this->graphqlData->getThemeData(); // cached internally
            $result = [
                'data' => $themeData,
            ];

            return $result;
        } catch (\Exception $e) {
            Logger::error('GraphQL', 'ThemeDataResolver::resolveThemeData error: ' . $e->getMessage());
            return [
                'data' => null,
            ];
        }
    }
}