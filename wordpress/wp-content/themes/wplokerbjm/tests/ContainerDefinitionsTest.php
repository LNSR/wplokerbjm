<?php

namespace WPLokerBJM\Tests;

use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Core\Container\Definitions\AutoScanned;
use WPLokerBJM\Core\Container\Definitions\Core;
use WPLokerBJM\Core\Container\Support\AutowireScanner;

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

    public function testCoreDefinitionsCount()
    {
        $definitions = Core::getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;35m🏗️  Core Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count manual definitions:\033[0m\n";
        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";
        $this->assertEquals(1, $count); // Should have exactly the Init service
        $this->assertArrayHasKey(\WPLokerBJM\Core\Container\Init::class, $definitions);
    }

    public function testHookAttributesFound()
    {
        // Create scanner like Core.php does
        $scanner = new AutowireScanner(
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