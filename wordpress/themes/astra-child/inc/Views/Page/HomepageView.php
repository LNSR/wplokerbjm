<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\HomepageViewModel;

class HomepageView
{
    public function __construct(
        private HomepageViewModel $homepageViewModel
    ) {}

    public function render(): void
    {

?>
        <main class="container mx-auto max-w-[95vmax] lg:max-w-[90vmax] px-4 py-8">
            <?= $this->homepageViewModel->viewHero(); ?>
            <?= $this->homepageViewModel->viewCarousel(); ?>
            <?= $this->homepageViewModel->viewFeaturedJobs(); ?>

            <?= $this->homepageViewModel->viewFloatingActionButton(); ?>
        </main>
<?php
    }
}
