<?php

namespace AstraChild\Core\Definitions;

class Core
{
	public static function getDefinitions(): array
	{
		return [
			\AstraChild\Core\Enqueue::class => \DI\create(),
			\AstraChild\Core\Actions::class => \DI\create(),
			\AstraChild\Core\Filters::class => \DI\create(),
			\AstraChild\Core\Init::class => \DI\create()
				->constructor([
					\DI\get(\AstraChild\Core\Enqueue::class),
					\DI\get(\AstraChild\Core\Actions::class),
					\DI\get(\AstraChild\Core\Filters::class),
					\DI\get(\AstraChild\Models\Schema\CustomFields::class),
					\DI\get(\AstraChild\Models\Schema\Taxonomies::class),
					\DI\get(\AstraChild\Models\Schema\PostTypes::class),
					\DI\get(\AstraChild\Services\REST\RESTServices::class),
					\DI\get(\AstraChild\Services\Job\ArchiveServices::class),
					\DI\get(\AstraChild\Services\PostsManagement\PostsManagement::class),
				]),
		];
	}
}
