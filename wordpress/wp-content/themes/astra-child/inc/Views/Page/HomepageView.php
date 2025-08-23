<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\HomepageViewModel;
use AstraChild\Components\Placeholder;

class HomepageView
{
    public function __construct(
        private HomepageViewModel $homepageViewModel
    ) {
    }

    public function render(): void
    {
        $schemaCards = $this->homepageViewModel->getSchema();
        foreach ($schemaCards as $item):
            echo $item;
        endforeach;
        ?>
        <div id="homepage">
            <script type="application/json" data-props>
                <?= wp_json_encode($this->homepageViewModel->getProps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
            </script>
            <?= Placeholder::render(); ?>
        </div>
        <?php
    }
}
