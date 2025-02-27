<div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 p-6 animate-fade-in relative">
    <!-- Job Status Badge -->
    <?php
    $job_data = get_job_meta_data();
    $status = $job_data['status'] ?? '0';
    $status_attrs = get_job_status_attributes($status);

    // Only show the status badge if it's urgent or pinned+urgent and has a class defined
    if ($status == '2' && !empty($status_attrs['class'])):
    ?>
        <div class="absolute top-3 left-3">
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium <?php echo esc_attr($status_attrs['class']); ?>">
                <i class="<?php echo esc_attr($status_attrs['icon']); ?> mr-1"></i>
                <?php echo esc_html($status_attrs['label']); ?>
            </span>
        </div>
    <?php endif; ?>
    
    <!-- Deadline in top right corner -->
    <?php if ($status == '2' && !empty($job_data['deadline'])): 
        // Get the deadline timestamp
        $deadline_timestamp = strtotime($job_data['deadline']);
        $current_timestamp = current_time('timestamp');
        $time_diff = $deadline_timestamp - $current_timestamp;
    ?>
        <div class="absolute top-3 right-3">
            <div class="flex items-center bg-white bg-opacity-90 rounded-lg px-2 py-1 text-xs border <?php echo $time_diff > 0 ? 'border-green-200' : 'border-red-200'; ?> shadow-sm">
                <i class="fas fa-clock <?php echo $time_diff > 0 ? 'text-green-600' : 'text-red-600'; ?> mr-1"></i>
                <span class="font-medium">
                    <?php if ($time_diff > 0): ?>
                        <!-- Future deadline - show remaining time -->
                        <?php 
                            $human_diff = translate_time_diff(human_time_diff($current_timestamp, $deadline_timestamp)); 
                            echo "Deadline: $human_diff lagi";
                        ?>
                    <?php else: ?>
                        <!-- Past deadline - show expired notice -->
                        Berakhir: <?php echo date_i18n('d M Y', $deadline_timestamp); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Add an invisible spacer when badge is present -->
    <?php if ($status == '2' && !empty($status_attrs['class'])): ?>
        <div class="h-6"></div>
    <?php endif; ?>

    <!-- Title and date section -->
    <div class="flex justify-between items-start gap-4 mb-4">
        <div class="flex items-center gap-3">
            <h3 class="text-xl font-semibold text-gray-900 hover:text-blue-600 transition-colors">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
        </div>
        
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

    <!-- Job details - Modified for side-by-side layout -->
    <div class="mb-0">
        <!-- Company name stays full width -->
        <p class="text-gray-600 font-bold mb-2">
            <i class="fas fa-building mr-2 text-blue-600"></i>
            <?php echo esc_html($job_data['company']); ?>
        </p>
        <!-- Flex container for location, education and experience -->
        <div class="flex flex-wrap gap-x-4">
            <!-- Location -->

            <p class="flex items-center text-gray-500">
                <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                <?php echo esc_html($job_data['location']); ?>
            </p>

            <!-- Education when available -->
            <?php if ($job_data['education']) : ?>
                <p class="flex items-center text-gray-500">
                    <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                    <?php echo is_array($job_data['education']) ?
                        esc_html(implode(', ', $job_data['education'])) :
                        esc_html($job_data['education']); ?>
                </p>
            <?php endif; ?>

            <!-- Experience when available -->
            <?php if (!is_really_empty($job_data['experience'])) : ?>
                <p class="flex items-center text-gray-500">
                    <i class="fas fa-history mr-2 text-blue-600"></i>
                    <?php echo esc_html($job_data['experience'] . ' tahun'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex items-center justify-end border-t border-gray-100">
        <a href="<?php the_permalink(); ?>"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
            Lihat Detail
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>