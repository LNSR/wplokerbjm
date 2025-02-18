<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\page\HomepageViewModel;

class HomepageView
{
    public function __construct(
        private HomepageViewModel $homepageViewModel
    ) {}

    public function render(): void
    {

?>
        <main class="container mx-auto px-4 py-8">
            <?= $this->homepageViewModel->viewHero(); ?>
            <?= $this->homepageViewModel->viewCarousel(); ?>
            <?= $this->homepageViewModel->viewFeaturedJobs(); ?>

            <?= $this->homepageViewModel->viewFloatingActionButton(); ?>
            <?= $this->homepageViewModel->viewFloatingAstraColorSwitchButton(); ?>
        </main>
<?php
    }
}
