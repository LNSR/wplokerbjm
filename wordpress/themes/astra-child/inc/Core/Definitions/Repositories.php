<?php

namespace AstraChild\Core\Definitions;

class Repositories
{
	public static function getDefinitions(): array
	{
		return [
			// Bind repositories
			\AstraChild\Repositories\JobRepository::class => fn($c) =>
				new \AstraChild\Repositories\JobRepository(
					$c->get(\AstraChild\Factories\JobDataFactory::class)
				)
		];
	}
}
