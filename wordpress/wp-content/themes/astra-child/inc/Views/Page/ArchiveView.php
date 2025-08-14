<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\ArchiveViewModel;

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
        <div id="archive"
            data-props='<?= esc_attr(wp_json_encode($hydration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
            <div class="component-placeholder p-4">
                <div role="status" aria-live="polite" class="flex items-center justify-center p-6">
                    <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span class="sr-only">Loading content…</span>
                </div>
            </div>
        </div>
        <?php

    }
}
