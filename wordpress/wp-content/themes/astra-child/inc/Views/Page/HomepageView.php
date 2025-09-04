<?php

namespace AstraChild\Views\Page;

use AstraChild\Presenters\Page\HomepagePresenter;
use AstraChild\Presenters\Components\Placeholder;

class HomepageView
{
    public function __construct(
        private HomepagePresenter $homepagePresenter
    ) {
    }

    public function render(): void
    {
        $schemaCards = $this->homepagePresenter->getSchema();
        foreach ($schemaCards as $item):
            echo $item;
        endforeach;
        ?>
        <div id="homepage">
            <script type="application/json" data-props>
                <?= wp_json_encode($this->homepagePresenter->getProps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
            </script>
            <?= Placeholder::render(); ?>
        </div>
        <?php
    }
}
