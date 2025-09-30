<?php
namespace AstraChild\Contracts;

interface DataProviderInterface {

    /**
     * Get MetaBox data for a given post ID.
     *
     * @param int $post_id Post ID
     * @return array The data representing the metabox
     */
    public function getMetaBoxData(int $post_id): array;
}