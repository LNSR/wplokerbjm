<?php

namespace AstraChild\Core\Definitions;

class Repositories
{
	public static function getDefinitions(): array
	{
		return [
			// Bind repositories
			\AstraChild\Repositories\JobRepository::class => \DI\create()
				->constructor(
					\DI\get(\AstraChild\Factories\JobDataFactory::class),

				),
		];
	}
}
