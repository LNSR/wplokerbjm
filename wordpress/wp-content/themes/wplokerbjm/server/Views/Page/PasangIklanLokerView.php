<?php

namespace WPLokerBJM\Views\Page;
use WPLokerBJM\Presenters\DocumentHTML;
class PasangIklanLokerView
{
    public function render(): void
    {
        DocumentHTML::renderHead(); ?>
        <div id="app">
        </div>
        <?php
        DocumentHTML::renderFooter();
    }
}
