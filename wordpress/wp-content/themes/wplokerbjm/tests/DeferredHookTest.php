<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use WPLokerBJM\Core\Container\Support\WPHooksRegistry;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Test suite for deferred hook activation via #[Action]/#[Filter] attributes
 * with defer: true.
 *
 * Verifies that:
 *  - Deferred hooks are NOT registered with WordPress during initialize().
 *  - Deferred hooks are stored in $deferredHandlers internally.
 *  - activateDeferredByHook registers all deferred handlers for a hook.
 *  - activateDeferredByClass registers all deferred handlers for a class.
 *  - activateDeferredByMethod registers a specific deferred handler.
 *  - Activating an already-active deferred handler is safe (no double registration).
 *  - Deferred and non-deferred hooks coexist correctly.
 *  - unregisterDeferredByHook removes deferred handlers for a hook.
 *  - unregisterDeferredByClass removes deferred handlers for a class.
 *  - unregisterDeferredByMethod removes a specific deferred handler.
 *  - unregisterDeferredBy* with nonexistent targets are safe no-ops.
 *  - unregisterDeferredBy* does not affect active (non-deferred) handlers.
 */
class DeferredHookTest extends WplokerbjmTestCase
{
    private ContainerInterface $container;
    private WPHooksRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $this->container = $builder->build();

