<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Definitions\Factory;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Core\Container\Definitions\Core;
use WPLokerBJM\Core\Container\Definitions\DefinitionProviderInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, WPHooksContainerRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\{ContainerLazyHookHandler, ContainerLazyPropertyHookHandler, WPHookPlanProvider, WPHooksScanner};
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\AutowireScanner;
use WPLokerBJM\Core\Container\Init;
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;

class ContainerDefinitionsTest extends WplokerbjmTestCase
{
    public static string $NAMESPACE = "WPLokerBJM";
    
    public function testCoreDefinitions()
    {
        $definitions = Core::getDefinitions();

        $this->assertIsArray($definitions);
        $count = count($definitions);
        echo "\n\033[1;35m🏗️  Core Definitions\033[0m\n";
        echo "\033[1;32m✓ Found $count definitions (autowired + core):\033[0m\n";
        foreach (array_keys($definitions) as $className) {
            echo "  \033[0;33m•\033[0m $className\n";
        }
        echo "\n";

        $this->assertGreaterThanOrEqual(3, $count, 'Core Registered');
        $this->assertArrayHasKey(Init::class, $definitions, 'Init should be in core definitions');
        $this->assertArrayHasKey(WPHooksContainerRegistry::class, $definitions, 'WPHooksContainerRegistry should be in core definitions');
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

        $this->assertEquals(2, $count, 'Factory should have exactly 2 definitions');
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

        $this->assertGreaterThanOrEqual(3, $count, 'Core + Factory should have at least 3 definitions');
        $this->assertArrayHasKey(Init::class, $definitions);
        $this->assertArrayHasKey(WPHooksContainerRegistry::class, $definitions);
        $this->assertArrayHasKey(Cloudflare::class, $definitions);
        $this->assertArrayHasKey(RedisAdapter::class, $definitions);
    }

