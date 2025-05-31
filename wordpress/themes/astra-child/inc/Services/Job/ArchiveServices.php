<?php
namespace AstraChild\Services\Job;

class ArchiveServices {
    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'forceLowonganArchiveTemplate']);
    }

    /**
     * Force the lowongan archive template when searching.
     *
     * @param \WP_Query $query
     */
    public function forceLowonganArchiveTemplate($query): void
    {
        if (
            !is_admin() &&
            $query->is_main_query() &&
            (
                is_post_type_archive('lowongan') ||
                (isset($_GET['post_type']) && $_GET['post_type'] === 'lowongan')
            )
        ) {
            // If any filter is present, force post type archive context
            if (
                (isset($_GET['cari']) && $_GET['cari'] !== '') ||
                (isset($_GET['lokasi']) && $_GET['lokasi'] !== '') ||
                (isset($_GET['gender']) && $_GET['gender'] !== '') ||
                (isset($_GET['pendidikan']) && $_GET['pendidikan'] !== '')
            ) {
                $query->set('post_type', 'lowongan');
                $query->is_archive = true;
                $query->is_post_type_archive = true;
                $query->is_tax = false;
                $query->is_category = false;
                $query->is_tag = false;
                $query->is_search = false;
            }
        }
    }
}