<?php

namespace AstraChild\Views\Page;

use AstraChild\ViewModels\Page\SingleViewModel;

class SingleView
{
	public function __construct(
		private SingleViewModel $singleViewModel
	) {}

	public function render(int $post_id): void
	{
		$this->singleViewModel->setJobDataInfo($post_id);

?>
		<main class="container mx-auto space-y-8 mt-12">
			<article>
				<section class="top-0 backdrop-blur text-center">
					<h1 class="text-3xl font-bold"><?= the_title(); ?></h1>
				</section>

				<div class="divider"></div>

				<?= $this->singleViewModel->viewFloatingAstraColorSwitchButton(); ?>
				<?= $this->singleViewModel->viewFloatingActionButton(); ?>

				<?= $this->singleViewModel->viewNamaPerusahaan(); ?>
				<?= $this->singleViewModel->viewTentangPerusahaan(); ?>
				<?= $this->singleViewModel->viewRingkasanPekerja(); ?>
				<?= $this->singleViewModel->viewDeskripsiPekerjaan(); ?>
				<?= $this->singleViewModel->viewPersyaratan(); ?>
				<?= $this->singleViewModel->viewBenefit(); ?>
				<?= $this->singleViewModel->viewContact(); ?>
				<?= $this->singleViewModel->viewSosmed(); ?>
			</article>
		</main>
<?php
	}
}
