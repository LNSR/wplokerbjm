<?php

namespace AstraChild\Core\Definitions;

class ViewModels
{
	public static function getDefinitions(): array
	{
		return [
			\AstraChild\ViewModels\Page\SingleViewModel::class => \DI\create()
				->constructor(
					\DI\get(\AstraChild\Repositories\JobRepository::class),
					\DI\get(\AstraChild\Services\CustomField\SocialMediaService::class),
					\DI\get(\AstraChild\Factories\JobDataFactory::class)
				),
			\AstraChild\ViewModels\Page\HomepageViewModel::class => \DI\create()
				->constructor(
					\DI\get(\AstraChild\Repositories\JobRepository::class),
				),

			\AstraChild\ViewModels\Page\ArchiveViewModel::class => \DI\create(),
			\AstraChild\ViewModels\Page\PasangIklanViewModel::class => \DI\create()

		];
	}
}
