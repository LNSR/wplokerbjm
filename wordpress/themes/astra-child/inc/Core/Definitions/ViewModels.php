<?php

namespace AstraChild\Core\Definitions;

class ViewModels
{
	public static function getDefinitions(): array
	{
		return [
			\AstraChild\ViewModels\Page\SingleViewModel::class => fn($c) =>
				new \AstraChild\ViewModels\Page\SingleViewModel(
					$c->get(\AstraChild\Repositories\JobRepository::class),
					$c->get(\AstraChild\Services\CustomField\SocialMediaService::class),
					$c->get(\AstraChild\Factories\JobDataFactory::class),
					$c->get(\AstraChild\Services\Job\FormatterServices::class),
					$c->get(\AstraChild\Services\Job\JobServices::class)
				),
			\AstraChild\ViewModels\Page\HomepageViewModel::class => fn() =>
				new \AstraChild\ViewModels\Page\HomepageViewModel(),
			\AstraChild\ViewModels\Page\ArchiveViewModel::class => fn() =>
				new \AstraChild\ViewModels\Page\ArchiveViewModel(),
			\AstraChild\ViewModels\Page\PasangIklanViewModel::class => fn() =>
				new \AstraChild\ViewModels\Page\PasangIklanViewModel()
		];
	}
}
