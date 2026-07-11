<?php
namespace WPLokerBJM\Core\Container\Definitions;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\{WPhooksScanner, WPHooksRegistry, AutowireScanner};
use WPLokerBJM\Services\WebHooks\Cloudflare;
use WPLokerBJM\Adapter\RedisAdapter;
use WPLokerBJM\Configs\CredentialConfig;


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
 * such as the WPHooksRegistry and Init services.
 *
 * How it works:
 * 1. WPhooksScanner scans the server/ directory for #[Action] and #[Filter] attributes.
 * 2. WPHooksRegistry receives the scanner results and pre-builds LazyHookHandler instances
 *    (named invocable objects that defer container resolution to hook-fire time).
 * 3. Init delegates to WPHooksRegistry::initialize() which registers hooks with WordPress
 *    via add_action/add_filter using the stored handler instances.
 *
 * The named LazyHookHandler objects enable hook unregistration by class/method key,
 * unlike anonymous closures which cannot be unregistered.
 *
 * @see \WPLokerBJM\Core\Container\Support\WPHooksRegistry
 * @see \WPLokerBJM\Core\Container\Support\LazyHookHandler
 * @see \WPLokerBJM\Core\Container\Init
 * @see \WPLokerBJM\Core\Container\Support\WPhooksScanner
 */
class Core implements DefinitionProviderInterface
{
    public static function getDefinitions(): array
    {
        $args = [
            get_stylesheet_directory() . '/server',
            'WPLokerBJM',
        ];

        $scanner = new AutowireScanner(...$args);
        $hookScanner = new WPhooksScanner(...$args);
        $autoWiredDefinitions = $scanner->scanForAutowirableClasses();
        $registrationHooks = $hookScanner->getHookRegistrations();

        $core = [
            WPHooksRegistry::class => \DI\autowire(WPHooksRegistry::class)
                ->constructor(
                    \DI\get(ContainerInterface::class),
                    $registrationHooks
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
            Cloudflare::class => \DI\autowire(Cloudflare::class)->constructor(static fn() => CredentialConfig::CloudflareCredential())->lazy(),
            RedisAdapter::class => \DI\autowire(RedisAdapter::class)->constructor(static fn() => CredentialConfig::RedisCredential())->lazy(),
        ];
    }
}
