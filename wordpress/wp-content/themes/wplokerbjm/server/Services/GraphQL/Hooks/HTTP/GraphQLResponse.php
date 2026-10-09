<?php

namespace WPLokerBJM\Services\GraphQL\Hooks\HTTP;

use GraphQL\Executor\ExecutionResult;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter, Inject};
use WPLokerBJM\Core\Wordpress\Plugins\PluginList;
use WPLokerBJM\Shared\Utilities\{SharedUtils};
use WP_User;
use WPLokerBJM\Core\Wordpress\ContainerRegistryEvent;
use WPLokerBJM\Core\Wordpress\InstanceRuntimeRegistryEvent;
use WPLokerBJM\Core\Wordpress\Plugins\ThirdParty\Integrations\LiteSpeedGraphQLIntegration;
use WPLokerBJM\Services\GraphQL\ETag\WPGraphQLETag;
use WPLokerBJM\Transport\GraphQL\Registration\GraphQLRegistration;

/**
 * @phpstan-import-type GraphQLDataType from GraphQLRegistration
 */
final class GraphQLResponse
{
    public function __construct(private WPGraphQLETag $eTag) {}
    /**
     * @see \WPGraphQL\Router::prepare_headers;
     * Router passes: $status_code, $_deprecated, $response, $query, $operation_name, $variables, $user
     */
    #[Filter('graphql_response_status_code', 11, 7, deferRegister: true, tag: ['graphql'])]
    public function setGraphQLResponseStatusCode(
        int $http_status_code,
        ?ExecutionResult $graphql_response,
        ?ExecutionResult $_deprecated = null,
        string $query = '',
        string $operation_name = '',
        ?array $variables = null,
        ?WP_User $user = null,
    ): int {
        $http_status_code = $this->checkJwt401Response($http_status_code, $graphql_response);
        $this->eTag->computeAndStore($graphql_response, $query, $operation_name, (array) $variables, $user);
        return $http_status_code;
    }

    /**
     * Check JWT 401 implementation.
     * @param int $http_status_code
     * @return int
     */
    private function checkJwt401Response(
        int $http_status_code,
        ?ExecutionResult $graphql_response,
    ): int {
        /**
         * @var GraphQLDataType $data
         */
        $data = $graphql_response->data ?? null;
        if (array_key_exists('jwt', $data) && !$data['jwt']) {
            return 401;
        }
        return $http_status_code;
    }
}
