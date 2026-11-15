<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Nette\Loaders\RobotLoader;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use WPLokerBJM\Core\Container\Definitions\Factory;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Tests\Support\Terminal;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Core\Container\Definitions\Core;
use WPLokerBJM\Core\Container\Definitions\DefinitionProviderInterface;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyAutowireScanner;
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Services\WebHooks\CloudflareCachePurger;
use WPLokerBJM\Adapter\RedisAdapter;

#[AllowMockObjectsWithoutExpectations]
class ContainerDefinitionsTest extends WplokerbjmTestCase
{
    public static string $NAMESPACE = "WPLokerBJM";
    private RobotLoader $testRobotLoader;
    protected function setUp(): void
    {
        global $testRobotLoader;
        $this->testRobotLoader = $testRobotLoader;
        parent::setUp();
    }


    public function testCoreDefinitions()
    {
        $definitions = new Core($this->testRobotLoader)->getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        Terminal::out("\n\033[1;35m🏗️  Core Definitions\033[0m\n");
        Terminal::out("\033[1;32m✓ Found $count definitions (autowired + core):\033[0m\n");
        foreach (array_keys($definitions) as $className) {
            Terminal::out("  \033[0;33m•\033[0m $className\n");
        }
        Terminal::out("\n");

        $this->assertGreaterThanOrEqual(3, $count, 'Core Registered');
        $this->assertArrayHasKey(Init::class, $definitions, 'Init should be in core definitions');
        $this->assertArrayHasKey(WPHooksContainerRegistry::class, $definitions, 'WPHooksContainerRegistry should be in core definitions');
    }

    public function testFactoryDefinitions()
    {
        $definitions = new Factory()->getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        Terminal::out("\n\033[1;35m🏭 Factory Definitions\033[0m\n");
        Terminal::out("\033[1;32m✓ Found $count factory definitions:\033[0m\n");
        foreach (array_keys($definitions) as $className) {
            Terminal::out("  \033[0;33m•\033[0m $className\n");
        }
        Terminal::out("\n");

        $this->assertIsArray($definitions, 'Factory should be array');
        $this->assertArrayHasKey(CloudflareCachePurger::class, $definitions, 'Cloudflare should be in factory definitions');
        $this->assertArrayHasKey(RedisAdapter::class, $definitions, 'RedisAdapter should be in factory definitions');
    }

    public function testCoreAndFactoryDefinitions()
    {
        $definitions = array_merge(new Core($this->testRobotLoader)->getDefinitions(), new Factory()->getDefinitions());

        $this->assertIsArray($definitions);
        $count = count($definitions);
        Terminal::out("\n\033[1;35m🏗️  Core + Factory Definitions\033[0m\n");
        Terminal::out("\033[1;32m✓ Found $count combined definitions:\033[0m\n");
        foreach (array_keys($definitions) as $className) {
            Terminal::out("  \033[0;33m•\033[0m $className\n");
        }
        Terminal::out("\n");

        $this->assertGreaterThanOrEqual(3, $count, 'Core + Factory should have at least 3 definitions');
        $this->assertArrayHasKey(Init::class, $definitions);
        $this->assertArrayHasKey(WPHooksContainerRegistry::class, $definitions);
        $this->assertArrayHasKey(CloudflareCachePurger::class, $definitions);
        $this->assertArrayHasKey(RedisAdapter::class, $definitions);
    }

    public function testDependencyAutowireScannerCount()
    {
        $namespace = 'WPLokerBJM';
        $scanner = new DependencyAutowireScanner($this->testRobotLoader, excludedSubNamespaces: [
            $namespace . '\\Core\\Container\\Support\\',
            $namespace . '\\Tests\\',
        ]);

        // First scan (cache miss)
        $start = microtime(true);
        $definitions = $scanner->scanForAutowirableClasses();
        $end = microtime(true);
        $timeFirst = $end - $start;

        $this->assertIsArray($definitions);
        $count = count($definitions);
        Terminal::out("\n\033[1;36m🔍 DependencyAutowireScanner\033[0m\n");
        Terminal::out("\033[1;32m✓ Found $count autowirable classes:\033[0m\n");
        Terminal::out("\033[1;33m⏱️  First scan time: " . number_format($timeFirst, 4) . " seconds\033[0m\n");

        foreach (array_keys($definitions) as $className) {
            Terminal::out("  \033[0;33m•\033[0m $className\n");
        }
        Terminal::out("\n");
        $this->assertGreaterThan(0, $count);

        // Second scan (cache hit via wp_cache mock)
        $start2 = microtime(true);
        $definitions2 = $scanner->scanForAutowirableClasses();
        $end2 = microtime(true);
        $timeSecond = $end2 - $start2;

        Terminal::out("\033[1;33m⏱️  Cached scan time: " . number_format($timeSecond, 4) . " seconds\033[0m\n");
        Terminal::out("\n");

        $this->assertEquals($definitions, $definitions2);
        $this->assertLessThan($timeFirst * 0.1, $timeSecond, 'Cached DependencyAutowireScanner scan should be significantly faster');
    }

    public function testDefinitionProvidersImplementInterface()
    {
        $providers = [
            Core::class,
            Factory::class,
        ];

        Terminal::out("\n\033[1;35m📋 DefinitionProviderInterface\033[0m\n");
        Terminal::out("\033[1;32m✓ Verifying all definition providers implement DefinitionProviderInterface:\033[0m\n");

        foreach ($providers as $providerClass) {
            $this->assertTrue(
                is_subclass_of($providerClass, DefinitionProviderInterface::class),
                "'$providerClass' should implement DefinitionProviderInterface"
            );
            Terminal::out("  \033[0;32m✓\033[0m $providerClass implements DefinitionProviderInterface\n");
        }

        Terminal::out("\n");
    }

}
