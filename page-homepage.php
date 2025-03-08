<?php
/**
 * Template Name: Homepage Lowongan
 */

use AstraChild\Controllers\HomePageController;
use AstraChild\Views\Components\Pagination;

// Initialize the controller
$homeController = new HomePageController();

get_header(); ?>

<div class="max-w-8xl mx-auto px-4 py-8">
    <!-- Flex container for main content and sidebar -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main content column -->
        <div class="w-full">
        <!-- <div class="w-full lg:w-3/4"> Replace above if using Sidebar -->
            <!-- Hero Section with Search -->
            <?php get_template_part('template-parts/search/search-section'); ?>

            <!-- Status Carousel Section -->
            <?php get_template_part('template-parts/homepage/status-carousel'); ?>

            <!-- Featured Jobs Section -->
            <section class="featured-jobs-section mb-12">
                <div class="max-w-7xl mx-auto lg:mx-50">
                    <h2 class="text-3xl font-bold text-gray-900 mb-8">Lowongan Terbaru</h2>
                    <div id="featured-jobs-grid" class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-6">
                        <?php
                        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
                        $featured_jobs = $homeController->getFeaturedJobs($paged);
                        $query = $featured_jobs['query'];

                        if ($query->have_posts()) :
                            while ($query->have_posts()) : $query->the_post();
                                get_template_part('template-parts/homepage/content-job-card');
                            endwhile;
                            wp_reset_postdata();
                        else :
                            echo '<p class="text-gray-500 text-center">Tidak ada lowongan tersedia.</p>';
                        endif;
                        ?>
                    </div>

                    <?php 
                    // Use the Pagination component
                    $pagination = new Pagination();
                    $pagination->render(
                        $featured_jobs, // Contains 'max_pages' and 'current_page' keys
                        'featured-jobs', // Target element ID (without '-pagination')
                        'loadFeaturedJobs' // JavaScript callback function name
                    );
                    ?>

                    <!-- Loading indicator -->
                    <div id="featured-jobs-loading" class="text-center py-8 hidden">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Sidebar
        <div class="hidden lg:block w-full lg:w-1/4">
            <?php // get_template_part('template-parts/jobs/sidebar'); ?>
        </div> -->
    </div>
</div>

<?php get_footer(); ?>