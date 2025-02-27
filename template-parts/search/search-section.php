<?php
/**
 * Template part for displaying the search section
 * 
 * @package Astra-Child
 */

$filter_data = get_job_filters_data();
?>

<section class="bg-gradient-to-r rounded-2xl p-8 mb-12">
    <div class="max-w-4xl mx-auto text-center">
        <h1 class="text-4xl font-bold text-white mb-4">Temukan Lowongan Kerja Terbaik</h1>
        <p class="text-blue-100 mx-auto mb-8">Temukan ribuan lowongan kerja di Banjarmasin dan sekitarnya</p>

        <!-- Search Form -->
        <div class="bg-white p-6 rounded-xl shadow-lg md:mx-auto lg:mx-30">
            <form action="<?php echo esc_url(home_url('/search-jobs/')); ?>" method="GET" class="space-y-6">
                <!-- Search Input -->
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" 
                           name="keywords" 
                           placeholder="Cari berdasarkan posisi, perusahaan, atau kata kunci"
                           value="<?php echo isset($_GET['keywords']) ? esc_attr($_GET['keywords']) : ''; ?>"
                           class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 indent-8">
                </div>

                <!-- Filters -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Location Filter -->
                    <?php if (!empty($filter_data['locations'])) : ?>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-map-marker-alt text-gray-400"></i>
                        </div>
                        <select name="loc" class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                            <option value="">Lokasi</option>
                            <?php foreach ($filter_data['locations'] as $location) : ?>
                            <option value="<?php echo esc_attr($location->slug); ?>"<?php echo isset($_GET['loc']) && $_GET['loc'] === $location->slug ? ' selected' : ''; ?>>
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
                    <?php if (!empty($filter_data['experiences'])) : ?>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-briefcase text-gray-400"></i>
                        </div>
                        <select name="pengalaman" class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                            <option value="">Pengalaman</option>
                            <?php foreach ($filter_data['experiences'] as $exp) : ?>
                            <option value="<?php echo esc_attr($exp->slug); ?>"<?php echo isset($_GET['pengalaman']) && $_GET['pengalaman'] === $exp->slug ? ' selected' : ''; ?>>
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
                    <?php if (!empty($filter_data['education'])) : ?>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-graduation-cap text-gray-400"></i>
                        </div>
                        <select name="pendidikan" class="w-full pl-12 pr-4 py-4 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 appearance-none bg-white text-center">
                            <option value="">Lulusan</option>
                            <?php foreach ($filter_data['education'] as $edu) : ?>
                            <option value="<?php echo esc_attr($edu->slug); ?>"<?php echo isset($_GET['pendidikan']) && $_GET['pendidikan'] === $edu->slug ? ' selected' : ''; ?>>
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

                <!-- Buttons Container -->
                <div class="flex flex-row justify-center items-center gap-4">
                    <!-- Search Button -->
                    <button type="submit" class="w-auto px-4 md:px-8 py-3 md:py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors duration-200 flex items-center justify-center gap-2 text-sm md:text-base">
                        <i class="fas fa-search"></i>
                        <span>Cari</span>
                    </button>

                    <?php
                    // Only show Reset button on search results page
                    $is_search_results_page = strpos($_SERVER['REQUEST_URI'], 'search-jobs') !== false;
                    if ($is_search_results_page): 
                    ?>
                    <!-- Reset Button -->
                    <button type="button" onclick="window.location.href='<?php echo esc_url(home_url('/')); ?>'" class="w-auto px-4 md:px-8 py-3 md:py-4 text-blue-600 hover:text-blue-700 font-semibold rounded-lg border-2 border-blue-600 hover:border-blue-700 transition-colors duration-200 flex items-center justify-center gap-2 text-sm md:text-base">
                        <i class="fas fa-undo"></i>
                        <span>Reset</span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>