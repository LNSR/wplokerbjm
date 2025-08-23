<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\ArchiveViewModel;
use AstraChild\Components\Placeholder;

class ArchiveView
{

    public function __construct(private ArchiveViewModel $archiveViewModel)
    {
    }

    public function render(): void
    {
        $schemaCards = $this->archiveViewModel->viewSearchResultsSchema();
        foreach ($schemaCards as $item):
            echo $item;
        endforeach;
        $hydration = $this->archiveViewModel->viewSearchResultsProps();
        ?>
        <div id="archive">
            <script type="application/json" data-props>
                <?= wp_json_encode($hydration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
            </script>
            <?= Placeholder::render(); ?>
        </div>
        <?php

    }
}
