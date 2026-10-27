<?php

namespace WPLokerBJM\Services\GraphQL\Hooks\Search;

use DI\Attribute\Injectable;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPLokerBJM\Controllers\GraphQL\Resolvers\JobsDataResolver;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Services\GraphQL\GraphQLRegistration;
use WPGraphQL\Utils\InstrumentSchema;
use WPGraphQL\Utils\Tracing;
use WPLokerBJM\Core\Wordpress\ContainerRegistryHookEvent;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Transport\GraphQL\Registration\Job\JobRegistrationTypes;

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
final class SearchJobHooks
{

    /**
     * @param \wpdb|null $wpdb
     */
    public function __construct(private ?\wpdb $wpdb = null)
    {
        $this->wpdb ??= $GLOBALS['wpdb'];
    }

    /**
     * @see InstrumentSchema::wrap_fields
     * @see Tracing::init
     */
    #[Action(
        'graphql_before_resolve_field',
        10,
        4,
        tag: ['graphql'],
        deferRegister: true,
        executeIf: static function (ResolveInfo $info): bool {
            return $info->fieldName === JobRegistrationTypes::TYPE_SEARCH_JOBS;
        }
    )]
    private function boot(
        mixed $source,
        array $args,
        AppContext $context,
        ResolveInfo $info,
    ) {
        \do_action(ContainerRegistryHookEvent::ACTIVATE_DEFERRED_BY_CLASS, __CLASS__);
    }

    /**
     * @param string        $search   The current search SQL fragment (may be empty).
     * @param \WP_Query     $wp_query The WP_Query object being executed.
     * @return string Modified search SQL fragment.
     */
    #[Filter('posts_search', acceptedArgs: 2, deferRegister: true, once: true)]
    public function filterPostsSearch(string $search, \WP_Query $wp_query): string
    {
        return JobQuery::buildPostsSearchSql(
            $this->wpdb,
            (string) ($wp_query->query_vars['s'] ?? '')
        );
    }
}
