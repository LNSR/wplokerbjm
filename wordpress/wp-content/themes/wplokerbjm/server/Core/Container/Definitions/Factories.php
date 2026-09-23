<?php

namespace WPLokerBJM\Core\Container\Definitions;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Configs\Credential\{RedisCred, CloudflareCred, CredentialConfig};
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\{DependencyInjector, PlanCache, PlanCompiler, ScopeAccessFactory};

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
            Cloudflare::class => \DI\autowire(Cloudflare::class)->constructor(static fn(): CloudflareCred => CredentialConfig::CloudflareCredential()),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)->constructor(static fn(): RedisCred => CredentialConfig::RedisCredential()),
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
