<?php

namespace WPLokerBJM\Core\Container\Definitions;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\AutowireScanner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookRuntimeResolver, HookTargetResolver, WPHooksContainerRegistry, WPHooksRuntimeCache, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\{Provider\WPHookPlanProvider, WPHooksScanner};
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Configs\Credential\CredentialConfig;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\PlanCache;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\PlanCompiler;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\ScopeAccessFactory;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Core\DependencyInjectorHookActions;
use WPLokerBJM\Core\HooksRuntimeRegistryActions;
use WPLokerBJM\Core\Plugins\PluginManagement;

interface DefinitionProviderInterface
{
    /**
     * Return PHP-DI container definitions.
     *
     * @return array<string|class-string, mixed>
     */
    public static function getDefinitions(): array;
}

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
        $scanner = new AutowireScanner($namespace);
        $autoWiredDefinitions = $scanner->scanForAutowirableClasses();

        $core = [
            WPHookPlanProvider::class => \DI\autowire(WPHookPlanProvider::class),
            HookTargetResolver::class => \DI\autowire(HookTargetResolver::class),
            RuntimeWPHookProvider::class => \DI\autowire(RuntimeWPHookProvider::class)->constructor(\DI\get(ContainerInterface::class))->lazy(),
            WPHooksScanner::class => \DI\autowire(WPHooksScanner::class)->constructor($namespace, static fn() => get_stylesheet_directory() . "/cache", \DI\get(WPHookPlanProvider::class))->lazy(),
            WPHooksRuntimeCache::class => \DI\autowire(WPHooksRuntimeCache::class)->constructor(
                static fn(): string => get_stylesheet_directory() . '/cache/WPHooksRuntimeCache.php'
            ),
            WPHooksRuntimeRegistry::class => \DI\autowire(WPHooksRuntimeRegistry::class)->constructor(
                \DI\get(HookRuntimeResolver::class),
                \DI\get(WPHooksRuntimeCache::class),
                \DI\get(RuntimeWPHookProvider::class),
            ),

            DeferredHookManager::class => \DI\autowire(DeferredHookManager::class)->constructor(
                \DI\get(WPHookPlanProvider::class),
                \DI\get(ContainerInterface::class),
                \DI\get(HookTargetResolver::class),
            ),
            WPHooksContainerRegistry::class => \DI\autowire(WPHooksContainerRegistry::class)->constructor(
                \DI\get(ContainerInterface::class),
                static fn(WPHooksScanner $scanner): array => $scanner->getHookRegistrations(),
                \DI\get(WPHookPlanProvider::class),
                \DI\get(DeferredHookManager::class),
                \DI\get(HookTargetResolver::class),
            ),
        ];

        return array_merge($autoWiredDefinitions, $core);
    }
}

/**
 * Factory definitions — Manually define your arguments here.
 */
class Factory implements DefinitionProviderInterface
{
    public static function getDefinitions(): array
    {

        return [
            ...self::getInstanceWithCredentials(),
            ...self::dependencyService(),
        ];
    }

    //! For creds, defer via closure because if not CompiledContainer.php gonna expose your creds
    private static function getInstanceWithCredentials(): array
    {
        return [
            Cloudflare::class => \DI\autowire(Cloudflare::class)->constructor(static fn(): array => CredentialConfig::CloudflareCredential()),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)->constructor(static fn(): array => CredentialConfig::RedisCredential()),
        ];
    }
    private static function dependencyService(): array
    {
        $dependencyInjector = [
            PlanCompiler::class => \DI\autowire(PlanCompiler::class),
            ScopeAccessFactory::class => \DI\autowire(ScopeAccessFactory::class),
            PlanCache::class => \DI\autowire(PlanCache::class)->constructor(
                static fn(): string => get_stylesheet_directory() . '/cache/DependencyInjectorCache.php'
            ),
            DependencyInjector::class => \DI\autowire(DependencyInjector::class)->constructor(
                \DI\get(ContainerInterface::class),
                \DI\get(ScopeAccessFactory::class),
                \DI\get(PlanCache::class),
                \DI\get(PlanCompiler::class),
            ),
        ];
        return $dependencyInjector;
    }
}
