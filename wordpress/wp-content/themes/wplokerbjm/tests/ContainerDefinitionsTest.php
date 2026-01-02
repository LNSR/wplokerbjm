<?php

namespace WPLokerBJM\Tests;

use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Core\Container\Definitions\AutoScanned;
use WPLokerBJM\Core\Container\Definitions\Core;
use WPLokerBJM\Core\Container\AutowireScanner;

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

    public function testHooksInterfaceImplementersCount()
    {
        // Create scanner like Core.php does
        $scanner = new AutowireScanner(
            get_stylesheet_directory() . '/server',
            'WPLokerBJM'
        );

        $hooksImplementers = $scanner->getInterfaceImplementerClassNames(
            \WPLokerBJM\Contracts\HooksInterface::class
        );

        $count = count($hooksImplementers);
        echo "\n\033[1;34m🔗 HooksInterface Implementers\033[0m\n";
        echo "\033[1;32m✓ Found $count classes implementing HooksInterface:\033[0m\n";
        foreach ($hooksImplementers as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";
        $this->assertGreaterThan(0, $count);
    }
}