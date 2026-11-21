<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Registry;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the owner-scoped tag selectors on the runtime/instance path:
 * `activateDeferredByTagsInLiveInstance()`,
 * `unregisterDeferredByTagsInLiveInstance()`,
 * `unregisterByTagsInLiveInstance()` and their tag-pattern counterparts.
 *
 * Tags are resolved per live owner, so sibling instances with different
 * dynamic tag closures stay independent.
 */
final class InstanceRegistryTagSelectorTest extends WplokerbjmTestCase
{
    private WPHooksInstanceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'queuedHooksCleaner' => $this->queuedHooksCleaner = new QueuedHooksCleaner(),
        ]);
    }

    public function testActivateDeferredByTagsWakesOnlyMatchingOwnerAndTags(): void
    {
        $factory = static fn(): object => new class {
            public int $cache = 0;
            public int $seo = 0;
            public int $both = 0;

            #[Action('instance_tag_cache_hook', 10, deferRegister: true, tag: ['cache'])]
            public function onCache(): void
            {
                $this->cache++;
            }

            #[Action('instance_tag_seo_hook', 10, deferRegister: true, tag: ['seo'])]
            public function onSeo(): void
            {
                $this->seo++;
            }

            #[Action('instance_tag_both_hook', 10, deferRegister: true, tag: ['cache', 'seo'])]
            public function onBoth(): void
            {
                $this->both++;
            }
        };

        $first = $factory();
        $second = $factory();

        $this->registry->registerHooksOn($first);
        $this->registry->registerHooksOn($second);

        self::assertSame(
            2,
            $this->registry->activateDeferredByTagsInLiveInstance($first, ['cache']),
            'Tag matching is OR and deduplicates the entry carrying both tags.',
        );

        \do_action('instance_tag_cache_hook');
        \do_action('instance_tag_seo_hook');
        \do_action('instance_tag_both_hook');

        self::assertSame([1, 0, 1], [$first->cache, $first->seo, $first->both]);
        self::assertSame([0, 0, 0], [$second->cache, $second->seo, $second->both]);

        self::assertSame(
            2,
            $this->registry->activateDeferredByTagsInLiveInstance($second, ['cache']),
        );

        \do_action('instance_tag_cache_hook');
        \do_action('instance_tag_seo_hook');
        \do_action('instance_tag_both_hook');

        self::assertSame([2, 0, 2], [$first->cache, $first->seo, $first->both]);
        self::assertSame([1, 0, 1], [$second->cache, $second->seo, $second->both]);
    }

    public function testActivateDeferredByTagPatternWakesOnlyMatchingTags(): void
    {
        $instance = new class {
            public int $themeCache = 0;
            public int $themeSeo = 0;
            public int $other = 0;

            #[Action('instance_tag_pattern_theme_cache', 10, deferRegister: true, tag: ['theme_cache'])]
            public function onThemeCache(): void
            {
                $this->themeCache++;
            }

            #[Action('instance_tag_pattern_theme_seo', 10, deferRegister: true, tag: ['theme_seo'])]
            public function onThemeSeo(): void
            {
                $this->themeSeo++;
            }

            #[Action('instance_tag_pattern_other', 10, deferRegister: true, tag: ['plugin_cache'])]
            public function onOther(): void
            {
                $this->other++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            2,
            $this->registry->activateDeferredByTagPatternInLiveInstance($instance, ['theme_*']),
        );

        \do_action('instance_tag_pattern_theme_cache');
        \do_action('instance_tag_pattern_theme_seo');
        \do_action('instance_tag_pattern_other');

        self::assertSame([1, 1, 0], [$instance->themeCache, $instance->themeSeo, $instance->other]);
    }

    public function testUnregisterDeferredByTagsLeavesActiveAndUnrelatedDeferredEntries(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_tag_deferred_cache', 10, deferRegister: true, tag: ['cache'])]
            public function onDeferredCache(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_deferred_seo', 10, deferRegister: true, tag: ['seo'])]
            public function onDeferredSeo(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_active_cache', 10, tag: ['cache'])]
            public function onActiveCache(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            1,
            $this->registry->unregisterDeferredByTagsInLiveInstance($instance, ['cache']),
        );

        self::assertSame(
            0,
            $this->registry->activateDeferredByTagsInLiveInstance($instance, ['cache']),
            'The dropped deferred entry cannot activate later.',
        );
        self::assertSame(
            1,
            $this->registry->activateDeferredByTagsInLiveInstance($instance, ['seo']),
            'Deferred entries outside the tag set survive.',
        );
        self::assertNotNull(
            $this->findRegisteredHook('action', 'instance_tag_active_cache'),
            'Active registrations are untouched by the deferred tag selector.',
        );
    }

    public function testUnregisterByTagsLeavesDeferredAndUnrelatedActiveEntries(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_tag_active_cache', 10, tag: ['cache'])]
            public function onActiveCache(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_active_seo', 10, tag: ['seo'])]
            public function onActiveSeo(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_deferred_cache', 10, deferRegister: true, tag: ['cache'])]
            public function onDeferredCache(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            1,
            $this->registry->unregisterByTagsInLiveInstance($instance, ['cache']),
        );

        self::assertNull($this->findRegisteredHook('action', 'instance_tag_active_cache'));
        self::assertNotNull($this->findRegisteredHook('action', 'instance_tag_active_seo'));
        self::assertNull($this->findRegisteredHook('action', 'instance_tag_deferred_cache'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByTagsInLiveInstance($instance, ['cache']),
            'The active tag selector must not sweep deferred entries.',
        );
    }

    public function testUnregisterByTagPatternLeavesDeferredEntries(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_tag_pattern_active_cache', 10, tag: ['theme_cache'])]
            public function onActiveCache(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_pattern_active_seo', 10, tag: ['theme_seo'])]
            public function onActiveSeo(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_pattern_active_other', 10, tag: ['plugin_cache'])]
            public function onActiveOther(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_pattern_deferred_cache', 10, deferRegister: true, tag: ['theme_cache'])]
            public function onDeferredCache(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            2,
            $this->registry->unregisterByTagPatternInLiveInstance($instance, ['theme_*']),
        );

        self::assertNull($this->findRegisteredHook('action', 'instance_tag_pattern_active_cache'));
        self::assertNull($this->findRegisteredHook('action', 'instance_tag_pattern_active_seo'));
        self::assertNotNull($this->findRegisteredHook('action', 'instance_tag_pattern_active_other'));
        self::assertNull($this->findRegisteredHook('action', 'instance_tag_pattern_deferred_cache'));

        self::assertSame(
            1,
            $this->registry->activateDeferredByTagPatternInLiveInstance($instance, ['theme_*']),
            'The active tag-pattern selector must leave deferred entries alone.',
        );
    }

    public function testUnregisterDeferredByTagPatternDropsOnlyMatchingDeferredEntries(): void
    {
        $instance = new class {
            public int $runs = 0;

            #[Action('instance_tag_pattern_drop_cache', 10, deferRegister: true, tag: ['theme_cache'])]
            public function onDeferredCache(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_pattern_drop_seo', 10, deferRegister: true, tag: ['theme_seo'])]
            public function onDeferredSeo(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_pattern_drop_other', 10, deferRegister: true, tag: ['plugin_cache'])]
            public function onDeferredOther(): void
            {
                $this->runs++;
            }

            #[Action('instance_tag_pattern_drop_active', 10, tag: ['theme_cache'])]
            public function onActive(): void
            {
                $this->runs++;
            }
        };

        $this->registry->registerHooksOn($instance);

        self::assertSame(
            2,
            $this->registry->unregisterDeferredByTagPatternInLiveInstance($instance, ['theme_*']),
        );

        self::assertSame(
            0,
            $this->registry->activateDeferredByTagsInLiveInstance($instance, ['theme_cache']),
        );
        self::assertSame(
            0,
            $this->registry->activateDeferredByTagsInLiveInstance($instance, ['theme_seo']),
        );
        self::assertSame(
            1,
            $this->registry->activateDeferredByTagsInLiveInstance($instance, ['plugin_cache']),
            'Deferred entries outside the pattern survive.',
        );
        self::assertNotNull(
            $this->findRegisteredHook('action', 'instance_tag_pattern_drop_active'),
            'Active registrations are untouched by the deferred tag-pattern selector.',
        );
    }

    public function testTagClosureResolvesPerInstanceAndDoesNotLeakBetweenSiblings(): void
    {
        $factory = static fn(array $tags): object => new class ($tags) {
            public function __construct(public array $tags) {}

            public int $runs = 0;

            #[Action(
                'instance_tag_dynamic_hook',
                10,
                deferRegister: true,
                tag: static function (?self $self): array {
                    return $self === null ? [] : $self->tags;
                },
            )]
            public function onDynamicTag(): void
            {
                $this->runs++;
            }
        };

        $first = $factory(['alpha']);
        $second = $factory(['beta']);

        $this->registry->registerHooksOn($first);
        $this->registry->registerHooksOn($second);

        self::assertSame(
            1,
            $this->registry->activateDeferredByTagsInLiveInstance($first, ['alpha']),
        );
        self::assertSame(
            0,
            $this->registry->activateDeferredByTagsInLiveInstance($second, ['alpha']),
            'The first instance\'s dynamic tag must not leak into its sibling.',
        );
        self::assertSame(
            1,
            $this->registry->activateDeferredByTagsInLiveInstance($second, ['beta']),
        );

        \do_action('instance_tag_dynamic_hook');
        self::assertSame([1, 1], [$first->runs, $second->runs]);
    }

    public function testCachedTagCallableResolvesPerInstance(): void
    {
        $cache = new WPHooksInstanceObjectCache();

        $factory = static fn(array $tags): ModuleHookMetadata => new class ($tags) extends ModuleHookMetadata {
            public function __construct(public array $tags)
            {
                parent::__construct('CachedTagHost', 'hooks');
            }

            public int $runs = 0;

            #[Action(
                'instance_tag_cached_dynamic_hook',
                10,
                deferRegister: true,
                tag: static function (?self $self): array {
                    return $self === null ? [] : $self->tags;
                },
            )]
            public function onDynamicTag(): void
            {
                $this->runs++;
            }
        };

        $registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'cache' => $cache,
            'queuedHooksCleaner' => $this->queuedHooksCleaner,
        ]);

        $first = $factory(['alpha']);
        $registry->registerHooksOn($first);

        $second = $factory(['beta']);
        $registry->registerHooksOn($second);

        self::assertSame(1, $registry->activateDeferredByTagsInLiveInstance($first, ['alpha']));
        self::assertSame(0, $registry->activateDeferredByTagsInLiveInstance($second, ['alpha']));
        self::assertSame(1, $registry->activateDeferredByTagsInLiveInstance($second, ['beta']));

        \do_action('instance_tag_cached_dynamic_hook');
        self::assertSame([1, 1], [$first->runs, $second->runs]);
    }
}
