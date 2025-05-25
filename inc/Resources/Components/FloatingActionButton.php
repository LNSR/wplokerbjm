<?php

namespace AstraChild\Resources\Components;

class FloatingActionButton
{
	/**
	 * Render the Floating Action Button with dropdown.
	 *
	 * @param array $contactLinks Array of Contact links with 'icon', 'label', and 'url'.
	 * @return string
	 */
	public static function render(): string
	{
		ob_start();

		$contactLinks = [
			/* 			[
				'icon' => 'fab fa-whatsapp text-green-500',
				'label' => 'WhatsApp',
				'url' => 'https://api.whatsapp.com/send?phone=6283862447271',
			], */
			[
				'icon' => 'fab fa-instagram text-pink-500',
				'label' => 'Instagram',
				'url' => 'https://www.instagram.com/loker_banjarmasin',
			],
		];

?>
		<div
			x-data="{ show: false }"
			x-init="window.addEventListener('scroll', () => { 
				const scrollBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 2;
				show = scrollBottom;
			})"
			class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-4">
			<button
				x-show="show"
				x-transition
				onclick="window.scrollTo({top: 0, behavior: 'smooth'});"
				class="btn btn-circle btn-outline btn-xs shadow-lg transition hover:scale-110 bg-white/0 hover:bg-white/70 dark:bg-slate-800/0 dark:hover:bg-slate-800/70"
				title="Kembali ke Atas"
				aria-label="Kembali ke Atas"
				style="display: none;">
				<i class="fas fa-arrow-up text-base"></i>
			</button>
			<div class="dropdown dropdown-top dropdown-hover">
				<div tabindex="0"
					class="btn btn-primary flex items-center gap-2 rounded-full px-4 py-3 cursor-pointer transform transition hover:scale-105">
					<i class="fas fa-user-headset"></i> Kontak Admin
				</div>


				<ul tabindex="0" class="dropdown-content p-2 rounded-lg w-43 right-0 border-0 bg-transparent shadow-none transition-colors">
					<div class="!backdrop-blur-lg shadow-xl border border-blue-400 rounded-xl p-4 flex flex-col gap-2 w-45">
						<?php foreach ($contactLinks as $link) : ?>
							<a href="<?= esc_url($link['url']); ?>" target="_blank"
								class="btn btn-outline flex items-center justify-start gap-3 rounded-full px-4 py-2 transition transform hover:border-blue-600 hover:scale-105 hover:border-solid">
								<i class="<?= esc_attr($link['icon']); ?> text-xl w-6 text-center"></i>
								<span class="font-semibold"><?= esc_html($link['label']); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</ul>
			</div>
		</div>
<?php
		return ob_get_clean();
	}
}
