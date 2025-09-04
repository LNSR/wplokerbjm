<?php

namespace AstraChild\Views\Page;

use AstraChild\Presenters\Page\ArchivePresenter;
use AstraChild\Presenters\Components\Placeholder;

class ArchiveView
{

    public function __construct(private ArchivePresenter $archivePresenter)
    {
    }

    public function render(): void
    {
        $schemaCards = $this->archivePresenter->viewSearchResultsSchema();
        foreach ($schemaCards as $item):
            echo $item;
        endforeach;
        $hydration = $this->archivePresenter->viewSearchResultsProps();
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
