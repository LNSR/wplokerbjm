<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use Closure;
use DI\ContainerBuilder;
use DI\Container;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredHookManager;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfActionService;

/**
 * Test suite for the `tag` grouping metadata on hook registrations.
 *
 * Verifies that:
 *  - A tagged hook without defer registers immediately and can be bulk
 *    unregistered from the active pool.
 *  - A tagged hook with defer lands in the deferred pool.
 *  - activateDeferredByTags activates only the matching tag group.
 *  - A hook with multiple tags is matched by any of them.
 *  - String and array tag forms are equivalent.
 *  - unregisterByTags only touches the active pool.
 *  - unregisterDeferredByTags only touches the deferred pool.
 *  - Unknown/empty tag sets are clean no-ops.
 *  - Tags compose with the condition gate: activation does not bypass it.
 */
class TaggedHookTest extends WplokerbjmTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions([
            ExecuteIfActionService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();

        ExecuteIfActionService::reset();
    }

    public function testTaggedHookWithoutDeferRegistersImmediately(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_active',
                tags: ['cache'],
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'tag_active'));

        do_action('tag_active', 'hello');
        $this->assertSame(1, ExecuteIfActionService::$instantiationCount);
        $this->assertSame(['hello'], ExecuteIfActionService::$capturedValues);
    }

    public function testUnregisterByTagsRemovesActiveButLeavesDeferredIntact(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_active',
                tags: ['cache'],
            ),
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_deferred',
                tags: ['cache'],
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $this->assertNotNull($this->findRegisteredHook('action', 'tag_active'));
        $this->assertNull($this->findRegisteredHook('action', 'tag_deferred'));

        $registry->unregisterByTags(['cache']);

        // Active pool swept...
        $this->assertNull($this->findRegisteredHook('action', 'tag_active'));

        // ...deferred pool untouched — still activatable.
        $registry->activateDeferredByHook('tag_deferred');
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_deferred'));

        do_action('tag_deferred', 'still-here');
        $this->assertSame(1, ExecuteIfActionService::$instantiationCount);
        $this->assertSame(['still-here'], ExecuteIfActionService::$capturedValues);
    }

    public function testUnregisterDeferredByTagsRemovesDeferredButLeavesActiveIntact(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_active',
                tags: ['seo'],
            ),
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_deferred',
                tags: ['seo'],
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $registry->unregisterDeferredByTags(['seo']);

        // Deferred pool swept — no longer activatable.
        $registry->activateDeferredByHook('tag_deferred');
        $this->assertNull($this->findRegisteredHook('action', 'tag_deferred'));

        // Active pool untouched.
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_active'));

        do_action('tag_active', 'hello');
        $this->assertSame(1, ExecuteIfActionService::$instantiationCount);
    }

    public function testTaggedHookWithDeferLandsInDeferredPool(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_lazy',
                tags: ['cache'],
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $this->assertNull(
            $this->findRegisteredHook('action', 'tag_lazy'),
            'Deferred tagged hook must NOT be registered during initialize()',
        );

        $registry->activateDeferredByTags(['cache']);

        $this->assertNotNull($this->findRegisteredHook('action', 'tag_lazy'));

        do_action('tag_lazy', 'hello');
        $this->assertSame(['hello'], ExecuteIfActionService::$capturedValues);
    }

    public function testActivateDeferredByTagsActivatesOnlyMatchingGroup(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_cache',
                tags: ['cache'],
                defer: true,
            ),
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_seo',
                tags: ['seo'],
                defer: true,
            ),
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_none',
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $activated = $registry->activateDeferredByTags(['cache']);

        $this->assertSame(1, $activated);
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_cache'));
        $this->assertNull($this->findRegisteredHook('action', 'tag_seo'));
        $this->assertNull($this->findRegisteredHook('action', 'tag_none'));

        // Second activation run: only the remaining seo group is affected —
        // the already-active cache entry is skipped, not double-registered.
        $activated = $registry->activateDeferredByTags(['cache', 'seo']);
        $this->assertSame(1, $activated);
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_seo'));
        $this->assertNull($this->findRegisteredHook('action', 'tag_none'));
    }

    public function testMultiTagHookIsMatchedByAnyTag(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_multi',
                tags: ['cache', 'seo'],
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $this->assertSame(1, $registry->activateDeferredByTags(['seo']));
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_multi'));
    }

    public function testStringAndArrayTagFormsAreEquivalent(): void
    {
        // String form.
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_str',
                tags: ['cache'],
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();
        $this->assertSame(1, $registry->activateDeferredByTags(['cache']));

        // Array form — identical behavior.
        ExecuteIfActionService::reset();
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_arr',
                tags: ['cache'],
                defer: true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();
        $this->assertSame(1, $registry->activateDeferredByTags(['cache']));
    }

    public function testUnknownOrEmptyTagsAreNoOps(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_keep',
                tags: ['cache'],
                defer: true,
            ),
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_keep_active',
                tags: ['cache'],
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        // Unknown tag: nothing activated, nothing unregistered.
        $this->assertSame(0, $registry->activateDeferredByTags(['nope']));
        $this->assertNull($this->findRegisteredHook('action', 'tag_keep'));

        $registry->unregisterByTags(['nope']);
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_keep_active'));

        // Empty array: clean no-op as well.
        $this->assertSame(0, $registry->activateDeferredByTags([]));
        $registry->unregisterByTags([]);
        $registry->unregisterDeferredByTags([]);

        $this->assertNotNull($this->findRegisteredHook('action', 'tag_keep_active'));
    }

    public function testTagComposesWithConditionGate(): void
    {
        // Tagged + deferred + condition false: activation registers the hook,
        // but the condition gate still suppresses every fire.
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_cond',
                tags: ['cache'],
                defer: true,
                executeIf: static fn (ContainerInterface $c): bool => false,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $registry->activateDeferredByTags(['cache']);
        $this->assertNotNull($this->findRegisteredHook('action', 'tag_cond'));

        do_action('tag_cond', 'hello');

        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);

        // Condition true: fires normally after tag activation.
        ExecuteIfActionService::reset();
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_cond2',
                tags: ['seo'],
                defer: true,
                executeIf: static fn (ContainerInterface $c): bool => true,
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        $registry->activateDeferredByTags(['seo']);
        do_action('tag_cond2', 'gated');

        $this->assertSame(1, ExecuteIfActionService::$instantiationCount);
        $this->assertSame(['gated'], ExecuteIfActionService::$capturedValues);
    }

    public function testUnregisterByTagsUnregistersActiveHandlers(): void
    {
        $registry = new WPHooksContainerRegistry($this->container, [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'tag_bulk',
                tags: ['cache'],
            ),
        ], new WPHookPlanProvider(), new DeferredHookManager(new WPHookPlanProvider(), $this->container));
        $registry->initialize();

        do_action('tag_bulk', 'before');
        $this->assertSame(['before'], ExecuteIfActionService::$capturedValues);

        ExecuteIfActionService::reset();

        $registry->unregisterByTags(['cache']);
        $this->assertNull($this->findRegisteredHook('action', 'tag_bulk'));

        do_action('tag_bulk', 'after');
        $this->assertSame(0, ExecuteIfActionService::$instantiationCount);
        $this->assertSame([], ExecuteIfActionService::$capturedValues);
    }

    /**
     * @return array<string,mixed>
     */
    private function action(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        array $tags = [],
        ?Closure $executeIf = null,
        bool $defer = false,
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer' => $defer,
            'execute_if' => $executeIf,
            'execute_if_params' => [],
            'tags' => $tags,
        ];
    }
}
