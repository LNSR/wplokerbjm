<div class="w-full lg:auto px-4">
    <aside class="sticky top-24 bg-white rounded-lg shadow-md p-6 divide-y divide-gray-200">
        <!-- Featured Jobs Section -->
        <section class="pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Lorem Ipsum</h3>
            <div class="space-y-4">
                <?php
                $paged = get_query_var('paged') ? get_query_var('paged') : 1;
                $query = get_featured_jobs_data($paged);
                $recent_jobs = $query['query'];


                if ($recent_jobs->have_posts()) :
                    while ($recent_jobs->have_posts()) : $recent_jobs->the_post();
                ?>
                    <article class="group">
                        <a href="<?php the_permalink(); ?>" class="block hover:bg-gray-50 rounded-lg p-3 transition-colors duration-200">
                            <h4 class="text-gray-900 font-medium group-hover:text-blue-600 line-clamp-2">
                                <?php the_title(); ?>
                            </h4>
                            <?php 
                            $job_data = get_job_meta_data();
                            if (!empty($job_data['company'])) :
                            ?>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php echo esc_html($job_data['company']); ?>
                                </p>
                            <?php endif; ?>
                        </a>
                    </article>
                <?php
                    endwhile;
                    wp_reset_postdata();
                else:
                ?>
                    <p class="text-gray-500 text-center">Tidak ada lowongan terbaru.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Kategori</h3>
            <?php
            $categories = get_terms([
                'taxonomy' => 'kategori-lowongan',
                'hide_empty' => true
            ]);

            if (!empty($categories) && !is_wp_error($categories)) :
            ?>
                <div class="space-y-2">
                    <?php foreach ($categories as $category) : ?>
                        <a href="<?php echo get_term_link($category); ?>" 
                           class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                            <span class="text-gray-700 hover:text-blue-600">
                                <?php echo esc_html($category->name); ?>
                            </span>
                            <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                <?php echo $category->count; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center">Tidak ada kategori.</p>
            <?php endif; ?>
        </section>
    </aside>
</div>