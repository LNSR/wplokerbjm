<?php

namespace AstraChild\Views\Page;

use AstraChild\Layouts\Layouts;
use AstraChild\Components\Placeholder;


class PasangIklanView
{

	public function __construct(
		private Layouts $layouts
	) {
	}
	public function render(): void
	{


		?>
		<div id="pasang-iklan-loker">
			<script type="application/json" data-props>
				<?= wp_json_encode($this->layouts->getProps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
			</script>
			<?= Placeholder::render(); ?>
		</div>
		<?php
	}
}
