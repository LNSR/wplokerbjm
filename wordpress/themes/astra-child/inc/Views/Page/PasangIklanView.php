<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\PasangIklanViewModel;

class PasangIklanView
{

	public function __construct(private PasangIklanViewModel $pasangIklanViewModel) {}
	public function render(): void
	{
?>
		<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
			<header>
				<h1 class="text-3xl md:text-4xl font-bold !mb-8 text-center">Pasang Iklan Lowongan Kerja</h1>
			</header>
			<?= $this->pasangIklanViewModel->viewBenefit(); ?>
			<?= $this->pasangIklanViewModel->viewCaraMasang(); ?>
			<?= $this->pasangIklanViewModel->viewSyarat(); ?>

			<?= $this->pasangIklanViewModel->viewFloatingAstraColorSwitchButton(); ?>
		</main>
<?php
	}
}
