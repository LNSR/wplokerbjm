<?php

namespace WPLokerBJM\Services\GraphQL\Hooks\Search;

use GraphQL\Type\Definition\FieldDefinition, GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPLokerBJM\Controllers\GraphQL\Resolvers\JobsDataResolver;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\GraphQL\GraphQLRegistration;
use WPGraphQL\Utils\InstrumentSchema;
use WPGraphQL\Utils\Tracing;
use WPLokerBJM\Core\ContainerRegistryActions;

/*======================================================================
 | SEARCH
 ======================================================================*/

/**
 * Customizes the SQL WHERE clause for WordPress search queries on job posts.
 *
 * This filter intercepts the default WordPress search behavior and replaces it with
 * custom SQL that searches across multiple fields relevant to job listings:
 * - Post titles
 * - Company names (stored in post meta)
 * - Taxonomy terms (e.g., job categories, locations)
 *
 * This enables more comprehensive search results for the job platform, allowing users
 * to find jobs by company name or category even if those terms aren't in the title.
 *
 * Used by: DynamicSearch Graphql endpoint, and any WP_Query with 's' parameter
 * on 'lowongan' post type.
 * @see JobsDataResolver::resolveSearchJobs
 */
class SearchHooks
{

    /**
     * @param string        $search   The current search SQL fragment (may be empty).
     * @param \WP_Query     $wp_query The WP_Query object being executed.
     * @return string Modified search SQL fragment.
     */
    #[Filter('posts_search', 10, 2, deferRegister: true, once: true)]
    public function jobPostsSearchFilterImpl(string $search, \WP_Query $wp_query): string
    {
        global $wpdb;
        $q = (string) ($wp_query->query_vars['s'] ?? '');
        return JobQuery::buildPostsSearchSql($wpdb, $q);
    }

    /**
     * @see InstrumentSchema::wrap_fields
     * @see Tracing::init
     */
    #[Action('graphql_before_resolve_field', 10, 8, once: true, deferRegisterUntilHook: 'init_graphql_request', executeIf: static function (ResolveInfo $info): bool {
        $result = $info->fieldName === GraphQLRegistration::TYPE_SEARCH_JOBS;
        if (!$result) do_action(ContainerRegistryActions::UNREGISTER_DEFERRED_BY_CALLABLE, [SearchHooks::class, 'jobPostsSearchFilterImpl']);
        return $result;
    })]
    public function beforeResolveField(
        mixed $source,
        array $args,
        AppContext $context,
        ResolveInfo $info,
        ?callable $fieldResolver,
        string $typeName,
        string $fieldKey,
        FieldDefinition $field,
    ): void {
        do_action(ContainerRegistryActions::ACTIVATE_DEFERRED_BY_CALLABLE, [$this, 'jobPostsSearchFilterImpl']);
    }
}
