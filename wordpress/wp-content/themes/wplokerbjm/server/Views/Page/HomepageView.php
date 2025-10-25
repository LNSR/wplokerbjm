<?php

namespace WPLokerBJM\Views\Page;

use WPLokerBJM\Presenters\Components\{JobGrid, JobCarousel};
use WPLokerBJM\QueryBuilders\JobQuery;
use WPLokerBJM\Repositories\JobRepository;
use WPLokerBJM\Presenters\DocumentHTML;

class HomepageView
{
    public function __construct(
        private JobGrid $jobGrid,
        private JobCarousel $jobCarousel,
        private JobRepository $jobRepository
    ) {
    }

    /**
     * Output JSON-LD schema for latest jobs on homepage
     * @return string
     */
    private function getSchema(): string
    {
        $query_args = JobQuery::latestJobsArgs(1, 12);
        $result = $this->jobRepository->queryCard($query_args);

        return implode('', $result['schema']);
    }

    public function render(): void
    {
        
        $props = [
            'carousel' => $this->jobCarousel->getProps(),
            'jobGrid' => $this->jobGrid->getProps(
                JobQuery::latestJobsArgs(1, 12),
                'Lowongan Terbaru',
                'latest'
            ),
        ];
        $schema = $this->getSchema();

        DocumentHTML::renderHead($schema);
        ?>
        <div id="app">
            <script type="application/json" data-props>
                <?= wp_json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
            </script>
        </div>
        <?php
        DocumentHTML::renderFooter();
    }
}
