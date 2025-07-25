<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\ArchiveViewModel;

class ArchiveView
{

	public function __construct(private ArchiveViewModel $archiveViewModel) {}

	public function render(): void
	{
?>
		<main class="container mx-auto max-w-[95vmax] lg:max-w-[90vmax] px-4 py-8">
			<?= $this->archiveViewModel->viewHero(); ?>
			<?= $this->archiveViewModel->viewSearchResults(); ?>

			<?= $this->archiveViewModel->viewFloatingActionButton(); ?>
			<?= $this->archiveViewModel->viewFloatingAstraColorSwitchButton(); ?>

		</main>
<?php

	}
}
