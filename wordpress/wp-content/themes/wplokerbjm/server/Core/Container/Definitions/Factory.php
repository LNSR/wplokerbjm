<?php

namespace WPLokerBJM\Core\Container\Definitions;
use WPLokerBJM\Core\Container\Support\{WPhooksScanner};
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Configs\CredentialConfig;

/**
 * Factory definitions — Manually define your arguments here.
 */
class Factory implements DefinitionProviderInterface
{
    public static function getDefinitions(): array
    {
        
        return [
            WPhooksScanner::class => \DI\create(WPhooksScanner::class)
                ->constructor(
                    static fn() => get_stylesheet_directory() . '/server',
                    'WPLokerBJM'
                ),
            ...self::getInstanceWithCredentials(),
        ];
    }

    //! For creds, defer via closure because if not CompiledContainer.php gonna expose your creds
    private static function getInstanceWithCredentials(): array
    {
        return [
            Cloudflare::class => \DI\autowire(Cloudflare::class)->constructor(static fn() => CredentialConfig::CloudflareCredential())->lazy(),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)
                ->constructor(static fn() => CredentialConfig::RedisCredential())
                ->lazy(),
        ];
    }
}
