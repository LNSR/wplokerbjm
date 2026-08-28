<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Shared\Cache\{Cache, CacheKey};
use WPLokerBJM\Models\Schema\PostTypes;
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};

/**
 * Centralized cache purge when posts, meta, terms, or status change.
 *
 * Calling conventions:
 * - For post hooks (e.g., `save_post`, `delete_post`) we pass `($post_id, $post)` so
 *   the method can perform both global purges and per-job invalidation when applicable.
 * - For term hooks (`created_term`, `edited_term`, `delete_term`) we call this method
 *   without arguments (no post context) and it will only purge global caches.
 * - For meta/taxonomy hooks we pass the `object_id` (post id) when available.
 */
class CacheInvalidationHooks
{

    /**
     * @param RedisAdapter    $redisAdapter  Used for direct Redis pattern-based cache deletion.
     */
    public function __construct(private RedisAdapter $redisAdapter) {}
    /**
     * Per-post cache invalidation — fires for EVERY lowongan post change.
     *
     * Never self-unregisters: in a batch of 50 trashed jobs, each one must
     * invalidate its own individual cache entries. The global cache sweep
     * is handled separately by {@see self::purgeGlobalCacheOnce}.
     *
     * Registered only on hooks that carry post context.
     * @var static::class
     */
    #[Action('save_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 2)]
    #[Action('delete_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 1)]
    #[Action('trashed_post', 10, 1)]
    #[Action('delete_attachment', 10, 1)]
    #[Action('transition_post_status', 10, 3)]
    private AnonClassHookMetadata $invalidatePostCache {
        get => $this->invalidatePostCache ??= new class(self::class, __PROPERTY__) extends AnonClassHookMetadata {

            /** @var array<int, bool> */
            private array $snapshotPostID = [];

            public function __invoke(...$args): void
            {
                $post_id = $this->extractPostId($args);

                if ($post_id === null || isset($this->snapshotPostID[$post_id]) && $this->snapshotPostID[$post_id] === true) {
                    return;
                }

                $this->snapshotPostID[(int) $post_id] = true;

                $this->invalidateJobDataCache((int) $post_id);
            }

            /**
             * Extract a post ID from variadic hook arguments, validating post type.
             *
             * @param array $args Hook arguments
             * @return int|null The resolved lowongan post ID, or null if not applicable
             */
            private function extractPostId(array $args): ?int
            {
                $post_id = null;

                foreach ($args as $arg) {
                    if ($arg instanceof \WP_Post) {
                        if ($arg->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                            return null;
                        }
                        $post_id = $arg->ID;
                        continue;
                    }

                    if (is_int($arg)) {
                        $post_id = $arg;
                        continue;
                    }

                    if (is_string($arg) && ctype_digit($arg)) {
                        $post_id = (int) $arg;
                        continue;
                    }
                }

                if ($post_id !== null) {
                    $resolved = get_post($post_id);
                    if ($resolved === null || $resolved->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                        return null;
                    }
                }

                return $post_id;
            }

            /**
             * Invalidate job data caches for a specific lowongan post.
             *
             * @param int $post_id The post ID.
             * @return bool True if any cache entry was deleted.
             */
            private function invalidateJobDataCache(int $post_id): bool
            {
                $deleteResults = Cache::deleteMultiple([
                    CacheKey::JOB_DATA_PREFIX . $post_id,
                    CacheKey::GRAPHQL_JOB_CARD_PREFIX . $post_id,
                    CacheKey::JOB_SCHEMA_PREFIX . $post_id,
                ]);

                return !empty(array_filter($deleteResults));
            }
        };
    }

    /**
     * Global cache purge — fires once per request, then self-unregisters.
     *
     * One comprehensive global sweep per request is sufficient. After the
     * first fire, this handler removes itself from WordPress so that term/
     * meta changes later in the same request don't trigger redundant purges.
     */
    #[Action('save_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 2)]
    #[Action('delete_post_' . PostTypes::POST_TYPE_LOWONGAN, 10, 1)]
    #[Action('trashed_post', 10, 1)]
    #[Action('delete_attachment', 10, 1)]
    #[Action('created_term', 10, 0)]
    #[Action('edited_term', 10, 0)]
    #[Action('delete_term', 10, 0)]
    #[Action('updated_postmeta', 10, 4)]
    #[Action('set_object_terms', 10, 6)]
    #[Action('transition_post_status', 10, 3)]
    private \Closure $purgeGlobalCacheOnce {
        get {
            $propertyName = __PROPERTY__;
            return $this->purgeGlobalCacheOnce ??= function () use ($propertyName) {
                static $alreadyRun = false;
                if ($alreadyRun)
                    return;
                $alreadyRun = true;
                do_action(ContainerRegistryActions::UNREGISTER_BY_CALLABLE, [$this, $propertyName]);
                try {
                    Cache::deleteMultiple([
                        CacheKey::CAROUSEL_JOBS,
                        CacheKey::JOB_LAST_MODIFIED,
                        CacheKey::TAXONOMY_DEPTH_HANDLE,
                        CacheKey::TAXONOMY_DEPTH_LOKASI,
                        CacheKey::TAXONOMY_DEPTH_GENDER,
                        CacheKey::TAXONOMY_DEPTH_PENDIDIKAN,
                    ]);

                    $this->redisAdapter->deletePattern([
                        CacheKey::JOB_GRID_PREFIX . '*',
                        CacheKey::SEARCH_SQL_PREFIX . '*',
                        CacheKey::LOAD_MORE_PREFIX . '*',
                        CacheKey::AUTO_SUGGESTION_PREFIX . '*',
                        CacheKey::POST_TAXONOMIES_PREFIX . '*',
                        CacheKey::GRAPHQL_JOB_DETAIL_PREFIX . '*',
                        CacheKey::GRAPHQL_JOB_CARD_PREFIX . '*',
                        CacheKey::DYNAMIC_SEARCH_PREFIX . '*',
                        CacheKey::SYNC_BOOKMARK_PREFIX . '*',
                        CacheKey::GRAPHQL_JOB_SCHEMA_BATCH_PREFIX . '*',
                        CacheKey::RANKMATH_HEAD_PREFIX . '*',
                        CacheKey::GRAPHQL_ETAG_PREFIX . '*',
                        CacheKey::THEME_DATA . '*',
                    ]);
                } catch (\Exception $e) {
                    Logger::error('Hooks', 'CacheInvalidationHooks::purgeGlobalCacheOnce error: ' . $e->getMessage());
                }
            };
        }
    }
}
