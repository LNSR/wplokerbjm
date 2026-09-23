<?php

namespace WPLokerBJM\Core\Container\Definitions;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\AutowireScanner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookRuntimeResolver, HookTargetResolver, WPHooksContainerRegistry, WPHooksRuntimeCache, WPHooksInstanceRegistry};
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
 * 2. WPHooksContainerRegistry receives the scanner results and pre-builds ContainerLazyHookHandler and ContainerLazyPropertyHookHandler instances
 *    (named invocable objects that defer container resolution to hook-fire time).
 * 3. Init delegates to WPHooksContainerRegistry::initialize() which registers hooks with WordPress
 *    via add_action/add_filter using the stored handler instances.
 *
 */
class Core implements DefinitionProviderInterface
{
    public static function getDefinitions(): array
    {
        $namespace = 'WPLokerBJM';
        $scanner = new AutowireScanner(excludedSubNamespaces: [
            $namespace . '\\Core\\Container\\Support\\',
            $namespace . '\\Tests\\',
        ]);
        $autoWiredDefinitions = $scanner->scanForAutowirableClasses();


        $core = [
            WPHookPlanProvider::class => \DI\autowire(WPHookPlanProvider::class),
            HookTargetResolver::class => \DI\autowire(HookTargetResolver::class),
            RuntimeWPHookProvider::class => \DI\autowire(RuntimeWPHookProvider::class)->constructor(\DI\get(ContainerInterface::class))->lazy(),
            WPHooksScanner::class => \DI\autowire(WPHooksScanner::class)->constructor($namespace, static fn() => get_stylesheet_directory() . "/cache", \DI\get(WPHookPlanProvider::class))->lazy(),
            WPHooksRuntimeCache::class => \DI\autowire(WPHooksRuntimeCache::class)->constructor(
                static fn(): string => get_stylesheet_directory() . '/cache/WPHooksRuntimeCache.php'
            ),
            WPHooksInstanceRegistry::class => \DI\autowire(WPHooksInstanceRegistry::class)->constructor(
                \DI\get(HookRuntimeResolver::class),
                static fn() => new EntriesIndexer(),
                \DI\get(WPHooksRuntimeCache::class),
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

        return array_merge($autoWiredDefinitions, $core);
    }
}
