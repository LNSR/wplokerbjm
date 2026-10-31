<?php

namespace WPLokerBJM\Core\Wordpress;

use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
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
class CachePurgeHandler
{

    /**
     * @param RedisAdapter $redisAdapter  Used for direct Redis pattern-based cache deletion.
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
     * @phpstan-ignore-next-line
     * @var __CLASS__::class
     */
    #[Action('init', 10, 0, once: true)]
    public private(set) ?ModuleClassHookMetadata $invalidatePostCache {
        get {
            return $this->invalidatePostCache ??= new class(__CLASS__, __PROPERTY__) extends ModuleClassHookMetadata {
                /** @var array<int, bool> */
                public private(set) array $snapshotPostID = [];

                public function __invoke(): void
                {
                    do_action(InstanceRuntimeRegistryHookEvent::REGISTER_HOOKS, $this);
                }

                #[Action('wp_after_insert_post', 10, 4)]
                public function onAfterInsertPost(int $postId, \WP_Post $post, bool $update, ?\WP_Post $postBefore): void
                {
                    $this->invalidatePostOnce($post);
                }

                #[Action('before_delete_post', 10, 2)]
                public function onBeforeDeletePost(int $postId, \WP_Post $post): void
                {
                    $this->invalidatePostOnce($post);
                }

                private function invalidatePostOnce(\WP_Post $post): void
                {
                    if ($post->post_type !== PostTypes::POST_TYPE_LOWONGAN) {
                        return;
                    }

                    $postId = (int) $post->ID;
                    if (isset($this->snapshotPostID[$postId])) {
                        return;
                    }

                    try {
                        $this->snapshotPostID[$postId] = true;
                        Cache::deleteMultiple([
                            CacheKey::JOB_DATA_PREFIX . $postId,
                            CacheKey::GRAPHQL_JOB_CARD_PREFIX . $postId,
                            CacheKey::JOB_SCHEMA_PREFIX . $postId,
                        ]);
                    } catch (\Throwable $e) {
                        Logger::error($this->constructCurrentClassIdentity, 'Error:', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    }
                }
            };
        }
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
            static $__propertyName = __PROPERTY__;
            return $this->purgeGlobalCacheOnce ??= function () use ($__propertyName) {
                static $alreadyRun = false;
                if ($alreadyRun) return;
                $alreadyRun = true;
                do_action(ContainerRegistryHookEvent::UNREGISTER_BY_CALLABLE, [$this, $__propertyName]);
                try {
                    Cache::deleteMultiple([
                        CacheKey::CAROUSEL_JOBS,
                        CacheKey::JOB_LAST_MODIFIED,
                        CacheKey::TAXONOMY_DEPTH_HANDLE,
                        CacheKey::TAXONOMY_DEPTH_LOKASI,
                        CacheKey::TAXONOMY_DEPTH_GENDER,
                        CacheKey::TAXONOMY_DEPTH_PENDIDIKAN,
                    ]);

                    $this->redisAdapter->deletePattern(array_map(
                        static fn(string $prefix): string => $prefix . '*',
                        CacheKey::PATTERN_PURGE_PREFIXES,
                    ));
                } catch (\Throwable $e) {
                    Logger::error(static::class, '::' . $__propertyName . ' Error: ', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            };
        }
    }
}
