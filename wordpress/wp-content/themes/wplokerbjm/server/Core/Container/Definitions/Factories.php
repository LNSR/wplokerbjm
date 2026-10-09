<?php

namespace WPLokerBJM\Core\Container\Definitions;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Configs\Credential\{RedisCred, CloudflareCred, CredentialConfig};
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Core\Container\Attributes\Injectable;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\{DependencyInjector, PlanCache, PlanCompiler, ScopeAccessFactory};
use WPLokerBJM\Services\WebHooks\CloudflareCachePurger;

/**
 * Factory definitions — Manually define your arguments here.
 */
#[Injectable(skip: true)]
class Factory implements DefinitionProviderInterface
{
    public function getDefinitions(): array
    {

        return [
            ...$this->getInstanceWithCredentials(),
            ...$this->dependencyService(),
        ];
    }

    //! For creds, defer via closure because if not CompiledContainer.php gonna expose your creds
    private function getInstanceWithCredentials(): array
    {
        return [
            CloudflareCachePurger::class => \DI\autowire(CloudflareCachePurger::class)->constructor(static fn () => CredentialConfig::CloudflareCredential()),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)->constructor(static fn () => CredentialConfig::RedisCredential()),
        ];
    }
    private function dependencyService(): array
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
