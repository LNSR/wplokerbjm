<?php

namespace AstraChild\Resources\Components;

class JobGrid
{
    public static function render(array $query_args, string $title = 'Lowongan Terbaru', string $context = 'latest', int $total_jobs = 0): string
    {
        $jobs_query = new \WP_Query($query_args);
        ob_start();
?>
        <section class="mt-8" id="jobs-list">
            <h2 class="text-xl font-semibold !mb-6"><?= esc_html($title) ?></h2>
            <?php if ($total_jobs > 0): ?>
                <div class="text-base font-medium mb-4"><?= esc_html($total_jobs) ?> lowongan ditemukan</div>
            <?php endif; ?>
            <div
                x-data='loadMoreJobs(
                    "<?= esc_attr($context) ?>",
                    <?= (int)($jobs_query->max_num_pages) ?>,
                    <?= json_encode([
                        'cari' => $_GET['cari'] ?? '',
                        'lokasi' => $_GET['lokasi'] ?? '',
                        'gender' => $_GET['gender'] ?? '',
                        'pendidikan' => $_GET['pendidikan'] ?? '',
                        'sort' => $_GET['sort'] ?? 'desc',
                    ]) ?>
                )'>
                <article id="job-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php
                    if ($jobs_query->have_posts()) :
                        while ($jobs_query->have_posts()) :
                            $jobs_query->the_post();
                            echo \AstraChild\Resources\Components\JobCard::render(get_the_ID(), 'featured');
                        endwhile;
                        wp_reset_postdata();
                    endif;
                    ?>
                </article>
                <?php if ($jobs_query->have_posts() && $jobs_query->max_num_pages > 1) : ?>
                    <div class="text-center mt-8">
                        <button class="btn btn-outline btn-wide" x-show="hasMore" x-bind:disabled="loading" x-on:click="loadMore">
                            <span x-show="!loading">Muat Lowongan</span>
                            <span x-show="loading">
                                <span class="loading loading-spinner loading-sm"></span>
                                Memuat Lowongan
                            </span>
                        </button>
                    </div>
                <?php elseif (!$jobs_query->have_posts()) : ?>
                    <div class="text-center py-12">
                        <h2 class="text-2xl font-semibold !mb-6">Tidak ada lowongan ditemukan.</h2>
                        <p>Coba gunakan kata kunci atau filter lain.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
<?php
        return ob_get_clean();
    }
}
