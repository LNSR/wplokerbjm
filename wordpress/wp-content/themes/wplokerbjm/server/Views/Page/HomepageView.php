<?php

namespace WPLokerBJM\Views\Page;

use WPLokerBJM\Presenters\Pages\HomepagePresenter;
use WPLokerBJM\Presenters\DocumentHTML;

class HomepageView
{
    public function __construct(
        private HomepagePresenter $homepagePresenter
    ) {
    }

    public function render(): void
    {
        $data = $this->homepagePresenter->getHomepageData();
        DocumentHTML::renderDocument($data['schema'], $data['props'], $data['seoHtml']);
    }
}
