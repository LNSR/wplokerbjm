<?php

namespace AstraChild\Views\Page;

use AstraChild\Presenters\Components\Placeholder;


class PasangIklanView
{

	public function __construct(
	) {
	}
	public function render(): void
	{
		$logo = [ 'logo' => get_custom_logo() ] ;

		?>
		<div id="pasang-iklan-loker">
			<script type="application/json" data-props>
				<?= wp_json_encode($logo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
			</script>
			<?= Placeholder::render(); ?>
		</div>
		<?php
	}
}
