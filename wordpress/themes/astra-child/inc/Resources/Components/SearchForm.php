<?php

namespace AstraChild\Resources\Components;

class SearchForm
{
    public static function render(array $params)
    {
        $current_search    = $params['current_search'] ?? '';
        $current_lokasi    = $params['current_lokasi'] ?? '';
        $current_gender    = $params['current_gender'] ?? '';
        $current_pendidikan = $params['current_pendidikan'] ?? '';
        $current_sort      = $params['current_sort'] ?? 'desc';

        ob_start();
?>
        <form
            class="space-y-4"
            action="<?= esc_url(get_post_type_archive_link('lowongan')) ?>"
            method="get"
            x-data="dynamicSearch"
            @submit.prevent="searchJobs">
            <input type="hidden" name="post_type" value="lowongan" />

            <div class="flex gap-2 relative" x-data="autoSuggestSearch">
                <input type="text" placeholder="Masukkan Pekerjaan atau Perusahaan" class="input input-bordered w-full rounded-r-none" name="cari"
                    x-model="query" @input.debounce.300ms="getSuggestions" @focus="show = suggestions.length > 0"
                    autocomplete="off" value="<?= esc_attr($current_search) ?>" />
                <button class="btn btn-primary rounded-l-none px-4"
                    :class="{'opacity-75': loading}">
                    <i class="fas" :class="{'fa-search': !loading, 'fa-spinner fa-spin': loading}"></i>
                    <span class="sr-only">Cari</span>
                </button>

                <div
                    x-show="show && suggestions.length"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="auto-suggest-dropdown absolute left-0 sm:left-1/2 sm:-translate-x-1/2 top-full mt-2 min-w-[12rem] w-full sm:w-auto max-w-full sm:max-w-xs md:max-w-md z-20">
                    <div class="bg-blue-100 dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded-xl shadow-lg ring-1 ring-blue-100 dark:ring-blue-900">
                        <ul class="divide-y divide-blue-200 dark:divide-blue-800 max-h-52 overflow-y-auto">
                            <template x-for="(suggestion, idx) in suggestions" :key="suggestion + '-' + idx">
                                <li>
                                    <a
                                        class="block px-4 py-2 text-center text-gray-800 dark:text-white hover:bg-blue-200 dark:hover:bg-blue-900 hover:text-blue-700 dark:hover:text-blue-300 transition-colors cursor-pointer whitespace-nowrap"
                                        @click="selectSuggestion(suggestion)"
                                        x-text="suggestion"></a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="relative">
                    <i class="fas fa-map-marker-alt absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
                    <select name="lokasi" class="select select-bordered w-full !pl-10 h-12 z-0 select2-lazy" 
                        data-taxonomy="lokasi-pekerjaan" 
                        data-placeholder="Semua Lokasi">
                        <option value="">Semua Lokasi</option>
                        <?php if ($current_lokasi): ?>
                            <?php $term = get_term_by('slug', $current_lokasi, 'lokasi-pekerjaan'); ?>
                            <?php if ($term && !is_wp_error($term)): ?>
                                <option value="<?= esc_attr($current_lokasi) ?>" selected>
                                    <?= esc_html($term->name) ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="relative">
                    <i class="fas fa-venus-mars absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
                    <select name="gender" class="select select-bordered w-full !pl-10 h-12 z-0 select2-lazy" 
                        data-taxonomy="gender" 
                        data-placeholder="Semua Gender">
                        <option value="">Semua Gender</option>
                        <?php if ($current_gender): ?>
                            <?php $term = get_term_by('slug', $current_gender, 'gender'); ?>
                            <?php if ($term && !is_wp_error($term)): ?>
                                <option value="<?= esc_attr($current_gender) ?>" selected>
                                    <?= esc_html($term->name) ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="relative">
                    <i class="fas fa-graduation-cap absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
                    <select name="pendidikan" class="select select-bordered w-full !pl-10 h-12 z-0 select2-lazy" 
                        data-taxonomy="pendidikan" 
                        data-placeholder="Semua Pendidikan">
                        <option value="">Semua Pendidikan</option>
                        <?php if ($current_pendidikan): ?>
                            <?php $term = get_term_by('slug', $current_pendidikan, 'pendidikan'); ?>
                            <?php if ($term && !is_wp_error($term)): ?>
                                <option value="<?= esc_attr($current_pendidikan) ?>" selected>
                                    <?= esc_html($term->name) ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="relative">
                    <i class="fas fa-sort-amount-down absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
                    <select name="sort" class="select select-bordered w-full !pl-10 h-12 z-0">
                        <option value="desc" <?= selected($current_sort ?? 'desc', 'desc', false); ?>>Terbaru</option>
                        <option value="asc" <?= selected($current_sort ?? 'desc', 'asc', false); ?>>Terlama</option>
                    </select>
                </div>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }
}
