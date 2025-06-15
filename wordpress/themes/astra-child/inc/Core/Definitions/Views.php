<?php

namespace AstraChild\Core\Definitions;

class Views {
    public static function getDefinitions(): array {
        return [
            \AstraChild\Views\Page\SingleView::class => \DI\create()
                ->constructor(
                    \DI\get(\AstraChild\ViewModels\Page\SingleViewModel::class)
                ),
            \AstraChild\Views\Page\HomepageView::class => \DI\create()
                ->constructor(
                    \DI\get(\AstraChild\ViewModels\Page\HomepageViewModel::class)
                ),
            \AstraChild\Views\Page\ArchiveView::class => \DI\create()
                ->constructor(
                    \DI\get(\AstraChild\ViewModels\Page\ArchiveViewModel::class)
                ),
            \AstraChild\Views\Page\PasangIklanView::class => \DI\create()
                ->constructor(
                    \DI\get(\AstraChild\ViewModels\Page\PasangIklanViewModel::class)
                ),
        ];
    }
}