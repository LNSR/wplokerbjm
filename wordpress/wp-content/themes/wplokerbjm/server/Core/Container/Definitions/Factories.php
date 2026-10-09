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

        return \array_merge(
            $this->getInstanceWithCredentials()
        );
    }

    //! For creds, defer via closure because if not CompiledContainer.php gonna expose your creds
    private function getInstanceWithCredentials(): array
    {
        return [
            CloudflareCachePurger::class => \DI\autowire(CloudflareCachePurger::class)->constructor(static fn () => CredentialConfig::CloudflareCredential()),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)->constructor(static fn () => CredentialConfig::RedisCredential()),
        ];
    }
}
