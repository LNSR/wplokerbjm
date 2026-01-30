<?php

namespace WPLokerBJM\Views\Page;
use WPLokerBJM\Presenters\DocumentHTML;
use WPLokerBJM\Presenters\Pages\PasangIklanLokerPresenter;

class PasangIklanLokerView
{
    public function render(): void
    {
        $data = PasangIklanLokerPresenter::getData();
        DocumentHTML::renderDocument(null, null, $data['seoHtml']);
    }
}
