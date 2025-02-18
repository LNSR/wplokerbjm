<?php
/**
 * Template part for displaying the sidebar content
 * 
 * @package Astra-Child
 */

// Access variables passed from the view
$recent_jobs_data = get_query_var('recent_jobs_data', null);
$job_controller = get_query_var('job_controller', null);
$options = get_query_var('options', []);
$view = get_query_var('view', null);
?>

<div class="w-full lg:auto px-4">
    <aside class="sticky top-24 bg-white rounded-lg shadow-md p-6 divide-y divide-gray-200">
        <!-- Recent Jobs Section -->
        <?php if ($options['show_recent_jobs']): ?>
        <section class="pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Lowongan Terbaru</h3>
            <?php $view->renderRecentJobs($recent_jobs_data, $job_controller); ?>
        </section>
        <?php endif; ?>

        <!-- Categories Section -->
        <?php if ($options['show_categories']): ?>
        <section class="pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Kategori</h3>
            <?php $view->renderCategories(); ?>
        </section>
        <?php endif; ?>
    </aside>
</div>