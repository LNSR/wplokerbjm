<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Regression guard for stacked attributes on one method that resolve to the
 * same hook + priority + accepted args (only `once` / `tag` differ).
 *
 * Before `HookKey::$attributeIndex` existed, all of them produced the same
 * unique key (`hook:class::method::target::type::priority::acceptedArgs`).
 * WordPress still dispatched every invoker (they are distinct objects), but
 * the registry bookkeeping collapsed them into one slot: one entry in
 * `$handlers`, one key per EntriesIndexer bucket and one entry in the deferred
 * pool. Unregister selectors then removed a single registration and the other
 * callbacks leaked in `$wp_filter` forever.
 */
final class DuplicateAttributeRegistrationTest extends WplokerbjmTestCase
{
    private const HOOK = 'duplicate_attribute_hook';
    private const TRIGGER = 'duplicate_attribute_trigger';

    public function testStackedIdenticalAttributesKeepDistinctRegistrySlots(): void
    {
        $service = $this->bindService();

        $registry = $this->createRegistry([
            $this->registration(attributeIndex: 0, once: true),
            $this->registration(attributeIndex: 1),
            $this->registration(attributeIndex: 2, tags: ['init']),
        ]);
        $registry->initialize();

        $this->assertCount(3, $this->hooksNamed(self::HOOK));

        \do_action(self::HOOK);
        $this->assertSame(3, $service->runs, 'Every stacked attribute must fire');

        // The once registration consumed itself; the other two must survive.
        $this->assertCount(2, $this->hooksNamed(self::HOOK));

        $registry->unregisterByClass(DuplicateAttributeService::class);
        $this->assertCount(0, $this->hooksNamed(self::HOOK), 'Every stacked registration must be removable');
    }

    public function testTagSelectorRemovesOnlyTheTaggedStackedAttribute(): void
    {
        $this->bindService();

        $registry = $this->createRegistry([
            $this->registration(attributeIndex: 0, once: true),
            $this->registration(attributeIndex: 1),
            $this->registration(attributeIndex: 2, tags: ['init']),
        ]);
        $registry->initialize();

        $this->assertCount(3, $this->hooksNamed(self::HOOK));

        $registry->unregisterByTags(['init']);

        $this->assertCount(2, $this->hooksNamed(self::HOOK));
    }

    public function testStackedDeferredAttributesAllActivateOnTrigger(): void
    {
        $service = $this->bindService();

        $registry = $this->createRegistry([
            $this->registration(deferred: true, attributeIndex: 0),
            $this->registration(deferred: true, attributeIndex: 1),
            $this->registration(deferred: true, attributeIndex: 2, tags: ['init']),
        ]);
        $registry->initialize();

        $this->assertCount(0, $this->hooksNamed(self::HOOK));

        \do_action(self::TRIGGER);
        $this->assertCount(3, $this->hooksNamed(self::HOOK));

        \do_action(self::HOOK);
        $this->assertSame(3, $service->runs);
    }

    public function testScannerAssignsDistinctAttributeIndexesToStackedAttributes(): void
    {
        $scanner = new WPHooksScanner(
            [DuplicateAttributeService::class => static fn(): object => new DuplicateAttributeService()],
            'WPLokerBJM\\Tests\\WPHookTests',
        );

        $indexes = [];
        foreach ($scanner->getHookRegistrations() as $registration) {
            if ($registration->class !== DuplicateAttributeService::class || $registration->method !== 'onShutdown') {
                continue;
            }
            $indexes[] = $registration->attributeIndex;
        }

        $this->assertSame([0, 1, 2], $indexes);
    }

    private function bindService(): DuplicateAttributeService
    {
        $service = new DuplicateAttributeService();
        $this->container()->set(DuplicateAttributeService::class, $service);

        return $service;
    }

    /**
     * @param list<string> $tags
     * @return array<string, mixed>
     */
    private function registration(
        bool $deferred = false,
        bool $once = false,
        array $tags = [],
        int $attributeIndex = 0,
    ): array {
        return [
            'class' => DuplicateAttributeService::class,
            'method' => 'onShutdown',
            'type' => 'action',
            'hook' => self::HOOK,
            'priority' => 10,
            'acceptedArgs' => 0,
            'deferRegister' => false,
            'target' => 'method',
            'visibility' => 'public',
            'tags' => $tags,
            'once' => $once,
            'attributeIndex' => $attributeIndex,
            'deferRegisterUntilHook' => $deferred ? self::TRIGGER : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hooksNamed(string $hook): array
    {
        return array_values(array_filter(
            $this->registeredHooks(),
            static fn(array $registered): bool => $registered['hook'] === $hook,
        ));
    }
}

/**
 * Plain service: registrations are supplied by the test, mirroring
 * `#[Action(...)]` stacks that resolve to the same hook and priority.
 */
final class DuplicateAttributeService
{
    public int $runs = 0;

    #[Action('duplicate_attribute_hook', 10, once: true)]
    #[Action('duplicate_attribute_hook', 10)]
    #[Action('duplicate_attribute_hook', 10, tag: ['init'])]
    public function onShutdown(): void
    {
        $this->runs++;
    }
}
