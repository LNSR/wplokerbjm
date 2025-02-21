<div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-6 animate-fade-in">
    <div class="mb-4">
        <?php if (has_post_thumbnail()) : ?>
            <div class="w-16 h-16 mb-4">
                <?php the_post_thumbnail('thumbnail', ['class' => 'w-full h-full object-cover rounded-lg']); ?>
            </div>
        <?php endif; ?>
        <h3 class="text-xl font-semibold text-gray-900 hover:text-blue-600 transition-colors">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
    </div>

    <div class="space-y-2 mb-4">
        <?php
        $job_data = get_job_meta_data();
        ?>
        <p class="text-gray-600 font-bold"><?php echo esc_html($job_data['company']); ?></p>
        <p class="flex items-center text-gray-500">
            <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
            <?php echo esc_html($job_data['location']); ?>
        </p>
        <?php if ($job_data['education']) : ?>
            <p class="flex items-center text-gray-500">
                <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                <?php echo is_array($job_data['education']) ? 
                    esc_html(implode(', ', $job_data['education'])) : 
                    esc_html($job_data['education']); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
        <span class="text-sm text-gray-500"><?php echo get_the_date(); ?></span>
        <a href="<?php the_permalink(); ?>" 
           class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
            Lihat Detail
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>