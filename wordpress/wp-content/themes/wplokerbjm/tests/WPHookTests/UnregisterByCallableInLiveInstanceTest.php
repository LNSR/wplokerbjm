<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry;
use WPLokerBJM\Tests\Support\Fixtures\OnUnregisterLiveService;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins the live-instance callable unregistration API:
 * `WPHooksInstanceRegistry::unregisterByCallableInLiveInstance()`.
 *
 * The API receives `[$liveInstance, 'member']` or a first-class callable bound
 * to that instance and must remove every registration of that member (all
 * priorities/attributes) without touching siblings or other instances — and
 * without leaving deferred entries that could resurrect the hook later.
 */
final class UnregisterByCallableInLiveInstanceTest extends WplokerbjmTestCase
{
    private function instanceRegistry(): WPHooksInstanceRegistry
    {
        $janitor = new QueuedHooksCleaner();
        $ref = \WeakReference::create($janitor);
        return $this->container()->make(WPHooksInstanceRegistry::class, [
            'queuedHooksCleaner' => $ref->get(),
        ]);
    }

    /** @return list<int> */
    private function hookPriorities(string $hook): array
    {
        $priorities = [];

        foreach ($this->registeredHooks() as $entry) {
            if ($entry['hook'] === $hook) {
                $priorities[] = $entry['priority'];
            }
        }

        \sort($priorities);

        return $priorities;
    }

    public function testRemovesEveryPriorityOfTheTargetedMethodOnly(): void
    {
        $service = new OnUnregisterLiveService();
        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertSame([10, 20], $this->hookPriorities('live_alpha'));
        self::assertSame([10], $this->hookPriorities('live_beta'));
        self::assertSame([10], $this->hookPriorities('live_prop'));

        self::assertSame(2, $registry->unregisterByCallableInLiveInstance([$service, 'onAlpha']));

        self::assertSame([], $this->hookPriorities('live_alpha'));
        self::assertSame([10], $this->hookPriorities('live_beta'), 'sibling method must survive');
        self::assertSame([10], $this->hookPriorities('live_prop'), 'sibling property must survive');
    }

    public function testAcceptsAFirstClassCallableBoundToTheLiveInstance(): void
    {
        $service = new OnUnregisterLiveService();
        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertSame(2, $registry->unregisterByCallableInLiveInstance($service->onAlpha(...)));

        self::assertSame([], $this->hookPriorities('live_alpha'));
    }

    public function testPropertyHookIsTargetedByPropertyName(): void
    {
        $service = new OnUnregisterLiveService();
        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertSame(1, $registry->unregisterByCallableInLiveInstance([$service, 'propTarget']));

        self::assertSame([], $this->hookPriorities('live_prop'));
        self::assertSame([10, 20], $this->hookPriorities('live_alpha'));
    }

    public function testDropsDeferredEntrySoItCannotResurrect(): void
    {
        $service = new OnUnregisterLiveService();
        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertSame([], $this->hookPriorities('live_deferred'), 'entry starts deferred');

        self::assertSame(1, $registry->unregisterByCallableInLiveInstance([$service, 'onDeferred']));

        \do_action('live_trigger');

        self::assertSame([], $this->hookPriorities('live_deferred'), 'deferred entry must not activate after removal');
    }

    public function testOnlyTargetsTheGivenLiveInstance(): void
    {
        $first = new OnUnregisterLiveService();
        $second = new OnUnregisterLiveService();

        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($first);
        $registry->registerHooksOn($second);

        self::assertSame([10, 10, 20, 20], $this->hookPriorities('live_alpha'));

        self::assertSame(2, $registry->unregisterByCallableInLiveInstance([$first, 'onAlpha']));

        self::assertSame([10, 20], $this->hookPriorities('live_alpha'), 'the other instance must keep its registrations');
    }

    public function testMemberMatchingIsCaseInsensitive(): void
    {
        $service = new OnUnregisterLiveService();
        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertSame(2, $registry->unregisterByCallableInLiveInstance([$service, 'ONALPHA']));
        self::assertSame([], $this->hookPriorities('live_alpha'));
    }

    public function testUnknownOrInvalidTargetReturnsZero(): void
    {
        $service = new OnUnregisterLiveService();
        $registry = $this->instanceRegistry();
        $registry->registerHooksOn($service);

        self::assertSame(0, $registry->unregisterByCallableInLiveInstance([$service, 'missingMember']));
        self::assertSame(0, $registry->unregisterByCallableInLiveInstance(['not-an-object', 'onAlpha']));
    }
}
