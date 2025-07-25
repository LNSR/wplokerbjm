<?php

namespace AstraChild\Components;
use AstraChild\Components\JobCard;
use AstraChild\Services\Job\JobServices;
use AstraChild\Services\REST\RESTData;

class JobGrid
{

    public function __construct(
        private JobCard $jobCard,
        private JobServices $jobServices,
        private RESTData $restData,
    ) {
    }

    public function render(array $query_args, string $title, string $context = 'latest', int $total_jobs = 0): string
    {
        $jobs_query = new \WP_Query($query_args);
        ob_start();
        $jobs = [];
        $cards = [];


        if ($jobs_query->have_posts()) {
            while ($jobs_query->have_posts()) {
                $jobs_query->the_post();
                $post_id = get_the_ID();
                $jobs[] = $this->restData->getCardData($post_id);
                $cards[] = [
                    'card' => $this->jobCard->render($post_id, 'featured'),
                    'schema' => $this->jobServices->renderJobPostingJsonLd($post_id),
                ];
            }
            wp_reset_postdata();
        }

        if (!$title) {
            $title = match ($context) {
                'search' => 'Hasil Pencarian',
                'archive' => 'Semua Lowongan',
            };
        }

        $vueProps = self::getVueProps($jobs, $jobs_query, $context, $title, $total_jobs);

        ?>
        <section class="mt-8">
                <?php
                foreach ($cards as $item) {
                    echo $item['schema'];
                }
                ?>
            <div id="job-grid" data-props='<?= esc_attr(json_encode($vueProps)) ?>'>
                <h2 class="text-xl font-semibold !mb-6"><?= esc_html($title) ?></h2>
                <?php if ($total_jobs > 0): ?>
                    <div class="text-base font-medium mb-4"><?= esc_html($total_jobs) ?> lowongan ditemukan</div>
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Critical Content for SEO -->
                    <?php
                    foreach ($cards as $item) {
                        echo $item['card'];
                    }
                    ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Build Vue props array for hydration.
     */
    protected static function getVueProps(array $jobs, \WP_Query $jobs_query, string $context, string $title, int $total_jobs): array
    {
        return [
            'jobs' => $jobs,
            'maxNumPages' => (int) $jobs_query->max_num_pages,
            'context' => $context,
            'filters' => [
                'cari' => $_GET['cari'] ?? '',
                'lokasi' => $_GET['lokasi'] ?? '',
                'gender' => $_GET['gender'] ?? '',
                'pendidikan' => $_GET['pendidikan'] ?? '',
                'sort' => $_GET['sort'] ?? 'desc',
            ],
            'title' => $title,
            'totalJobs' => $total_jobs
        ];
    }
}
