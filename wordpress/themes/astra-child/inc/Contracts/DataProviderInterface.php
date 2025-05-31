<?php
namespace AstraChild\Contracts;

interface DataProviderInterface {

    /**
     * Get MetaBox data for a given post ID.
     *
     * @param int $post_id Post ID
     * @return object The entity representing the data
     */
    public function getMetaBoxData(int $post_id): mixed;
}