<?php

namespace WPLokerBJM\Presenters\Schema;

class JobPostingSchema
{
    /**
     * Render JobPosting JSON-LD script tag
     * @param array $schema
     * @param int $post_id
     * @return string
     */
    public static function renderSchema(array $schema, int $post_id): string
    {
        $jsonLd = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return '<script type="application/ld+json" data-ld-type="JobPosting" data-ld-id="jobposting-' . intval($post_id) . '">' . $jsonLd . '</script>';
    }

    /**
     * Render multiple JobPosting JSON-LD script tags
     * @param array $schemas
     * @param array $post_ids
     * @return string
     */
    public static function renderMultiple(array $schemas, array $post_ids): string
    {
        $scripts = [];
        foreach ($schemas as $index => $schema) {
            $post_id = $post_ids[$index] ?? 0;
            $scripts[] = self::renderSchema($schema, $post_id);
        }
        return implode('', $scripts);
    }
}
