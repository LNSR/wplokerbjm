<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\Container;
use DI\ContainerBuilder;
use WPLokerBJM\Core\Container\Support\WPHooks\{WPHookPlanProvider, WPHooksRegistry, WPHooksRuntimeRegistry, WPHooksScanner};
use WPLokerBJM\Tests\Support\Fixtures\{ChildNoRedeclareService, ChildRedeclareService, ParentHookService};
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Hook registration is declared-only: inherited #[Action] / #[Filter]
 * methods are deliberately excluded from scanning. A subclass opts in by
 * re-declaring the method with its own attribute (and `parent::` call).
 */
class InheritedHookTest extends WplokerbjmTestCase
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
            ParentHookService::class => \DI\autowire(),
            ChildNoRedeclareService::class => \DI\autowire(),
            ChildRedeclareService::class => \DI\autowire(),
        ]);
        $this->container = $builder->build();
        $this->planProvider = new WPHookPlanProvider();

        ParentHookService::reset();
        ChildNoRedeclareService::reset();
        ChildRedeclareService::reset();
    }

    /**
     * Scanner registrations filtered to a single fixture class.
     *
     * @return array<int, \WPLokerBJM\Core\Container\Support\WPHooks\HookRegistration>
     */
    private function registrationsFor(string $class, array $registrations): array
    {
        return array_values(array_filter(
            $registrations,
            static fn ($reg) => $reg->class === $class
        ));
    }

    public function testChildWithoutRedeclarationHasNoRegistration(): void
    {
        $scanner = new WPHooksScanner('WPLokerBJM\Tests\Support\Fixtures', '', $this->planProvider);
        $registrations = $scanner->getHookRegistrations();

        // Parent declares the hook — exactly one registration.
        $parentRegs = $this->registrationsFor(ParentHookService::class, $registrations);
        self::assertCount(1, $parentRegs);
        self::assertSame('parent_hook', $parentRegs[0]->hook);

        // Child that does NOT re-declare inherits nothing.
        self::assertSame([], $this->registrationsFor(ChildNoRedeclareService::class, $registrations));

        // Child that re-declares with its own attribute gets its own registration.
        $childRegs = $this->registrationsFor(ChildRedeclareService::class, $registrations);
        self::assertCount(1, $childRegs);
        self::assertSame('parent_hook', $childRegs[0]->hook);
        self::assertSame(ChildRedeclareService::class, $childRegs[0]->class);
    }

    public function testParentAndRedeclaringChildBothFire(): void
    {
        $scanner = new WPHooksScanner('WPLokerBJM\Tests\Support\Fixtures', '', $this->planProvider);
        $registrations = $scanner->getHookRegistrations();

        // Only the three inheritance fixtures exist in the container — the
        // registry registers them and skips everything else.
        $registry = new WPHooksRegistry($this->container, $registrations, $this->planProvider);
        $registry->initialize();

        do_action('parent_hook', 'ping');

        // Parent instance + re-declaring child instance both fired.
        self::assertSame(['ping', 'ping'], ParentHookService::$capturedValues);
        self::assertSame(2, ParentHookService::$instantiationCount);

        // (ChildNoRedeclareService never fires — its absence is proven at the
        // scanner level in testChildWithoutRedeclarationHasNoRegistration; its
        // instantiationCount is the shared inherited static of ParentHookService.)
    }

    public function testRuntimeRegistryIsDeclaredOnly(): void
    {
        $runtime = new WPHooksRuntimeRegistry();

        // Inherited parent hook is NOT registered on the child instance.
        $runtime->registerHooksOn(new ChildNoRedeclareService());
        self::assertNull($this->findRegisteredHook('action', 'parent_hook'));

        // A re-declared hook IS registered on the child instance.
        $runtime->registerHooksOn(new ChildRedeclareService());
        self::assertNotNull($this->findRegisteredHook('action', 'parent_hook'));

        do_action('parent_hook', 'runtime');
        self::assertSame(['runtime'], ParentHookService::$capturedValues);
    }
}
