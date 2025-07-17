<?php

namespace AstraChild\Core\Definitions;

class Core
{
	public static function getDefinitions(): array
	{
		return [
			\AstraChild\Core\Enqueue::class => fn($c) =>
				new \AstraChild\Core\Enqueue(),
			\AstraChild\Core\Hooks::class => fn($c) =>
				new \AstraChild\Core\Hooks(),
			\AstraChild\Core\Init::class => fn($c) =>
				new \AstraChild\Core\Init(
					[
						$c->get(\AstraChild\Core\Enqueue::class),
						$c->get(\AstraChild\Core\Hooks::class),
						$c->get(\AstraChild\Models\Schema\CustomFields::class),
						$c->get(\AstraChild\Models\Schema\Taxonomies::class),
						$c->get(\AstraChild\Models\Schema\PostTypes::class),
						$c->get(\AstraChild\Services\REST\RESTRoute::class),
						$c->get(\AstraChild\Services\Job\ArchiveServices::class),
						$c->get(\AstraChild\Services\PostsManagement\PostsManagement::class)
					],
				),
		];
	}
}
