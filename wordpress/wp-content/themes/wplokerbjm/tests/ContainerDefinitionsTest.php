<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use WPLokerBJM\Core\Container\Definitions\Factory;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Core\Container\Definitions\AutoScanned;
use WPLokerBJM\Core\Container\Definitions\Core;
use WPLokerBJM\Core\Container\Definitions\DefinitionProviderInterface;
use WPLokerBJM\Core\Container\Support\{AutowireScanner, WPhooksScanner};
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;

class ContainerDefinitionsTest extends WplokerbjmTestCase
{
    public function testAutoScannedDefinitionsCount()
    {
        // Measure time for first scan (cache miss)
        $start = microtime(true);
        $definitions = AutoScanned::getDefinitions();
        $end = microtime(true);
        $timeFirst = $end - $start;

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;36m📦 AutoScanned Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count autowirable classes:\033[0m\n";
        echo "\033[1;33m⏱️  First scan time: " . number_format($timeFirst, 4) . " seconds\033[0m\n";

        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";
        $this->assertGreaterThan(0, $count); // Should find at least some classes

        // Measure time for second scan (cache hit)
        $start2 = microtime(true);
        $definitions2 = AutoScanned::getDefinitions();
        $end2 = microtime(true);
        $timeSecond = $end2 - $start2;

        echo "\033[1;33m⏱️  Cached scan time: " . number_format($timeSecond, 4) . " seconds\033[0m\n";
        echo "\n";

        // Assert definitions are identical
        $this->assertEquals($definitions, $definitions2);

        // Assert cached scan is faster (at least 10x faster, but allow some variance)
        $this->assertLessThan($timeFirst * 0.1, $timeSecond, 'Cached scan should be significantly faster');
    }

    public function testCoreDefinitions()
    {
        $definitions = Core::getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;35m🏗️  Core Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count manual core definition:\033[0m\n";
        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";
        $this->assertEquals(1, $count, 'Core should have exactly 1 definition');
        $this->assertArrayHasKey(Init::class, $definitions);
    }

    public function testFactoryDefinitions()
    {
        $definitions = Factory::getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;35m🏭 Factory Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count factory definitions:\033[0m\n";
        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";

        $this->assertEquals(3, $count, 'Factory should have exactly 3 definitions');
        $this->assertArrayHasKey(WPhooksScanner::class, $definitions, 'WPhooksScanner should be in factory definitions');
        $this->assertArrayHasKey(Cloudflare::class, $definitions, 'Cloudflare should be in factory definitions');
        $this->assertArrayHasKey(RedisAdapter::class, $definitions, 'RedisAdapter should be in factory definitions');
    }

    public function testCoreAndFactoryDefinitions()
    {
        $definitions = array_merge(Core::getDefinitions(), Factory::getDefinitions());

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;35m🏗️  Core + Factory Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count combined definitions:\033[0m\n";
        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";

        $this->assertEquals(4, $count, 'Core + Factory should have exactly 4 definitions');
        $this->assertArrayHasKey(Init::class, $definitions);
        $this->assertArrayHasKey(WPhooksScanner::class, $definitions);
        $this->assertArrayHasKey(Cloudflare::class, $definitions);
        $this->assertArrayHasKey(RedisAdapter::class, $definitions);
    }

    public function testAutowireScannerCount()
    {
        $scanner = new AutowireScanner(
            get_stylesheet_directory() . '/server',
            'WPLokerBJM'
        );

        // First scan (cache miss)
        $start = microtime(true);
        $definitions = $scanner->scanForAutowirableClasses();
        $end = microtime(true);
        $timeFirst = $end - $start;

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;36m🔍 AutowireScanner\033[0m\n";
        echo "\033[1;32m✓ Found $count autowirable classes:\033[0m\n";
        echo "\033[1;33m⏱️  First scan time: " . number_format($timeFirst, 4) . " seconds\033[0m\n";

        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";
        $this->assertGreaterThan(0, $count);

        // Second scan (cache hit via wp_cache mock)
        $start2 = microtime(true);
        $definitions2 = $scanner->scanForAutowirableClasses();
        $end2 = microtime(true);
        $timeSecond = $end2 - $start2;

        echo "\033[1;33m⏱️  Cached scan time: " . number_format($timeSecond, 4) . " seconds\033[0m\n";
        echo "\n";

        $this->assertEquals($definitions, $definitions2);
        $this->assertLessThan($timeFirst * 0.1, $timeSecond, 'Cached AutowireScanner scan should be significantly faster');
    }

    public function testManualDefinitionsNotInAutoScanned()
    {
        $autoDefs = AutoScanned::getDefinitions();
        $manualDefs = array_merge(Core::getDefinitions(), Factory::getDefinitions());

        echo "\n\033[1;35m🔒 Manual vs Auto-Scanned\033[0m\n";
        echo "\033[1;32m✓ Verifying manual definitions are excluded from auto-scanned:\033[0m\n";

        foreach (array_keys($manualDefs) as $className) {
            $this->assertArrayNotHasKey(
                $className,
                $autoDefs,
                "Manual definition '$className' should not appear in auto-scanned definitions"
            );
            echo "  \033[0;32m✓\033[0m $className excluded from auto-scan\n";
        }

        echo "\n";
        echo "\033[1;32m✓ All " . count($manualDefs) . " manual definitions correctly excluded from auto-scan\033[0m\n";
        echo "\n";
    }

    public function testDefinitionProvidersImplementInterface()
    {
        $providers = [
            Core::class,
            Factory::class,
            AutoScanned::class,
        ];

        echo "\n\033[1;35m📋 DefinitionProviderInterface\033[0m\n";
        echo "\033[1;32m✓ Verifying all definition providers implement DefinitionProviderInterface:\033[0m\n";

        foreach ($providers as $providerClass) {
            $this->assertTrue(
                is_subclass_of($providerClass, DefinitionProviderInterface::class),
                "'$providerClass' should implement DefinitionProviderInterface"
            );
            echo "  \033[0;32m✓\033[0m $providerClass implements DefinitionProviderInterface\n";
        }

        echo "\n";
    }

    public function testHookAttributesFound()
    {
        // Create scanner like Core.php does
        $scanner = new WPhooksScanner(
            get_stylesheet_directory() . '/server',
            'WPLokerBJM'
        );

        // Measure time for first hook scan (cache miss)
        $start = microtime(true);
        $hookRegistrations = $scanner->getHookRegistrations();
        $end = microtime(true);
        $timeFirst = $end - $start;

        $count = count($hookRegistrations);
        echo "\n\033[1;35m🏷️  Hook Attributes\033[0m\n";
        echo "\033[1;32m✓ Found $count hook attributes:\033[0m\n";
        echo "\033[1;33m⏱️  First hook scan time: " . number_format($timeFirst, 4) . " seconds\033[0m\n";

        // Group by type
        $actions = array_filter($hookRegistrations, fn($reg) => $reg['type'] === 'action');
        $filters = array_filter($hookRegistrations, fn($reg) => $reg['type'] === 'filter');

        // Display actions
        if (!empty($actions)) {
            echo "\033[1;32m🔧 Actions:\033[0m\n";
            foreach ($actions as $reg) {
                echo "  \033[0;32m•\033[0m {$reg['hook']} on {$reg['class']}::{$reg['method']} (priority: {$reg['priority']})\n";
            }
        }

        // Display filters
        if (!empty($filters)) {
            echo "\033[1;34m🔍 Filters:\033[0m\n";
            foreach ($filters as $reg) {
                echo "  \033[0;34m•\033[0m {$reg['hook']} on {$reg['class']}::{$reg['method']} (priority: {$reg['priority']})\n";
            }
        }

        echo "\n";
        $this->assertGreaterThan(0, $count);

        // Measure time for second hook scan (cache hit)
        $start2 = microtime(true);
        $hookRegistrations2 = $scanner->getHookRegistrations();
        $end2 = microtime(true);
        $timeSecond = $end2 - $start2;

        echo "\033[1;33m⏱️  Cached hook scan time: " . number_format($timeSecond, 4) . " seconds\033[0m\n";
        echo "\n";

        // Assert hook registrations are identical
        $this->assertEquals($hookRegistrations, $hookRegistrations2);

        // Assert scan times are reasonable (both should be fast, caching may not provide much benefit for already fast operations)
        $this->assertLessThan(1.0, $timeFirst, 'First hook scan should be fast');
        $this->assertLessThan(1.0, $timeSecond, 'Cached hook scan should be fast');
    }
}
