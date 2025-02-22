<div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-6 animate-fade-in">
    <!-- Title and date section -->
    <div class="flex justify-between items-start gap-4 mb-4">
        <h3 class="text-xl font-semibold text-gray-900 hover:text-blue-600 transition-colors">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>
        <span class="text-sm text-gray-500 whitespace-nowrap">
            <?php
            $post_time = get_the_time('U');
            $current_time = current_time('timestamp');
            $time_diff = translate_time_diff(human_time_diff($post_time, $current_time));

            // Show relative time with absolute time as tooltip
            printf(
                '<span title="%s">%s yang lalu</span>',
                esc_attr(get_the_date('d M Y, H:i')),
                esc_html($time_diff)
            );
            ?>
        </span>
    </div>

    <!-- Job details -->
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

    <div class="flex items-center justify-end pt-4 border-t border-gray-100">
        <a href="<?php the_permalink(); ?>"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
            Lihat Detail
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>
