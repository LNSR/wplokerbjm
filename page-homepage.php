<?php
/**
 * Template Name: Homepage Lowongan
 */

use AstraChild\Views\Homepage\FeaturedJobs;

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

            <!-- Featured Jobs Section - Now using View -->
            <?php 
            $featured_jobs_view = new FeaturedJobs();
            $featured_jobs_view->render([
                'title' => 'Lowongan Terbaru',
                'columns' => [
                    'mobile' => 1,
                    'tablet' => 1,
                    'desktop' => 1
                ]
            ]); 
            ?>
        </div>
        <!-- Sidebar
        <div class="hidden lg:block w-full lg:w-1/4">
        <?php // get_template_part('template-parts/jobs/sidebar'); ?>
        </div> -->
    </div>
</div>

<?php get_footer(); ?>