<?php
namespace WPLokerBJM\Shared\Cache;
/**
 * Cache key definitions
 *
 * Centralized definition of all cache keys and prefixes used in the application.
 * Helps avoid typos and ensures consistency across the codebase.
 */
class CacheKey
{
    // General cache prefixes and keys
    const OBJECT_CACHE_PREFIX = 'wplokerbjm_obj_';
    const THEME_DATA = 'theme_data';

    // Job-related
    const JOB_DATA_PREFIX = 'job_data_';
    const JOB_SCHEMA_PREFIX = 'job_schema_';
    const JOB_LAST_MODIFIED = 'job_last_modified';

    // GraphQL API
    const GRAPHQL_JOB_CARD_PREFIX = 'graphql_job_card_';
    const GRAPHQL_JOB_DETAIL_PREFIX = 'graphql_job_detail_';
    const GRAPHQL_JOB_SCHEMA_BATCH_PREFIX = 'graphql_job_schema_batch_';
    const AUTO_SUGGESTION_PREFIX = 'auto_suggestion_';
    const LOAD_MORE_PREFIX = 'load_more_';
    const DYNAMIC_SEARCH_PREFIX = 'dynamic_search_';
    const SYNC_BOOKMARK_PREFIX = 'sync_bookmark_';
    const RANKMATH_HEAD_PREFIX = 'rankmath_head_';

    // Presenters
    const CAROUSEL_JOBS = 'carousel_jobs';
    const JOB_GRID_PREFIX = 'job_grid_';

    // Taxonomy
    const POST_TAXONOMIES_PREFIX = 'post_taxonomies_';
    const TAXONOMY_DEPTH_HANDLE = 'taxonomy_depth_handle';
    const TAXONOMY_DEPTH_LOKASI = 'taxonomy_depth_lokasi';
    const TAXONOMY_DEPTH_GENDER = 'taxonomy_depth_gender';
    const TAXONOMY_DEPTH_PENDIDIKAN = 'taxonomy_depth_pendidikan';

    // Query Builders
    const SEARCH_SQL_PREFIX = 'search_sql_';

    // ETag
    const GRAPHQL_ETAG_PREFIX = 'graphql_etag_';

    /**
     * Prefixes whose keys are recorded in the Redis purge registry
     * ({@see WPLokerBJM\Shared\Cache\CacheRegistry::record()}) and bulk-deleted by
     * {@see WPLokerBJM\Adapter\RedisAdapter::deletePattern()}.
     */
    const PATTERN_PURGE_PREFIXES = [
        self::JOB_GRID_PREFIX,
        self::SEARCH_SQL_PREFIX,
        self::LOAD_MORE_PREFIX,
        self::AUTO_SUGGESTION_PREFIX,
        self::POST_TAXONOMIES_PREFIX,
        self::GRAPHQL_JOB_DETAIL_PREFIX,
        self::GRAPHQL_JOB_CARD_PREFIX,
        self::DYNAMIC_SEARCH_PREFIX,
        self::SYNC_BOOKMARK_PREFIX,
        self::GRAPHQL_JOB_SCHEMA_BATCH_PREFIX,
        self::RANKMATH_HEAD_PREFIX,
        self::GRAPHQL_ETAG_PREFIX,
        self::THEME_DATA,
    ];
}