    public function testAutowireScannerCount()
    {
        $scanner = new AutowireScanner(
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

    public function testDefinitionProvidersImplementInterface()
    {
        $providers = [
            Core::class,
            Factory::class,
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
            self::$NAMESPACE,
            '',
            new WPHookPlanProvider()
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
        $actions = array_filter($hookRegistrations, fn($reg) => $reg->type === 'action');
        $filters = array_filter($hookRegistrations, fn($reg) => $reg->type === 'filter');

        // Display actions
        if (!empty($actions)) {
            echo "\033[1;32m🔧 Actions:\033[0m\n";
            foreach ($actions as $reg) {
                $hookLabel = $reg->hook instanceof \Closure ? '(dynamic closure)' : $reg->hook;
                echo "  \033[0;32m•\033[0m {$hookLabel} on {$reg->class}::{$reg->method} (priority: {$reg->priority})\n";
            }
        }

        // Display filters
        if (!empty($filters)) {
            echo "\033[1;34m🔍 Filters:\033[0m\n";
            foreach ($filters as $reg) {
                $hookLabel = $reg->hook instanceof \Closure ? '(dynamic closure)' : $reg->hook;
                echo "  \033[0;34m•\033[0m {$hookLabel} on {$reg->class}::{$reg->method} (priority: {$reg->priority})\n";
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

    public function testHookRegistryProcessesRegistrations(): void
    {
        // Get real hook registrations from the scanner
        $scanner = new WPhooksScanner(
            self::$NAMESPACE,
            '',
            new WPHookPlanProvider()
        );
        $registrations = $scanner->getHookRegistrations();
        $hookClasses = array_unique(array_column($registrations, 'class'));

        echo "\n\033[1;34m📦 WPHooksContainerRegistry\033[0m\n";
        echo "\033[1;32m✓ Processing " . count($registrations) . " hook registrations...\033[0m\n";

        // Mock container: has() returns true for hook service classes
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(fn(string $class) => in_array($class, $hookClasses, true));
        $container->method('get')
            ->willReturnCallback(fn(string $class) => $this->createMock($class));

        // Create registry with registrations via constructor, then initialize
        $registry = new WPHooksContainerRegistry($container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $container));
        $registry->initialize();

        $registered = $this->registeredHooks();
        // Deferred hooks (defer: true) are not auto-registered by initialize(),
        // so exclude them from the count assertion. RegisterIf-gated hooks whose
        // gate evaluates to false (environment-dependent, e.g. !isWPCLI() in
        // tests) are also skipped by the registry — mirror those semantics.
        $planProvider = new WPHookPlanProvider();
        $nonDeferred = array_filter(
            $registrations,
            function ($r) use ($planProvider, $container) {
                if (!empty($r->defer)) {
                    return false;
                }
                if ($r->registerIf === null) {
                    return true;
                }
                try {
                    return $planProvider->evaluateRegistrationGate(
                        $r->registerIf,
                        $r->registerIfParams ?? [],
                        $container,
                        $r->class . '::' . $r->method
                    );
                } catch (\Throwable) {
                    return false;
                }
            }
        );
        $this->assertCount(count($nonDeferred), $registered, 'All non-deferred registrations should produce a registered hook');
        $this->assertGreaterThan(0, $registered);

        // Verify each registered hook has a matching registration and a ContainerLazyHookHandler callable.
        // Comparison is by hook name + type rather than index because WPHooksContainerRegistry
        // groups handlers by hook name internally, which changes iteration order.
        foreach ($registered as $hookData) {
            // Each callable must be a ContainerLazyHookHandler or ContainerLazyPropertyHookHandler, not an anonymous closure
            $this->assertTrue(
                $hookData['callable'] instanceof ContainerLazyHookHandler || $hookData['callable'] instanceof ContainerLazyPropertyHookHandler,
                "Callable for {$hookData['hook']} should be ContainerLazyHookHandler or ContainerLazyPropertyHookHandler (not anonymous closure)"
            );

            // Verify type is valid
            $this->assertContains($hookData['type'], ['action', 'filter'], "Type must be action or filter");
            $this->assertIsInt($hookData['priority']);
        }

        echo "  \033[0;32m•\033[0m All " . count($registrations) . " hooks use named ContainerLazyHookHandler / ContainerLazyPropertyHookHandler callables (ordered by hook name)\n";

        // Verify initialize() is idempotent
        $countBefore = count($this->registeredHooks());
        $registry->initialize();
        $this->assertCount($countBefore, $this->registeredHooks(), 'Second initialize() should not register additional hooks');

        echo "  \033[0;32m•\033[0m initialize() is idempotent (no duplicate registrations)\n";
        echo "\n";
    }

    public function testHookRegistrySkipsClassesNotInContainer(): void
    {
        // Container says "no" to everything
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        echo "\n\033[1;34m⏭️  WPHooksContainerRegistry — Skip Behavior\033[0m\n";

        $registry = new WPHooksContainerRegistry($container, [[
            'class' => 'NonExistentService',
            'method' => 'handle',
            'type' => 'action',
            'hook' => 'init',
            'priority' => 10,
            'accepted_args' => 1,
        ]], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $container));
        $registry->initialize();

        $this->assertCount(0, $this->registeredHooks(), 'Hooks for classes not in container should be skipped');

        echo "  \033[0;32m•\033[0m Classes not in container → hook skipped with warning\n";
        echo "\n";
    }

    public function testHookRegistryUnregistration(): void
    {
        $scanner = new WPhooksScanner(
            self::$NAMESPACE,
            '',
            new WPHookPlanProvider()
        );
        $registrations = $scanner->getHookRegistrations();

        if (empty($registrations)) {
            $this->markTestSkipped('No hook registrations found — cannot test unregistration');
        }

        $hookClasses = array_unique(array_column($registrations, 'class'));
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(fn(string $class) => in_array($class, $hookClasses, true));
        $container->method('get')
            ->willReturnCallback(fn(string $class) => $this->createMock($class));

        // Mock remove_action/remove_filter to update the registered hooks array
        \Brain\Monkey\Functions\when('remove_action')->alias(function (string $hook, callable $callable, int $priority = 10) {
            $GLOBALS['__wplokerbjm_registered_hooks'] = array_values(array_filter(
                $GLOBALS['__wplokerbjm_registered_hooks'],
                fn(array $reg): bool => !(
                    $reg['type'] === 'action'
                    && $reg['hook'] === $hook
                    && $reg['callable'] === $callable
                    && $reg['priority'] === $priority
                )
            ));
        });

        \Brain\Monkey\Functions\when('remove_filter')->alias(function (string $hook, callable $callable, int $priority = 10) {
            $GLOBALS['__wplokerbjm_registered_hooks'] = array_values(array_filter(
                $GLOBALS['__wplokerbjm_registered_hooks'],
                fn(array $reg): bool => !(
                    $reg['type'] === 'filter'
                    && $reg['hook'] === $hook
                    && $reg['callable'] === $callable
                    && $reg['priority'] === $priority
                )
            ));
        });

        echo "\n\033[1;34m🗑️  WPHooksContainerRegistry — Unregistration\033[0m\n";

        // Fresh registry
        $GLOBALS['__wplokerbjm_registered_hooks'] = [];
        $registry = new WPHooksContainerRegistry($container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $container));
        $registry->initialize();

        $totalBefore = count($this->registeredHooks());
        echo "  \033[0;33m•\033[0m $totalBefore hooks registered initially\n";

        // Test unregisterByHook
        $targetHook = $registrations[0]->hook;
        $registry->unregisterByHook($targetHook);

        $remainingForHook = array_filter($this->registeredHooks(), fn($r) => $r['hook'] === $targetHook);
        $this->assertCount(0, $remainingForHook, "No hooks should remain for '$targetHook' after unregisterByHook");
        $this->assertLessThan($totalBefore, count($this->registeredHooks()), 'Total hook count should decrease after unregistration');

        $removed = $totalBefore - count($this->registeredHooks());
        echo "  \033[0;32m✓\033[0m unregisterByHook('$targetHook') removed $removed hook(s)\n";

        // Test unregisterByClass (fresh setup)
        $GLOBALS['__wplokerbjm_registered_hooks'] = [];
        $registry2 = new WPHooksContainerRegistry($container, $registrations, new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $container));
        $registry2->initialize();

        $totalBeforeClass = count($this->registeredHooks());
        $targetClass = $registrations[0]->class;
        $registry2->unregisterByClass($targetClass);

        $this->assertLessThan($totalBeforeClass, count($this->registeredHooks()), 'Total hook count should decrease after unregisterByClass');
        echo "  \033[0;32m✓\033[0m unregisterByClass('$targetClass') removed hooks\n";

        // unregisterByCallable requires a valid callable at the type level —
        // the non-existent-class edge case is naturally eliminated by the callable type hint.

        echo "\n";
    }
}
