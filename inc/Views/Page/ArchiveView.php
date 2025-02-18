<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\ArchiveViewModel;

class ArchiveView
{

	public function __construct(private ArchiveViewModel $archiveViewModel) {}

	public function render(): void
	{
?>
		<main class="container mx-auto">
			<?= $this->archiveViewModel->viewHero(); ?>
			<?= $this->archiveViewModel->viewSearchResults(); ?>

			<?= $this->archiveViewModel->viewFloatingActionButton(); ?>
			<?= $this->archiveViewModel->viewFloatingAstraColorSwitchButton(); ?>

		</main>
<?php

	}
}
