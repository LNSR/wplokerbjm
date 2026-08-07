<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Core\Container\Support\WPHooks\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Hook attributes on magic methods are rejected by the scanner — only
 * __invoke may be hooked. The fixture namespaces live outside WPLokerBJM so
 * the broad production-namespace scans never see them.
 */
class HookScannerMagicMethodTest extends WplokerbjmTestCase
{
    public function testMagicMethodAttributeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('magic method');

        $scanner = new WPHooksScanner('MagicHookRejected', '', new WPHookPlanProvider());
        $scanner->getHookRegistrations();
    }

    public function testInvokeIsAllowed(): void
    {
        $scanner = new WPHooksScanner('MagicHookAllowed', '', new WPHookPlanProvider());
        $registrations = $scanner->getHookRegistrations();

        self::assertCount(1, $registrations);
        $registration = $registrations[0];
        self::assertSame('__invoke', $registration->method);
        self::assertSame('invoke_hook', $registration->hook);
        self::assertSame('action', $registration->type);
        self::assertFalse($registration->defer);
    }
}
