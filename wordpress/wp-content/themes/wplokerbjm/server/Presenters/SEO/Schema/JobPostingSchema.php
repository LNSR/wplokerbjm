<?php

namespace WPLokerBJM\Presenters\SEO\Schema;

class JobPostingSchema
{
    /**
     * Render JobPosting JSON-LD script tag
     * @param array $schema
     * @param int $post_id
     * @return string
     */
    public static function renderSchemaJobPosting(array $schema, int $post_id): string
    {
        $jsonLd = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script type="application/ld+json" data-ld-type="JobPosting">' . $jsonLd . '</script>';
    }

    /**
     * Render ItemList JSON-LD script tag
     * @param array $schema
     * @return string
     */
    public static function renderSchemaItemList(array $schema): string
    {
        $jsonLd = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script type="application/ld+json" data-ld-type="ItemList">' . $jsonLd . '</script>';
    }
}
