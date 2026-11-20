<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Debug;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredRegistrationInfoDTO,
    LiveRegistrationInfoDTO,
};
use WPLokerBJM\Core\Container\Support\WPHooks\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{
    ContainerLazyHookInvoker,
    RuntimeInstanceHookInvoker,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\DeferredHooksTrait;

/**
 * End-to-end coverage of the Stage C emitters: probe closures must receive the
 * real lifecycle DTOs at the documented moments, with correct snapshots, and
 * without disturbing the hook pipeline.
 *
 * Probes are typed against the concrete DTO because the plan builder records
 * non-builtin parameter types; that is exactly how attribute probes declare
 * their payload parameter.
 */
final class DebugEmissionTest extends TestCase
{
    private function container(): ContainerInterface
    {
        return new class implements ContainerInterface {
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
    }

    public function testContainerLiveLifecycleEmitsDispatchingThenCompleted(): void
    {
        $container = $this->container();
        $owner = new class {
            public function debugHook(int $value): string
            {
                return 'value:' . $value;
            }
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveRegistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveRegistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $invoker = new ContainerLazyHookInvoker(
            $container,
            $provider,
            'hookDebug',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 1,
            debugLiveRegistration: $probe,
            debugLiveRegistrationParams: $provider->buildCallablePlan($probe),
        );

        self::assertSame('value:7', $invoker(7));

        self::assertSame(['dispatching', 'completed'], array_map(
            static fn(LiveRegistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        $completed = $payloads[1];
        self::assertSame('completed', $completed->after?->status);
        self::assertSame('string', $completed->after->returnType);
        self::assertSame('container', $completed->context->path);
        self::assertSame($owner::class, $completed->context->class);
        self::assertSame('debugHook', $completed->context->member);
        self::assertTrue($completed->before->registered);
        self::assertGreaterThanOrEqual(0, $completed->after->elapsedUs);
    }

    public function testRuntimeLiveSkipsWhenOwnerIsGone(): void
    {
        $container = $this->container();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveRegistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveRegistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $ref = \WeakReference::create($owner);

        $invoker = new RuntimeInstanceHookInvoker(
            $ref,
            'hookDebug',
            10,
            'debugHook',
            acceptedArgs: 0,
            hookPlanProvider: $provider,
            debugLiveRegistration: $probe,
            debugLiveRegistrationParams: $provider->buildCallablePlan($probe),
        );

        $container->service = new \stdClass();
        unset($owner);

        self::assertNull($invoker());

        self::assertSame(['dispatching', 'skipped'], array_map(
            static fn(LiveRegistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        $skipped = $payloads[1];
        self::assertFalse($skipped->before->ownerAlive);
        self::assertSame('skipped', $skipped->after?->status);
        self::assertSame('runtime', $skipped->context->path);
    }

    public function testDeferredLifecycleEmitsQueuedThenActivated(): void
    {
        $container = $this->container();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<DeferredRegistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (DeferredRegistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $handler = new ContainerLazyHookInvoker(
            $container,
            $provider,
            'hookDebug',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 0,
            debugDeferredRegistration: $probe,
            debugDeferredRegistrationParams: $provider->buildCallablePlan($probe),
        );

        $entry = new DeferredHookEntryHandler(
            hook: 'hookDebug',
            handler: $handler,
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
            key: new HookKey($owner::class, 'debugHook', 'method', 'action', 10, 0),
        );

        $pool = new class {
            use DeferredHooksTrait;

            public EntriesIndexer $entriesIndexer;

            public function __construct()
            {
                $this->entriesIndexer = new EntriesIndexer();
            }

            protected function gateDeferredActivation(DeferredHookEntryHandler $data): bool
            {
                return true;
            }

            public function queue(DeferredHookEntryHandler $entry): void
            {
                $this->addDeferred($entry);
            }

            public function activate(string $key): int
            {
                return $this->activateMatchingDeferredEntries([$key], static fn(): bool => true);
            }
        };

        $pool->queue($entry);
        self::assertSame(['queued'], array_map(
            static fn(DeferredRegistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        $pool->activate($entry->toUniqueKey());

        self::assertSame(['queued', 'activation_attempt', 'activated'], array_map(
            static fn(DeferredRegistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        $activated = $payloads[2];
        self::assertTrue($activated->after?->activated);
        self::assertTrue($activated->after->gatePassed);
        self::assertSame('activated', $activated->after->reason);
        self::assertSame(1, $activated->after->poolSizeBefore);
        self::assertSame(0, $activated->after->poolSizeAfter);
        self::assertSame($entry->toUniqueKey(), $activated->before->uniqueKey);
        self::assertTrue($activated->before->deferRegister);
    }

    public function testDeferredRejectedGateEmitsRejection(): void
    {
        $container = $this->container();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<DeferredRegistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (DeferredRegistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $handler = new ContainerLazyHookInvoker(
            $container,
            $provider,
            'hookDebug',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 0,
            debugDeferredRegistration: $probe,
            debugDeferredRegistrationParams: $provider->buildCallablePlan($probe),
        );

        $entry = new DeferredHookEntryHandler(
            hook: 'hookDebug',
            handler: $handler,
            type: 'action',
            priority: 10,
            acceptedArgs: 0,
            tags: ['mail'],
            registerIf: static fn(): bool => false,
            registerIfParams: [],
            executeIf: null,
            executeIfParams: [],
            once: false,
            onUnregisterDo: null,
            onUnregisterDoParams: [],
            key: new HookKey($owner::class, 'debugHook', 'method', 'action', 10, 0),
        );

        $pool = new class {
            use DeferredHooksTrait;

            public EntriesIndexer $entriesIndexer;

            public function __construct()
            {
                $this->entriesIndexer = new EntriesIndexer();
            }

            protected function gateDeferredActivation(DeferredHookEntryHandler $data): bool
            {
                return false;
            }

            public function queue(DeferredHookEntryHandler $entry): void
            {
                $this->addDeferred($entry);
            }

            public function activate(string $key): int
            {
                return $this->activateMatchingDeferredEntries([$key], static fn(): bool => true);
            }
        };

        $pool->queue($entry);
        self::assertSame(0, $pool->activate($entry->toUniqueKey()));

        self::assertSame(['queued', 'activation_attempt', 'activation_rejected'], array_map(
            static fn(DeferredRegistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        $rejected = $payloads[2];
        self::assertFalse($rejected->after?->gatePassed);
        self::assertFalse($rejected->after->activated);
        self::assertSame('register_if_false', $rejected->after->reason);
        self::assertTrue($rejected->before->hasRegisterIf);
        self::assertSame(['mail'], $rejected->before->tags);
    }
}
