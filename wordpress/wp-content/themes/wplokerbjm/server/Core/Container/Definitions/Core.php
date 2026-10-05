<?php

namespace WPLokerBJM\Core\Container\Definitions;

use Nette\Loaders\RobotLoader;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyAutowireScanner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookRuntimeResolver, HookTargetResolver, WPHooksContainerRegistry, WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\{Provider\WPHookPlanProvider, WPHooksScanner};
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;

/**
 * Core container definitions for the wplokerbjm theme.
 *
 * ## Hook Registry & Init
 *
 * This class provides manual definitions for key services that require special handling,
 * such as the WPHooksContainerRegistry and Init services.
 *
 * How it works:
 * 1. WPhooksScanner scans the server/ directory for #[Action] and #[Filter] attributes.
 * 2. WPHooksContainerRegistry receives the scanner results and pre-builds ContainerLazyHookInvoker and ContainerLazyPropertyHookInvoker instances
 *    (named invocable objects that defer container resolution to hook-fire time).
 * 3. Init delegates to WPHooksContainerRegistry::initialize() which registers hooks with WordPress
 *    via add_action/add_filter using the stored handler instances.
 *
 */
#[Injectable(skip: true)]
class Core implements DefinitionProviderInterface
{
    public function __construct(private RobotLoader $robotLoader) {}
    
    public function getDefinitions(): array
    {
        $namespace = 'WPLokerBJM';
        $scanner = new DependencyAutowireScanner($this->robotLoader, excludedSubNamespaces: [
            $namespace . '\\Core\\Container\\Support\\',
            $namespace . '\\Tests\\',
        ]);
        $autoWiredDefinitions = $scanner->scanForAutowirableClasses();


        $core = [
            WPHookPlanProvider::class => \DI\autowire(WPHookPlanProvider::class),
            HookTargetResolver::class => \DI\autowire(HookTargetResolver::class),
            RuntimeWPHookProvider::class => \DI\autowire(RuntimeWPHookProvider::class)->constructor(\DI\get(ContainerInterface::class))->lazy(),
            WPHooksScanner::class => \DI\autowire(WPHooksScanner::class)->constructor(
                \DI\get(RobotLoader::class),
                $namespace,
                static fn() => get_stylesheet_directory() . "/cache",
                \DI\get(WPHookPlanProvider::class)
            ),
            WPHooksInstanceObjectCache::class => \DI\autowire(WPHooksInstanceObjectCache::class)->constructor(
                static fn(): string => get_stylesheet_directory() . '/cache/WPHooksInstanceObjectCache.php'
            ),
            WPHooksInstanceRegistry::class => \DI\autowire(WPHooksInstanceRegistry::class)->constructor(
                \DI\get(HookRuntimeResolver::class),
                static fn() => new EntriesIndexer(),
                \DI\get(WPHooksInstanceObjectCache::class),
                \DI\get(RuntimeWPHookProvider::class),
            ),
            DeferredHookManager::class => \DI\autowire(DeferredHookManager::class)->constructor(
                \DI\get(WPHookPlanProvider::class),
                \DI\get(ContainerInterface::class),
                \DI\get(HookTargetResolver::class),
                static fn() => new EntriesIndexer(),
            ),
            WPHooksContainerRegistry::class => \DI\autowire(WPHooksContainerRegistry::class)->constructor(
                \DI\get(ContainerInterface::class),
                static fn(WPHooksScanner $scanner): array => $scanner->getHookRegistrations(),
                \DI\get(WPHookPlanProvider::class),
                \DI\get(DeferredHookManager::class),
                \DI\get(HookTargetResolver::class),
                static fn() => new EntriesIndexer(),
            ),
        ];

        return \array_merge($autoWiredDefinitions, $core);
    }
}
