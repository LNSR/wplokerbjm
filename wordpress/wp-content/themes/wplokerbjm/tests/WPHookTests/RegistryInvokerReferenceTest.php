<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry;
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterLiveService;
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the ownership model behind the reactive self-clean registries:
 *
 * - an ACTIVE invoker is kept alive only by `$wp_filter`; the registries hold
 *   entries strongly but the entry's `handler` property is a `WeakReference`,
 *   and both `entriesByInvokers` and the janitor's entry map are `WeakMap`s
 *   keyed by the invoker (weak keys);
 * - therefore WordPress releasing the callback must free the invoker and run
 *   its destructor (`removeEntry(..., invokerDied: true)`);
 * - DEFERRED invokers are the deliberate exception: `DeferredHookEntryHandler`
 *   holds a strong reference so the entry survives until activation.
 *
 * If any of these regress, the registry starts leaking invokers (and their
 * destructor-driven cleanup never runs).
 *
 ** Note: No explicit \gc_collect_cycles() called. Circular reference may goes unnoticed.
 */
final class RegistryInvokerReferenceTest extends WplokerbjmTestCase
{
    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'class' => OnUnregisterService::class,
            'method' => 'onHook',
            'type' => 'action',
            'hook' => 'invoker_ref_hook',
            'priority' => 10,
            'acceptedArgs' => 0,
            'deferRegister' => false,
            'executeIf' => null,
            'executeIfParams' => [],
            'registerIf' => null,
            'registerIfParams' => [],
            'onUnregisterDo' => null,
            'onUnregisterDoParams' => [],
        ], $overrides);
    }

    public function testContainerRegistryDoesNotRetainActiveInvokers(): void
    {
        $registry = $this->createRegistry([$this->registration()]);
        $registry->initialize();

        $hook = $this->findRegisteredHook('action', 'invoker_ref_hook');
        self::assertNotNull($hook);

        $invoker = $hook['callable'];
        $priority = $hook['priority'];
        $reference = \WeakReference::create($invoker);

        // Simulate WordPress releasing the callback (remove_action, once
        // consumption, or $wp_filter teardown).
        \remove_action($hook['hook'], $invoker, $priority);

        unset($invoker, $hook);

        self::assertNull(
            $reference->get(),
            'the container registry must not keep an active invoker alive'
        );
    }

    public function testContainerRegistryDoesNotRetainActiveInvokersAfterRemoveAllActions(): void
    {
        $registry = $this->createRegistry([$this->registration()]);
        $registry->initialize();
        $hook = $this->findRegisteredHook('action', 'invoker_ref_hook');
        self::assertNotNull($hook);

        $reference = \WeakReference::create($hook['callable']);

        \remove_all_actions('invoker_ref_hook');

        unset($hook);

        self::assertNull(
            $reference->get(),
            'remove_all_actions must release the active invoker when no other strong references exist'
        );
    }

    public function testInstanceRegistryDoesNotRetainActiveInvokers(): void
    {
        $service = new OnUnregisterLiveService();
        $janitor = new QueuedHooksCleaner();
        $weakRef = \WeakReference::create($janitor);
        $registry = $this->container()->make(WPHooksInstanceRegistry::class, [
            'queuedHooksCleaner' => $janitor,
        ]);
        $registry->registerHooksOn($service);

        $hook = $this->findRegisteredHook('action', 'live_beta');
        self::assertNotNull($hook);

        $invoker = $hook['callable'];
        $priority = $hook['priority'];
        $reference = \WeakReference::create($invoker);

        \remove_action($hook['hook'], $invoker, $priority);

        unset($invoker, $hook);

        self::assertNull(
            $reference->get(),
            'the instance registry must not keep an active invoker alive'
        );
        self::assertInstanceOf(OnUnregisterLiveService::class, $service, 'the owner instance stays alive');
    }

    public function testOnceInvokerIsCollectableAfterTheQueuedSweep(): void
    {
        $registry = $this->createRegistry([
            $this->registration(['once' => true]),
        ]);
        $registry->initialize();

        $hook = $this->findRegisteredHook('action', 'invoker_ref_hook');
        self::assertNotNull($hook);

        $invoker = $hook['callable'];
        $reference = \WeakReference::create($invoker);

        \do_action('invoker_ref_hook');

        // Drive the queued-removal sweep: the invoker must leave $wp_filter.
        $this->registeredHooks();

        self::assertNull($this->findRegisteredHook('action', 'invoker_ref_hook'));

        unset($invoker, $hook);

        self::assertNull(
            $reference->get(),
            'a consumed once-invoker must be collectable once swept, not retained by the janitor'
        );
    }

    public function testDeferredInvokerIsRetainedUntilActivation(): void
    {
        $registry = $this->createRegistry([
            $this->registration([
                'deferRegisterUntilHook' => 'invoker_ref_trigger',
            ]),
        ]);
        $registry->initialize();

        self::assertNull($this->findRegisteredHook('action', 'invoker_ref_hook'), 'entry starts deferred');

        // A weakly-held deferred invoker would die here, and the trigger would
        // then have nothing to activate.

        \do_action('invoker_ref_trigger');

        self::assertNotNull(
            $this->findRegisteredHook('action', 'invoker_ref_hook'),
            'deferred entries must hold their invoker strongly until activation'
        );
    }
}
