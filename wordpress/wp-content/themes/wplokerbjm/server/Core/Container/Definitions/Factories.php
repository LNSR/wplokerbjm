<?php
namespace WPLokerBJM\Core\Container\Definitions;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\AutowireScanner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{DeferredHookManager, HookTargetResolver, WPHooksContainerRegistry, WPHooksRuntimeRegistry};
use WPLokerBJM\Core\Container\Support\WPHooks\{Provider\WPHookPlanProvider, WPHooksScanner};
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Configs\CredentialConfig;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;

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
            HookTargetResolver::class => \DI\autowire(HookTargetResolver::class)->lazy(),
            RuntimeWPHookProvider::class => \DI\autowire(RuntimeWPHookProvider::class)->constructor(\DI\get(ContainerInterface::class))->lazy(),
            WPHooksScanner::class => \DI\autowire(WPHooksScanner::class)->constructor($namespace, static fn() => get_stylesheet_directory() . "/cache", \DI\get(WPHookPlanProvider::class))->lazy(),
            WPHooksRuntimeRegistry::class => \DI\autowire(WPHooksRuntimeRegistry::class)->constructor(\DI\get(RuntimeWPHookProvider::class))->lazy(),
            DeferredHookManager::class => \DI\autowire(DeferredHookManager::class)->constructor(\DI\get(WPHookPlanProvider::class), \DI\get(ContainerInterface::class), \DI\get(HookTargetResolver::class))->lazy(),
            WPHooksContainerRegistry::class => \DI\autowire(WPHooksContainerRegistry::class)->constructor(
                \DI\get(ContainerInterface::class),
                \DI\factory(static function (WPHooksScanner $scanner) {
                    return $scanner->getHookRegistrations();
                }),
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
        ];
    }

    //! For creds, defer via closure because if not CompiledContainer.php gonna expose your creds
    private static function getInstanceWithCredentials(): array
    {
        return [
            Cloudflare::class => \DI\autowire(Cloudflare::class)->constructor(static fn() => CredentialConfig::CloudflareCredential()),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)->constructor(static fn() => CredentialConfig::RedisCredential()),
        ];
    }
}
