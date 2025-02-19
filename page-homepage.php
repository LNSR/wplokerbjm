<?php

/**
 * Template Name: Homepage Lowongan
 * 
 * This is the custom homepage template for the job board
 */

get_header(); ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Hero Section with Search -->
    <section class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-8 mb-12">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl font-bold text-white mb-4">Temukan Lowongan Kerja Terbaik</h1>
            <p class="text-blue-100 mb-8">Temukan ribuan lowongan kerja di Banjarmasin dan sekitarnya</p>

            <!-- Search Form -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <form id="job-search-form" class="space-y-6">
                    <input type="hidden" name="action" value="search_jobs">
                    <?php wp_nonce_field('job_search_nonce', 'job_search_security'); ?>

                    <!-- Search Input -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="s" placeholder="Cari berdasarkan posisi, perusahaan, atau kata kunci"
                            value="<?php echo get_search_query(); ?>"
                            class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 indent-8">
                    </div>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Location Filter -->
                        <?php
                        $locations = get_terms(['taxonomy' => 'lokasi-pekerjaan', 'hide_empty' => false]);
                        if ($locations && !is_wp_error($locations)) : ?>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-map-marker-alt text-gray-400"></i>
                            </div>
                            <select name="lokasi-pekerjaan"
                                class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                                <option value="">Semua Lokasi</option>
                                <?php foreach ($locations as $location) : ?>
                                <option value="<?php echo esc_attr($location->slug); ?>">
                                    <?php echo esc_html($location->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Experience Filter -->
                        <?php
                        $experiences = get_terms(['taxonomy' => 'pengalaman', 'hide_empty' => false]);
                        if ($experiences && !is_wp_error($experiences)) : ?>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-briefcase text-gray-400"></i>
                            </div>
                            <select name="pengalaman"
                                class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                                <option value="">Pengalaman Kerja</option>
                                <?php foreach ($experiences as $exp) : ?>
                                <option value="<?php echo esc_attr($exp->slug); ?>">
                                    <?php echo esc_html($exp->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Education Filter -->
                        <?php
                        $education = get_terms(['taxonomy' => 'pendidikan', 'hide_empty' => false]);
                        if ($education && !is_wp_error($education)) : ?>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none ">
                                <i class="fas fa-graduation-cap text-gray-400"></i>
                            </div>
                            <select name="pendidikan"
                                class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                                <option value="">Lulusan</option>
                                <?php foreach ($education as $edu) : ?>
                                <option value="<?php echo esc_attr($edu->slug); ?>">
                                    <?php echo esc_html($edu->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Buttons Container - New separate div -->
                    <div class="flex flex-col md:flex-row justify-center items-center gap-4">
                        <!-- Search Button -->
                        <button type="submit" 
                            class="w-auto px-4 md:px-8 py-3 md:py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors duration-200 flex items-center justify-center gap-2 text-sm md:text-base"
                            id="search-submit">
                            <i class="fas fa-search"></i>
                            <span>Cari</span>
                            <div class="hidden animate-spin" id="search-loading">
                                <i class="fas fa-circle-notch"></i>
                            </div>
                        </button>

                        <!-- Reset Button - Will be injected here by JavaScript -->
                        <div id="reset-button-container"></div>
                    </div>
                </form>
            </div>

            <!-- Results Container -->
            <div id="search-results" class="mt-8 animate-fade-in hidden">
                <h2 class="text-2xl font-bold text-white mb-6">Hasil Pencarian</h2>
                <div class="search-results-grid">
                    <!-- Results will be loaded here -->
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Jobs Section -->
    <section class="featured-jobs-section mb-12">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Lowongan Terbaru</h2>
            <div id="featured-jobs-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $paged = get_query_var('paged') ? get_query_var('paged') : 1;
                $args = [
                    'post_type' => 'lowongan',
                    'posts_per_page' => 6,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'paged' => $paged
                ];

                $query = new WP_Query($args);

                if ($query->have_posts()) :
                    while ($query->have_posts()) : $query->the_post();
                        get_template_part('template-parts/content', 'job-card');
                    endwhile;
                    wp_reset_postdata();
                else :
                    echo '<p class="text-gray-500 text-center">Tidak ada lowongan tersedia.</p>';
                endif;
                ?>
            </div>

            <?php if ($query->max_num_pages > 1) : ?>
                <div class="mt-8 flex justify-center gap-2" id="featured-jobs-pagination">
                    <?php 
                    for ($i = 1; $i <= $query->max_num_pages; $i++) :
                        $is_current = $i === $paged;
                    ?>
                        <button type="button"
                                data-page="<?php echo $i; ?>"
                                class="page-number px-4 py-2 rounded-lg <?php echo $is_current ? 
                                    'bg-blue-600 text-white' : 
                                    'bg-white text-blue-600 hover:bg-blue-50'; ?> 
                                    border border-blue-200 transition-colors">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <!-- Loading indicator -->
            <div id="featured-jobs-loading" class="text-center py-8 hidden">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        </div>
    </section>

    <!-- Job Categories Section -->
    <!-- Job Categories Section -->
    <?php /* 
    <section class="mb-12">
        <div class="max-w-7xl mx-auto">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Kategori Pekerjaan</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php
                $categories = get_terms([
                    'taxonomy' => 'kategori-lowongan',
                    'hide_empty' => true,
                ]);

                if (!empty($categories) && !is_wp_error($categories)) :
                    foreach ($categories as $category) : ?>
                <a href="<?php echo get_term_link($category); ?>"
                    class="group block bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                    <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                        <?php echo esc_html($category->name); ?>
                    </h3>
                    <span class="inline-flex items-center mt-2 text-sm text-gray-500">
                        <i class="fas fa-briefcase mr-2 text-blue-600"></i>
                        <?php echo esc_html($category->count); ?> Lowongan
                    </span>
                </a>
                <?php endforeach;
                endif; ?>
            </div>
        </div>
    </section>
    */ ?>
<!-- </div> -->

<?php get_footer(); ?>