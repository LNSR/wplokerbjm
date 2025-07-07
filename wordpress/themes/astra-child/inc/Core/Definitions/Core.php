<?php

namespace AstraChild\Core\Definitions;

class Core
{
	public static function getDefinitions(): array
	{
		return [
			\AstraChild\Core\Enqueue::class => fn($c) =>
				new \AstraChild\Core\Enqueue(),
			\AstraChild\Core\Actions::class => fn($c) =>
				new \AstraChild\Core\Actions(),
			\AstraChild\Core\Filters::class => fn($c) =>
				new \AstraChild\Core\Filters(),
			\AstraChild\Core\Init::class => fn($c) =>
				new \AstraChild\Core\Init(
					[
						$c->get(\AstraChild\Core\Enqueue::class),
						$c->get(\AstraChild\Core\Actions::class),
						$c->get(\AstraChild\Core\Filters::class),
						$c->get(\AstraChild\Models\Schema\CustomFields::class),
						$c->get(\AstraChild\Models\Schema\Taxonomies::class),
						$c->get(\AstraChild\Models\Schema\PostTypes::class),
						$c->get(\AstraChild\Services\REST\RESTServices::class),
						$c->get(\AstraChild\Services\Job\ArchiveServices::class),
						$c->get(\AstraChild\Services\PostsManagement\PostsManagement::class)
					],
				),
		];
	}
}
