<?php

namespace WPLokerBJM\Views\Page;

use WPLokerBJM\Presenters\Components\Hero;
use WPLokerBJM\Presenters\Components\JobGrid;
use WPLokerBJM\Presenters\Components\JobCarousel;
use WPLokerBJM\Services\Job\JobServices;
use WPLokerBJM\QueryBuilders\JobQuery;

class HomepageView
{
    public function __construct(
        private Hero $hero,
        private JobGrid $jobGrid,
        private JobCarousel $jobCarousel,
        private JobServices $jobServices
    ) {
    }

    public function getProps(): array
    {
        $props = [
            'carousel' => $this->jobCarousel->getProps(),
            'jobGrid' => $this->jobGrid->getProps(
                JobQuery::latestJobsArgs(1, 12),
                'Lowongan Terbaru',
                'latest'
            )
        ];

        return $props;
    }

    public function getSchema(): array
    {
        return $this->jobGrid->getSchemaCard(
            JobQuery::latestJobsArgs(1, 12)
        );
    }

    public function render(): void
    {
        $schemaCards = $this->getSchema();
        foreach ($schemaCards as $item):
            echo $item;
        endforeach;
        ?>
        <div id="app">
            <script type="application/json" data-props>
                <?= wp_json_encode($this->getProps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
            </script>
        </div>
        <?php
    }
}
