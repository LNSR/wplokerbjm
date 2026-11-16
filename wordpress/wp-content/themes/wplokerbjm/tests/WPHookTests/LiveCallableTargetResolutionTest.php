<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\RuntimeWPHookProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\{
    ContainerRegistryHookTargetResolver,
    InstanceRegistryRuntimeHookResolver,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksInstanceObjectCache, WPHooksInstanceRegistry};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the shared callable-target resolution used by both registries.
 *
 * The runtime resolver must understand every callable shape the container
 * resolver does — array callables, first-class callables, PHP 8.4
 * property-hook closures, invokable objects (including anonymous classes and
 * ModuleClassHookMetadata hosts) — while mapping them onto a live instance
 * instead of a class-string.
 */
final class LiveCallableTargetResolutionTest extends WplokerbjmTestCase
{
    private InstanceRegistryRuntimeHookResolver $runtimeResolver;

    private ContainerRegistryHookTargetResolver $containerResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runtimeResolver = new InstanceRegistryRuntimeHookResolver();
        $this->containerResolver = new ContainerRegistryHookTargetResolver();
    }

    private function registry(): WPHooksInstanceRegistry
    {
        return $this->container()->make(WPHooksInstanceRegistry::class, [
            'provider' => $this->container()->get(RuntimeWPHookProvider::class),
            'cache' => new WPHooksInstanceObjectCache(),
            'queuedHooksCleaner' => $this->queuedHooksCleaner,
        ]);
    }

    // ── Runtime resolver: callable shapes ───────────────────────────────

    public function testResolvesArrayCallableToItsOwnerAndMember(): void
    {
        $service = new class {
            public function onInit(): void {}

            public $propertyTarget = null;
        };

        self::assertSame(
            [$service, 'onInit'],
            $this->runtimeResolver->resolveLiveCallableTarget([$service, 'onInit']),
        );

        self::assertSame(
            [$service, 'propertyTarget'],
            $this->runtimeResolver->resolveLiveCallableTarget([$service, 'propertyTarget']),
        );
    }

    public function testResolvesFirstClassCallableToItsOwnerAndMethod(): void
    {
        $service = new class {
            public function onInit(): void {}
        };

        self::assertSame(
            [$service, 'onInit'],
            $this->runtimeResolver->resolveLiveCallableTarget($service->onInit(...)),
        );
    }

    public function testResolvesInvokableObjectToItselfAndInvoke(): void
    {
        $invokable = new class {
            public function __invoke(): void {}
        };

        self::assertSame(
            [$invokable, '__invoke'],
            $this->runtimeResolver->resolveLiveCallableTarget($invokable),
        );
    }

    public function testResolvesModuleClassHookMetadataInstanceToItselfAndInvoke(): void
    {
        $module = new class('HostClass', 'hostProperty') extends ModuleClassHookMetadata {
            public function __invoke(): void {}
        };

        self::assertSame(
            [$module, '__invoke'],
            $this->runtimeResolver->resolveLiveCallableTarget($module),
        );
    }

    public function testPropertyHookClosureNameParsesToThePropertyName(): void
    {
        $parser = new class extends InstanceRegistryRuntimeHookResolver {
            public function parseMemberName(string $name): ?string
            {
                return $this->parsePropertyHookMemberName($name);
            }
        };

        self::assertSame(
            'optionActivePlugin',
            $parser->parseMemberName('{closure:WPLokerBJM\Host::$optionActivePlugin::get():42}'),
        );

        self::assertNull(
            $parser->parseMemberName('{closure:WPLokerBJM\Host::onInit():42}'),
        );
    }

    public function testRejectsClassStringTargetsBecauseTheyHaveNoLiveInstance(): void
    {
        self::assertSame(
            [null, null],
            $this->runtimeResolver->resolveLiveCallableTarget('Some\\Service::onInit'),
        );
    }

    public function testRejectsUnboundClosures(): void
    {
        self::assertSame(
            [null, null],
            $this->runtimeResolver->resolveLiveCallableTarget(static fn(): bool => true),
        );
    }

    public function testRejectsNonInvokableObjects(): void
    {
        self::assertSame(
            [null, null],
            $this->runtimeResolver->resolveLiveCallableTarget(new class {}),
        );
    }

    // ── Container resolver: unchanged public contract ───────────────────

    public function testContainerResolverKeepsHandlingEveryCallableShape(): void
    {
        $service = new class {
            public function onInit(): void {}
        };

        self::assertSame(
            [$service::class, 'onInit'],
            $this->containerResolver->resolve([$service, 'onInit']),
        );

        self::assertSame(
            ['WPLokerBJM\\Service', 'onInit'],
            $this->containerResolver->resolve('WPLokerBJM\\Service::onInit'),
        );

        self::assertSame(
            ['WPLokerBJM\\Service', '__invoke'],
            $this->containerResolver->resolve('WPLokerBJM\\Service'),
        );

        $invokable = new class {
            public function __invoke(): void {}
        };
        self::assertSame(
            [$invokable::class, '__invoke'],
            $this->containerResolver->resolve($invokable),
        );

        self::assertSame(
            [$service::class, 'onInit'],
            $this->containerResolver->resolve($service->onInit(...)),
        );
    }

    public function testContainerResolverMapsModuleClassMetadataToItsParentSite(): void
    {
        $module = new class('ParentHost', 'optionActivePlugin') extends ModuleClassHookMetadata {};

        self::assertSame(
            ['ParentHost', 'optionActivePlugin'],
            $this->containerResolver->resolve($module),
        );
    }

    // ── End-to-end: object callables on the runtime registry ────────────

    public function testInvokableObjectAttributeIsUnregisteredByCallable(): void
    {
        $service = new class {
            #[Action('live_invokable_hook', acceptedArgs: 0)]
            public function __invoke(): void {}
        };

        $registry = $this->registry();
        $registry->registerHooksOn($service);

        self::assertNotNull($this->findRegisteredHook('action', 'live_invokable_hook'));

        self::assertSame(1, $registry->unregisterByCallableInLiveInstance($service));
        self::assertNull($this->findRegisteredHook('action', 'live_invokable_hook'));
    }

    public function testInvokableObjectDeferredAttributeIsActivatedAndRemovedByCallable(): void
    {
        $service = new class {
            #[Action('live_invokable_deferred', deferRegister: true, acceptedArgs: 0)]
            public function __invoke(): void {}
        };

        $registry = $this->registry();
        $registry->registerHooksOn($service);

        self::assertNull($this->findRegisteredHook('action', 'live_invokable_deferred'));

        self::assertSame(1, $registry->activateDeferredByCallableInLiveInstance($service));
        self::assertNotNull($this->findRegisteredHook('action', 'live_invokable_deferred'));

        self::assertSame(1, $registry->unregisterByCallableInLiveInstance($service));
        self::assertNull($this->findRegisteredHook('action', 'live_invokable_deferred'));
    }

    public function testModuleClassInvokeAttributeIsRemovableByCallable(): void
    {
        $module = new class('RegistryHost', 'moduleProperty') extends ModuleClassHookMetadata {
            #[Action('live_module_hook', acceptedArgs: 0)]
            public function __invoke(): void {}
        };

        $registry = $this->registry();
        $registry->registerHooksOn($module);

        self::assertNotNull($this->findRegisteredHook('action', 'live_module_hook'));
        self::assertSame(1, $registry->unregisterByCallableInLiveInstance($module));
        self::assertNull($this->findRegisteredHook('action', 'live_module_hook'));
    }
}
