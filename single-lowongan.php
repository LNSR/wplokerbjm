<?php
get_header();

while (have_posts()) :
    the_post();
    $job_data = get_job_meta_data();
?>
<!-- Modify the main container to accommodate sidebar -->
<div class="max-w-7xl mx-auto px-4 py-12">
    <!-- Add flex container for main content and sidebar -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main content - modify width classes -->
        <div class="w-full lg:w-3/4">
            <article class="bg-white rounded-lg shadow-lg p-8 divide-y divide-gray-200">
                <section class="sticky top-0 z-10 bg-white/95 backdrop-blur mb-4 -mx-8 px-8 py-4">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4 text-center"><?php the_title(); ?></h1>
                </section>

                <!-- Company Section -->
                <section class="pt-8 group">
                    <div class="flex items-start gap-3 mb-6 transform transition-transform group-hover:translate-x-2">
                        <div class="shrink-0 mt-2">
                            <i class="fas fa-building text-blue-600 text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-2xl font-semibold text-gray-800">
                                <span class=""><?php echo esc_html($job_data['company']); ?></span>
                            </h2>
                        </div>
                    </div>

                    <?php if (!is_really_empty($job_data['company_desc'])) : ?>
                        <div class="mt-6">
                            <h3 class="flex items-center gap-1 text-xl font-semibold text-gray-800 mb-4">
                                <i class="fas fa-info-circle text-blue-600"></i>
                                <span class="pl-4">Tentang Perusahaan</span>
                            </h3>
                            <div class="prose max-w-none text-gray-600 mt-4 [&>p]:text-justify [&>ul]:text-left [&>ol]:text-left sm:[&>p]:indent-11 [&>p]:indent-10">
                                <?php echo wpautop($job_data['company_desc']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Job Summary Section -->
                <section class="pt-8">
                    <h3 class="flex items-center gap-2 text-xl font-semibold text-gray-800 mb-6">
                        <i class="fas fa-clipboard-check text-blue-600"></i>
                        <span class="pl-4">Ringkasan Pekerjaan</span>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 animate-fade-in mt-4 mb-6">
                        <?php if (!is_really_empty($job_data['job_type']) && $job_data['job_type'] !== 'Hidden / Tidak Diperlukan') : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-briefcase text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Jenis Pekerjaan:</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo esc_html($job_data['job_type']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['education'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-graduation-cap text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Pendidikan:</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo is_array($job_data['education']) ? esc_html(implode(', ', $job_data['education'])) : esc_html($job_data['education']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['experience'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-history text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Pengalaman:</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo esc_html($job_data['experience'] . ' tahun'); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['gender'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-user text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Gender:</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo esc_html($job_data['gender']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['min_age']) || !is_really_empty($job_data['max_age'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-user-clock text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Umur:</span>
                                    <span class="text-sm font-semibold text-gray-900">
                                        <?php echo esc_html(format_age_range($job_data['min_age'], $job_data['max_age'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['min_salary']) || !is_really_empty($job_data['max_salary'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-money-bill-wave text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Gaji:</span>
                                    <span class="text-sm font-semibold text-gray-900">
                                        <?php echo esc_html(format_salary_range($job_data['min_salary'], $job_data['max_salary'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['location'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-map-marker-alt text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Lokasi:</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo esc_html($job_data['location']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!is_really_empty($job_data['deadline'])) : ?>
                            <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                <div class="shrink-0">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                        <i class="fas fa-clock text-blue-600 group-hover:text-white text-xl"></i>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 flex items-baseline gap-1 text-wrap">
                                    <span class="text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Deadline:</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo date_i18n('d F Y', strtotime($job_data['deadline'])); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Job Description Section -->
                <?php if (!is_really_empty($job_data['job_desc'])) : ?>
                    <section class="pt-8 content-visibility-auto">
                        <h3 class="flex items-center gap-1 text-xl font-semibold text-gray-800 mb-6">
                            <i class="fas fa-tasks text-blue-600"></i>
                            <span class="pl-4">Deskripsi Pekerjaan</span>
                        </h3>
                        <div class="prose max-w-none text-gray-600 mt-4 [&>p]:text-justify [&>ul]:text-left [&>ol]:text-left sm:[&>p]:indent-11 [&>p]:indent-10">
                            <?php echo wpautop($job_data['job_desc']); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Requirements Section -->
                <?php if (!is_really_empty($job_data['requirements'])) : ?>
                    <section class="pt-8 content-visibility-auto">
                        <h3 class="flex items-center gap-1 text-xl font-semibold text-gray-800 mb-6">
                            <i class="fas fa-clipboard-list text-blue-600"></i>
                            <span class="pl-5">Persyaratan</span>
                        </h3>
                        <div class="prose max-w-none text-gray-600 mt-4 [&>p]:text-justify [&>ul]:text-left [&>ol]:text-left sm:[&>p]:indent-11 [&>p]:indent-10">
                            <?php echo wpautop($job_data['requirements']); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Contact Section -->
                <?php if (has_contact_info($job_data)) : ?>
                    <section class="pt-8">
                        <h3 class="flex items-center justify-between gap-2 text-xl font-semibold text-gray-800 mb-6">
                            <span class="flex items-center gap-2">
                                <i class="fas fa-address-card text-blue-600"></i>
                                <span class="pl-2">Kontak</span>
                            </span>
                            <!-- share button -->
                            <button 
                                class="share-button inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                                data-post-id="<?php echo get_the_ID(); ?>">
                                <i class="fas fa-share-alt mr-2"></i>
                                Bagikan
                            </button>
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4 mb-6">
                            <?php if (!is_really_empty($job_data['email'])) : ?>
                                <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                    <div class="shrink-0">
                                        <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                            <i class="fas fa-envelope text-blue-600 group-hover:text-white text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Email:</span>
                                        <a href="mailto:<?php echo esc_attr($job_data['email']); ?>" class="block text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors truncate">
                                            <?php echo esc_html($job_data['email']); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!is_really_empty($job_data['phone'])) : ?>
                                <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                    <div class="shrink-0">
                                        <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                            <i class="fas fa-phone text-blue-600 group-hover:text-white text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Telepon:</span>
                                        <a href="tel:<?php echo esc_attr($job_data['phone']); ?>" class="block text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors truncate">
                                            <?php echo esc_html($job_data['phone']); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!is_really_empty($job_data['website'])) : ?>
                                <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                    <div class="shrink-0">
                                        <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                            <i class="fas fa-globe text-blue-600 group-hover:text-white text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300">Website:</span>
                                        <a href="<?php echo esc_url($job_data['website']); ?>" target="_blank" class="block text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors truncate">
                                            <?php echo esc_html($job_data['website']); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Social Media Section -->
                <?php if (!is_really_empty($job_data['socials']) && is_array($job_data['socials'])) : ?>
                    <section class="pt-8">
                        <h3 class="flex items-center gap-2 text-xl font-semibold text-gray-800 group">
                            <i class="fas fa-share-alt text-blue-600"></i>
                            <span class="pl-4">Sosial Media</span>
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4 mb-6">
                            <?php
                            foreach ($job_data['socials'] as $platform => $username) :
                                $link_data = Social_Media::get_link_data($platform, $username);
                                if ($link_data) :
                            ?>
                                    <div class="group flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-200 hover:shadow-md transition-all duration-300">
                                        <div class="shrink-0">
                                            <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 group-hover:bg-blue-600 transition-colors duration-300">
                                                <i class="<?php echo esc_attr($link_data['icon']); ?> text-blue-600 group-hover:text-white text-xl"></i>
                                            </div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-gray-500 group-hover:text-blue-600 transition-colors duration-300"><?php echo esc_html(ucfirst($platform)); ?>:</span>
                                            <a href="<?php echo esc_url($link_data['url']); ?>" target="_blank" class="block text-sm font-semibold text-gray-900 hover:text-blue-600 transition-colors truncate">
                                                <?php echo esc_html($link_data['username']); ?>
                                            </a>
                                        </div>
                                    </div>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </section>
                <?php endif; ?>
            </article>
        </div>

        <!-- Add sidebar -->
        <div class="hidden lg:block w-full lg:w-1/4">
            <?php get_template_part('template-parts/sidebar'); ?>
        </div>
    </div>
</div>
<?php
endwhile;
get_footer();
?>