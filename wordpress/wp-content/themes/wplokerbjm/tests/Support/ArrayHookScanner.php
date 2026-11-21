<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\Support;

use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;

/**
 * Scanner test double for registries seeded with raw registration arrays.
 *
 * The container registry only calls getHookRegistrations(); reflection and
 * cache behavior stay out of these focused unit tests.
 */
final class ArrayHookScanner extends WPHooksScanner
{
    /**
     * @param array<int, mixed> $registrations
     */
    public function __construct(
        private readonly array $registrations,
        WPHookPlanProvider $hookPlanProvider,
    ) {
        parent::__construct(static fn(): array => [], $hookPlanProvider);
    }

    public function getHookRegistrations(): array
    {
        return $this->registrations;
    }
}