        $this->registry = new WPHooksRegistry($this->container, []);
    }

    /**
     * Seed deferred and non-deferred hook registrations, call registerAll
     * via reflection (bypassing container check), then verify internal state.
     *
     * @param array<int, array{class: string, method: string, type: 'action'|'filter', hook: string, priority: int, accepted_args: int, defer: bool}> $registrations
     */
    private function seedRegistrations(array $registrations): void
    {
        // Call registerAll via reflection to bypass the container existence check
        $ref = new ReflectionClass($this->registry);
        $method = $ref->getMethod('registerAll');
        $method->invoke($this->registry, $registrations);
    }

    /**
     * Read a private property from the registry.
     *
     * @return mixed
     */
    private function getRegistryProperty(string $name): mixed
    {
        $ref = new ReflectionClass($this->registry);
        $prop = $ref->getProperty($name);
        return $prop->getValue($this->registry);
    }

    /**
     * Count items in the deferred handlers map.
     */
    private function deferredHandlersCount(): int
    {
        $deferred = $this->getRegistryProperty('deferredHandlers');
        $count = 0;
        foreach ($deferred as $hookHandlers) {
            $count += count($hookHandlers);
        }
        return $count;
    }

    public function testDeferredHooksNotRegisteredOnInitialize(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred',
                'type'          => 'action',
                'hook'          => 'deferred_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->initialize();

        // Deferred hook should NOT be in the WordPress registry
        $this->assertNull(
            $this->findRegisteredHook('action', 'deferred_hook'),
            'Deferred hook should not be registered after initialize()',
        );
    }

    public function testNonDeferredHooksAreRegisteredOnInitialize(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyNormal',
                'type'          => 'action',
                'hook'          => 'normal_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => false,
            ],
        ]);

        $this->registry->initialize();

        // Non-deferred hook should be in the WordPress registry
        $this->assertNotNull(
            $this->findRegisteredHook('action', 'normal_hook'),
            'Non-deferred hook should be registered after initialize()',
        );
    }

    public function testDeferredHooksStoredInDeferredHandlers(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred',
                'type'          => 'filter',
                'hook'          => 'deferred_filter',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->assertSame(
            1,
            $this->deferredHandlersCount(),
            'Deferred hook should be stored in deferredHandlers',
        );
    }

    public function testActivateDeferredByHook(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred1',
                'type'          => 'action',
                'hook'          => 'shared_hook',
                'priority'      => 20,
                'accepted_args' => 2,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred2',
                'type'          => 'action',
                'hook'          => 'shared_hook',
                'priority'      => 15,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->activateDeferredByHook('shared_hook');

        // Both handlers should now be registered with WordPress
        $all = $this->findAllRegisteredHooks('action', 'shared_hook');
        $this->assertCount(2, $all, 'Both deferred handlers should be activated');
    }

    public function testActivateDeferredByHookNonExistentIsNoOp(): void
    {
        $this->seedRegistrations([]);

        // Should not throw or error
        $this->registry->activateDeferredByHook('nonexistent_hook');

        $this->assertNull(
            $this->findRegisteredHook('action', 'nonexistent_hook'),
            'No-op for nonexistent hook',
        );
    }

    public function testActivateDeferredByClass(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferredA',
                'type'          => 'filter',
                'hook'          => 'filter_one',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferredB',
                'type'          => 'action',
                'hook'          => 'action_two',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->activateDeferredByClass(self::class);

        // Both handlers across different hooks should be activated
        $this->assertNotNull(
            $this->findRegisteredHook('filter', 'filter_one'),
            'Deferred filter should be activated by class',
        );
        $this->assertNotNull(
            $this->findRegisteredHook('action', 'action_two'),
            'Deferred action should be activated by class',
        );
    }

    public function testActivateDeferredByMethod(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferredM',
                'type'          => 'action',
                'hook'          => 'specific_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->activateDeferredByMethod(self::class, 'dummyDeferredM');

        $this->assertNotNull(
            $this->findRegisteredHook('action', 'specific_hook'),
            'Deferred hook should be activated by method',
        );
    }

    public function testActivateAlreadyActiveDeferredIsSafe(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDup',
                'type'          => 'action',
                'hook'          => 'dup_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        // First activation
        $this->registry->activateDeferredByHook('dup_hook');
        $beforeCount = count($this->findAllRegisteredHooks('action', 'dup_hook'));

        // Second activation — should not double-register
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDup',
                'type'          => 'action',
                'hook'          => 'dup_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);
        $this->registry->activateDeferredByHook('dup_hook');

        $afterCount = count($this->findAllRegisteredHooks('action', 'dup_hook'));
        $this->assertSame(
            $beforeCount,
            $afterCount,
            'Activating an already-active deferred handler should not double-register',
        );
    }

    public function testDeferredAndNonDeferredCoexist(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyNormal',
                'type'          => 'action',
                'hook'          => 'mixed_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => false,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred',
                'type'          => 'action',
                'hook'          => 'mixed_hook',
                'priority'      => 20,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->initialize();

        // Non-deferred hook should already be registered
        $this->assertNotNull(
            $this->findRegisteredHook('action', 'mixed_hook'),
            'Non-deferred hook should be registered after initialize()',
        );

        $beforeCount = count($this->findAllRegisteredHooks('action', 'mixed_hook'));

        // Activate the deferred one
        $this->registry->activateDeferredByMethod(self::class, 'dummyDeferred');

        $afterCount = count($this->findAllRegisteredHooks('action', 'mixed_hook'));
        $this->assertSame(
            $beforeCount + 1,
            $afterCount,
            'Activating deferred handler should add one more to the hook',
        );
    }

    public function testUnregisterDeferredByHook(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred1',
                'type'          => 'action',
                'hook'          => 'purge_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred2',
                'type'          => 'action',
                'hook'          => 'purge_hook',
                'priority'      => 20,
                'accepted_args' => 2,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred3',
                'type'          => 'filter',
                'hook'          => 'other_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->assertSame(3, $this->deferredHandlersCount(), 'Should start with 3 deferred handlers');

        $this->registry->unregisterDeferredByHook('purge_hook');

        $this->assertSame(
            1,
            $this->deferredHandlersCount(),
            'Only purge_hook deferred handlers should be removed; other_hook should remain',
        );
    }

    public function testUnregisterDeferredByHookNonExistentIsNoOp(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred',
                'type'          => 'action',
                'hook'          => 'real_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->unregisterDeferredByHook('nonexistent_hook');

        $this->assertSame(
            1,
            $this->deferredHandlersCount(),
            'No-op should not affect existing deferred handlers',
        );
    }

    public function testUnregisterDeferredByClass(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferredA',
                'type'          => 'action',
                'hook'          => 'hook_a',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferredB',
                'type'          => 'filter',
                'hook'          => 'hook_b',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferredC',
                'type'          => 'action',
                'hook'          => 'hook_c',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->assertSame(3, $this->deferredHandlersCount(), 'Should start with 3 deferred handlers');

        $this->registry->unregisterDeferredByClass(self::class);

        $this->assertSame(
            0,
            $this->deferredHandlersCount(),
            'All deferred handlers for self::class should be removed',
        );
    }

    public function testUnregisterDeferredByMethod(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyKeep',
                'type'          => 'action',
                'hook'          => 'shared_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyRemove',
                'type'          => 'action',
                'hook'          => 'shared_hook',
                'priority'      => 20,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->assertSame(2, $this->deferredHandlersCount());

        $this->registry->unregisterDeferredByMethod(self::class, 'dummyRemove');

        $this->assertSame(
            1,
            $this->deferredHandlersCount(),
            'Only the specific method should be removed; sibling method should remain',
        );
    }

    public function testUnregisterDeferredByMethodNonExistentIsNoOp(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred',
                'type'          => 'action',
                'hook'          => 'real_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->unregisterDeferredByMethod(self::class, 'nonexistentMethod');

        $this->assertSame(
            1,
            $this->deferredHandlersCount(),
            'No-op should not affect existing deferred handlers',
        );
    }

    public function testUnregisterDeferredDoesNotAffectActiveHandlers(): void
    {
        $this->seedRegistrations([
            [
                'class'         => self::class,
                'method'        => 'dummyNormal',
                'type'          => 'action',
                'hook'          => 'mixed_hook',
                'priority'      => 10,
                'accepted_args' => 1,
                'defer'         => false,
            ],
            [
                'class'         => self::class,
                'method'        => 'dummyDeferred',
                'type'          => 'action',
                'hook'          => 'mixed_hook',
                'priority'      => 20,
                'accepted_args' => 1,
                'defer'         => true,
            ],
        ]);

        $this->registry->initialize();

        // Non-deferred hook should be registered
        $this->assertNotNull(
            $this->findRegisteredHook('action', 'mixed_hook'),
            'Non-deferred hook should be registered after initialize()',
        );

        $this->registry->unregisterDeferredByHook('mixed_hook');

        // Non-deferred handler should NOT be affected
        $this->assertNotNull(
            $this->findRegisteredHook('action', 'mixed_hook'),
            'Non-deferred handler should survive unregisterDeferredByHook',
        );

        // Deferred should be gone from internal store
        $this->assertSame(
            0,
            $this->deferredHandlersCount(),
            'All deferred handlers for mixed_hook should be removed',
        );
    }

    /**
     * Find all registered hooks matching type and hook name.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findAllRegisteredHooks(string $type, string $hook): array
    {
        return array_values(array_filter(
            $this->registeredHooks(),
            fn(array $reg): bool => $reg['type'] === $type && $reg['hook'] === $hook,
        ));
    }
}
