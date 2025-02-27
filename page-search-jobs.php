<?php
/**
 * Template Name: Job Search Results
 */

get_header(); ?>

<div class="max-w-8xl mx-auto px-4 py-8">
    <!-- Flex container for main content and sidebar -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main content column -->
        <div class="w-full lg:w-3/4">
            <!-- Include search form at top of results -->
            <?php get_template_part('template-parts/search/search-section'); ?>

            <!-- Search results heading with count -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Hasil Pencarian</h2>
                <div id="search-count" class="text-gray-500 mt-2">
                    <?php 
                    $jobs = get_search_results_jobs();
                    echo '<span>' . $jobs['found_posts'] . '</span> lowongan ditemukan';
                    ?>
                </div>
            </div>
            
            <!-- Results container -->
            <div id="search-results-container" class="animate-fade-in">
                <div id="search-results-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                    <?php
                    if ($jobs['query']->have_posts()) :
                        while ($jobs['query']->have_posts()) : $jobs['query']->the_post();
                            get_template_part('template-parts/homepage/content-job-card');
                        endwhile;
                        wp_reset_postdata();
                    else :
                        echo '<div class="col-span-full text-center p-8 bg-gray-50 rounded-lg">';
                        echo '<p class="text-gray-600">Tidak ada lowongan yang sesuai dengan kriteria pencarian.</p>';
                        echo '</div>';
                    endif;
                    ?>
                </div>
                
                <!-- Loading indicator for infinite scroll -->
                <div id="search-loading" class="text-center py-8 hidden">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="hidden lg:block w-full lg:w-1/4">
            <?php get_template_part('template-parts/sidebar'); ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>