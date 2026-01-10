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
        $definitions = AutoScanned::getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;36m📦 AutoScanned Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count autowirable classes:\033[0m\n";
        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";
        $this->assertGreaterThan(0, $count); // Should find at least some classes
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

        $hookRegistrations = $scanner->getHookRegistrations();

        $count = count($hookRegistrations);
        echo "\n\033[1;35m🏷️  Hook Attributes\033[0m\n";
        echo "\033[1;32m✓ Found $count hook attributes:\033[0m\n";

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
    }
}