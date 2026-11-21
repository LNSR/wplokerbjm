<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Logger;

use WPLokerBJM\Core\Container\Support\WPHooks\Logger\HookLoggerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\SharedLoggerAdapter;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;
use WPLokerBJM\Tests\Support\InMemoryHookLogger;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

final class HookLoggerTest extends WplokerbjmTestCase
{
    public function testScannerUsesInjectedLogger(): void
    {
        $logger = new InMemoryHookLogger();
        $scanner = new WPHooksScanner(
            static fn(): array => [],
            $this->container()->get(WPHookPlanProvider::class),
            'WPLokerBJM',
            '',
            $logger,
        );

        self::assertFalse($scanner->deleteCache());
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringContainsString('Delete failed', $logger->records[0]['message']);
    }

    public function testNamedLoggerBindingUsesSharedAdapter(): void
    {
        self::assertInstanceOf(
            SharedLoggerAdapter::class,
            $this->container()->get(HookLoggerInterface::class),
        );
    }
}
