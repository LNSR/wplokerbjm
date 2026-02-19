<?php

namespace WPLokerBJM\Views\Page;
use WPLokerBJM\Presenters\DocumentHTML;
use WPLokerBJM\Presenters\Pages\KebijakanPrivacyPresenter;

class KebijakanPrivacyView
{
    public function render(): void
    {
        $data = KebijakanPrivacyPresenter::getData();
        DocumentHTML::renderDocument(null, null, $data['seoHtml']);
    }
}