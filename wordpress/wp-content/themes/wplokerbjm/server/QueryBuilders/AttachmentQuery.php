<?php

namespace WPLokerBJM\QueryBuilders;

/**
 * Query builder helpers for attachments.
 */
class AttachmentQuery
{
    /**
     * Return args suitable for `get_posts()` to fetch attachment IDs for a parent post.
     *
     * @param int $parent_id
     * @param bool $only_ids If true, return only IDs (fields => 'ids').
     * @return array
     */
    public static function byParentArgs(int $parent_id, bool $only_ids = true): array
    {
        $args = [
            'post_parent' => $parent_id,
            'post_type'   => 'attachment',
            'numberposts' => -1,
            'post_status' => 'any',
        ];

        if ($only_ids) {
            $args['fields'] = 'ids';
        }

        return $args;
    }
}
