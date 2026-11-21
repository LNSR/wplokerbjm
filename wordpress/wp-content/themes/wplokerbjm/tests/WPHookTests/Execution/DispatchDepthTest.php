<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Execution;

use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\ContainerRegistryHandlerEntry;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * Pins re-entrant dispatch state: an inner callback must not clear the
 * executing flag while the outer callback is still on the stack.
 */
final class DispatchDepthTest extends WplokerbjmTestCase
{
    public function testReentrantDispatchKeepsExecutingStateUntilOuterFinally(): void
    {
        $container = new class implements ContainerInterface {
            public object $service;

            public function get(string $id): mixed
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $owner = new class {
            public ?HookInvoker $invoker = null;
            public ?ContainerRegistryHandlerEntry $entry = null;
            /** @var list<array{depth: int, dispatching: bool, executing: bool}> */
            public array $states = [];

            public function onHook(): void
            {
                $this->states[] = [
                    'depth' => $this->invoker->dispatchDepth,
                    'dispatching' => $this->invoker->dispatching,
                    'executing' => $this->entry->isCurrentlyExecuting(),
                ];

                if (count($this->states) === 1) {
                    ($this->invoker)();
                }
            }
        };

        $container->service = $owner;
        $provider = new WPHookPlanProvider($container);

        $invoker = HookInvoker::containerMethod(
            container: $container,
            planProvider: $provider,
            hookName: 'reentrant_depth_hook',
            priority: 10,
            class: $owner::class,
            method: 'onHook',
            acceptedArgs: 0,
        );

        $entry = new ContainerRegistryHandlerEntry(
            hook: 'reentrant_depth_hook',
            key: new HookKey(
                class: $owner::class,
                method: 'onHook',
                target: 'method',
                type: 'action',
                priority: 10,
                acceptedArgs: 0,
            ),
            handler: $invoker,
            type: 'action',
            priority: 10,
            acceptedArgs: 0,
            tags: [],
            registerIf: null,
            registerIfParams: [],
            executeIf: null,
            executeIfParams: [],
            once: false,
            onUnregisterDo: null,
            onUnregisterDoParams: [],
        );

        $owner->invoker = $invoker;
        $owner->entry = $entry;

        $invoker();

        self::assertSame([1, 2], array_column($owner->states, 'depth'));
        self::assertSame([true, true], array_column($owner->states, 'dispatching'));
        self::assertSame([true, true], array_column($owner->states, 'executing'));
        self::assertSame(0, $invoker->dispatchDepth);
        self::assertFalse($invoker->dispatching);
        self::assertFalse($entry->isCurrentlyExecuting());
    }
}
