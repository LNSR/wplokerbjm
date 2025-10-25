<?php
namespace WPLokerBJM\Contracts;

/**
 * Interface DataProviderInterface
 *
 * Defines methods for retrieving data for WordPress.
 */
interface DataProviderInterface
{

    /**
     * Get MetaBox data for a given post ID.
     *
     * @param int $post_id Post ID
     * @return array The data representing the metabox
     */
    public function getMetaBoxData(int $post_id): array;
}

/**
 * Interface HooksInterface
 *
 * Defines methods for registering WordPress actions and filters.
 */
interface HooksInterface
{
    /**
     * Register WordPress actions.
     *
     * @return void
     */
    public function registerActions(): void;

    /**
     * Register WordPress filters.
     *
     * @return void
     */
    public function registerFilters(): void;
}