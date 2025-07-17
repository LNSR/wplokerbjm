<?php

namespace AstraChild\Contracts;

/**
 * Interface HooksInterface
 *
 * Defines methods for registering WordPress actions and filters.
 */
interface HooksInterface
{
	/**
	 * Register WordPress actions.
	 *
	 * @return void
	 */
	public function registerActions(): void;

	/**
	 * Register WordPress filters.
	 *
	 * @return void
	 */
	public function registerFilters(): void;
}
