<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\Container;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Tests\Support\Fixtures\ExecuteIfActionService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

enum DynamicTag: string
{
    case Cache = 'cache';
    case Seo = 'seo';
}

/**
 * The `tag` attribute is EITHER a static array of strings OR a single
 * closure returning the full resolved tag list (array<int, string>).
 * The closure is resolved at registration time via the plan provider;
 * ByTags consumers match the RESOLVED strings.
 */
class DynamicTagTest extends WplokerbjmTestCase
{
    private Container $container;

    private WPHookPlanProvider $planProvider;

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
        $this->planProvider = new WPHookPlanProvider();

        ExecuteIfActionService::reset();
    }

    public function testTagCallableResolvesAtRegistrationAndByTagsMatch(): void
    {
        $tagCallable = static fn (): array => ['dynamic-cache', 'seo'];

        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'dynamic_tag_active',
                tagCallable: $tagCallable,
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('dynamic_tag_active', 'hello');
        self::assertSame(['hello'], ExecuteIfActionService::$capturedValues);

        // Matches the RESOLVED tag strings.
        $registry->unregisterByTags(['dynamic-cache']);
        self::assertNull($this->findRegisteredHook('action', 'dynamic_tag_active'));
    }

    public function testDeferredTagCallableActivatesByResolvedTag(): void
    {
        $tagCallable = static function (ContainerInterface $c): array {
            return ['dynamic-deferred-' . ($c->has(ExecuteIfActionService::class) ? 'yes' : 'no')];
        };

        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'dynamic_tag_deferred',
                deferRegister: true,
                tagCallable: $tagCallable,
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'dynamic_tag_deferred'));

        $activated = $registry->activateDeferredByTags(['dynamic-deferred-yes']);
        self::assertSame(1, $activated);
        self::assertNotNull($this->findRegisteredHook('action', 'dynamic_tag_deferred'));
    }

    public function testNonArrayTagCallableResultSkipsHook(): void
    {
        $tagCallable = static fn (): string => 'x';

        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'dynamic_tag_bad',
                tagCallable: $tagCallable,
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'dynamic_tag_bad'));
        self::assertSame([], $this->registeredHooks());
    }

    public function testStaticArrayTagFormStillWorks(): void
    {
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'static_tag_active',
                tags: ['static-one', 'static-two'],
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        $registry->unregisterByTags(['static-one']);
        self::assertNull($this->findRegisteredHook('action', 'static_tag_active'));
    }

    public function testInvalidTagTupleSkipsHook(): void
    {
        // null and int are not string|BackedEnum → RuntimeException → skipped.
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'bad_tuple_tag',
                tags: ['cache', null, 123],
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'bad_tuple_tag'));
        self::assertSame([], $this->registeredHooks());
    }

    public function testEnumTagNormalizesToValue(): void
    {
        // Enum case + identical string literal → both normalize to 'cache'.
        $registrations = [
            $this->action(
                ExecuteIfActionService::class,
                'onExecuteIfAction',
                'enum_tag_active',
                tags: [DynamicTag::Cache, 'cache'],
            ),
        ];

        $registry = $this->createRegistry($registrations, $this->container);
        $registry->initialize();

        do_action('enum_tag_active', 'enum');
        self::assertSame(['enum'], ExecuteIfActionService::$capturedValues);

        // Query side accepts the enum too — normalizes to the same value.
        $registry->unregisterByTags([DynamicTag::Cache]);
        self::assertNull($this->findRegisteredHook('action', 'enum_tag_active'));
    }

    private function action(
        string $class,
        string $method,
        string $hook,
        int $priority = 10,
        int $acceptedArgs = 1,
        bool $deferRegister = false,
        array $tags = [],
        ?\Closure $tagCallable = null
    ): array {
        return [
            'class' => $class,
            'method' => $method,
            'type' => 'action',
            'hook' => $hook,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
            'defer_register' => $deferRegister,
            'execute_if' => null,
            'execute_if_params' => [],
            'register_if' => null,
            'register_if_params' => [],
            'hook_params' => [],
            'hook_args' => [],
            'tags' => $tags,
            'tag_callable' => $tagCallable,
            'tag_callable_params' => $tagCallable !== null
                ? $this->planProvider->buildCallablePlan($tagCallable)
                : [],
        ];
    }
}
