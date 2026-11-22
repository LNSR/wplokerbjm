<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Debug;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredActivationSource,
    DeferredRegistrationInfoDTO,
    LiveRegistrationInfoDTO,
    LiveUnregistrationInfoDTO,
    LiveUnregistrationPhase,
    LiveUnregistrationResultDTO,
    UnregistrationSource,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\ContainerRegistryHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\DeferredHooksTrait;
use WPLokerBJM\Tests\Support\WplokerbjmTestCase;

/**
 * End-to-end coverage of the Stage C emitters: probe closures must receive the
 * real lifecycle DTOs at the documented moments, with correct snapshots, and
 * without disturbing the hook pipeline.
 *
 * Probes are typed against the concrete DTO because the plan builder records
 * non-builtin parameter types; that is exactly how attribute probes declare
 * their payload parameter.
 */
final class DebugEmissionTest extends WplokerbjmTestCase
{
    private function debugContainer(): ContainerInterface
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
        $container = $this->debugContainer();
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

        $invoker = HookInvoker::containerMethod(
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

    public function testExecuteIfFailureEmitsSkippedOutcomeWithError(): void
    {
        $container = $this->debugContainer();
        $owner = new class {
            public function debugHook(): string
            {
                return 'should-not-run';
            }
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveRegistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveRegistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $gate = static function (): bool {
            throw new \RuntimeException('gate exploded');
        };

        $invoker = HookInvoker::containerMethod(
            $container,
            $provider,
            'hookDebug',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 0,
            executeIf: $gate,
            executeIfParams: $provider->buildCallablePlan($gate),
            debugLiveRegistration: $probe,
            debugLiveRegistrationParams: $provider->buildCallablePlan($probe),
        );

        self::assertNull($invoker());

        self::assertSame(['dispatching', 'skipped'], array_map(
            static fn(LiveRegistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        $skipped = $payloads[1];
        self::assertSame('skipped', $skipped->after?->status);
        self::assertSame(\RuntimeException::class, $skipped->after?->errorClass);
        self::assertSame('gate exploded', $skipped->after?->errorMessage);
        self::assertSame('container', $skipped->context->path);
    }

    public function testRuntimeLiveSkipsWhenOwnerIsGone(): void
    {
        $container = $this->debugContainer();
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

        $invoker = HookInvoker::runtimeMethod(
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
        $container = $this->debugContainer();
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

        $handler = HookInvoker::containerMethod(
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
            /** @var array<string, DeferredHookEntryHandler> */
            protected array $deferredHandlers = [];

            public function __construct()
            {
                $this->entriesIndexer = new EntriesIndexer();
            }

            protected function deferredGet(string $key, ?object $owner = null): ?DeferredHookEntryHandler
            {
                return $this->deferredHandlers[$key] ?? null;
            }

            protected function deferredPut(string $key, DeferredHookEntryHandler $entry, ?object $owner = null): void
            {
                $this->deferredHandlers[$key] = $entry;
            }

            protected function deferredRemove(string $key, ?object $owner = null): void
            {
                unset($this->deferredHandlers[$key]);
            }

            protected function deferredCount(?object $owner = null): int
            {
                return \count($this->deferredHandlers);
            }

            protected function deferredEntries(?object $owner = null): array
            {
                return $this->deferredHandlers;
            }

            protected function afterDeferredAdded(DeferredHookEntryHandler $entry): void
            {
                $this->entriesIndexer->setIndexes($entry);
                $entry->setOnEntryRemovalRemoveIndexesCallback($this->entriesIndexer->removeIndexes(...));
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

        self::assertNull($payloads[0]->source);
        self::assertSame(DeferredActivationSource::Manual, $payloads[1]->source);
        self::assertSame(DeferredActivationSource::Manual, $payloads[2]->source);

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
        $container = $this->debugContainer();
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

        $handler = HookInvoker::containerMethod(
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
            /** @var array<string, DeferredHookEntryHandler> */
            protected array $deferredHandlers = [];

            public function __construct()
            {
                $this->entriesIndexer = new EntriesIndexer();
            }

            protected function deferredGet(string $key, ?object $owner = null): ?DeferredHookEntryHandler
            {
                return $this->deferredHandlers[$key] ?? null;
            }

            protected function deferredPut(string $key, DeferredHookEntryHandler $entry, ?object $owner = null): void
            {
                $this->deferredHandlers[$key] = $entry;
            }

            protected function deferredRemove(string $key, ?object $owner = null): void
            {
                unset($this->deferredHandlers[$key]);
            }

            protected function deferredCount(?object $owner = null): int
            {
                return \count($this->deferredHandlers);
            }

            protected function deferredEntries(?object $owner = null): array
            {
                return $this->deferredHandlers;
            }

            protected function afterDeferredAdded(DeferredHookEntryHandler $entry): void
            {
                $this->entriesIndexer->setIndexes($entry);
                $entry->setOnEntryRemovalRemoveIndexesCallback($this->entriesIndexer->removeIndexes(...));
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

    public function testUnregistrationProbeEmitsRequestedThenUnregistered(): void
    {
        $container = $this->debugContainer();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveUnregistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveUnregistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $handler = HookInvoker::containerMethod(
            $container,
            $provider,
            'unregistration_debug',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 1,
            debugLiveUnregistration: $probe,
            debugLiveUnregistrationParams: $provider->buildCallablePlan($probe),
        );

        $entry = new ContainerRegistryHandlerEntry(
            hook: 'unregistration_debug',
            key: new HookKey($owner::class, 'debugHook', 'method', 'action', 10, 1),
            handler: $handler,
            type: 'action',
            priority: 10,
            acceptedArgs: 1,
            tags: [],
            registerIf: null,
            registerIfParams: [],
            executeIf: null,
            executeIfParams: [],
            once: false,
            onUnregisterDo: null,
            onUnregisterDoParams: [],
        );

        $entry->register();
        self::assertTrue($entry->unregister(UnregistrationSource::Manual));

        self::assertSame(['unregister_requested', 'unregistered'], array_map(
            static fn(LiveUnregistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));

        self::assertTrue($payloads[0]->before->registered);
        self::assertFalse($payloads[0]->result?->removed);

        $unregistered = $payloads[1];
        self::assertFalse($unregistered->before->registered);
        self::assertTrue($unregistered->result?->removed);
        self::assertSame(UnregistrationSource::Manual, $unregistered->result->requestedBy);
    }

    public function testUnregisterOnMissingCallbackEmitsFailure(): void
    {
        $container = $this->debugContainer();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveUnregistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveUnregistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $handler = HookInvoker::containerMethod(
            $container,
            $provider,
            'unregistration_debug_missing',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 0,
            debugLiveUnregistration: $probe,
            debugLiveUnregistrationParams: $provider->buildCallablePlan($probe),
        );

        $entry = new ContainerRegistryHandlerEntry(
            hook: 'unregistration_debug_missing',
            key: new HookKey($owner::class, 'debugHook', 'method', 'action', 10, 0),
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
        );

        self::assertFalse($entry->unregister(UnregistrationSource::Cleaner));

        self::assertSame(['unregister_requested', 'unregister_failed'], array_map(
            static fn(LiveUnregistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));
        self::assertSame('callback_not_registered', $payloads[1]->result?->errorMessage);
        self::assertSame(UnregistrationSource::Cleaner, $payloads[1]->result->requestedBy);
    }

    public function testProxyReleasedEmitsFromDestructorWithNullOwner(): void
    {
        $container = $this->debugContainer();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        $captured = null;
        $probe = static function (?self $owner, LiveUnregistrationInfoDTO $dto) use (&$captured): void {
            $captured = [$owner, $dto];
        };

        $handler = HookInvoker::containerMethod(
            $container,
            $provider,
            'unregistration_debug_destructor',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 0,
            debugLiveUnregistration: $probe,
            debugLiveUnregistrationParams: $provider->buildCallablePlan($probe),
        );

        unset($handler);

        self::assertIsArray($captured);
        self::assertNull($captured[0], 'A destructor emission must not fabricate the owner.');
        /** @var LiveUnregistrationInfoDTO $dto */
        $dto = $captured[1];
        self::assertSame('proxy_released', $dto->phase->value);
        self::assertSame(UnregistrationSource::Destructor, $dto->result?->requestedBy);
    }

    public function testOnceConsumptionEmitsOnceConsumed(): void
    {
        $container = $this->debugContainer();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveUnregistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveUnregistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $invoker = HookInvoker::containerMethod(
            $container,
            $provider,
            'unregistration_debug_once',
            10,
            $owner::class,
            'debugHook',
            acceptedArgs: 0,
            once: true,
            debugLiveUnregistration: $probe,
            debugLiveUnregistrationParams: $provider->buildCallablePlan($probe),
        );

        $cleaned = 0;
        $invoker->setCleanupCallback(static function () use (&$cleaned): void {
            $cleaned++;
        });

        $invoker();
        $invoker();

        self::assertSame(1, $cleaned);
        self::assertSame(['once_consumed'], array_map(
            static fn(LiveUnregistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));
        self::assertSame(UnregistrationSource::Once, $payloads[0]->result?->requestedBy);
        self::assertTrue($payloads[0]->before->consumed);
    }

    public function testOwnerCollectionEmitsOwnerCollected(): void
    {
        $container = $this->debugContainer();
        $owner = new class {
            public function debugHook(): void {}
        };
        $container->service = $owner;

        $provider = new WPHookPlanProvider($container);
        /** @var list<LiveUnregistrationInfoDTO> $payloads */
        $payloads = [];
        $probe = static function (LiveUnregistrationInfoDTO $dto) use (&$payloads): void {
            $payloads[] = $dto;
        };

        $ref = \WeakReference::create($owner);

        $invoker = HookInvoker::runtimeMethod(
            $ref,
            'unregistration_debug_lifetime',
            10,
            'debugHook',
            acceptedArgs: 0,
            hookPlanProvider: $provider,
            debugLiveUnregistration: $probe,
            debugLiveUnregistrationParams: $provider->buildCallablePlan($probe),
        );

        $cleaned = 0;
        $invoker->setCleanupCallback(static function () use (&$cleaned): void {
            $cleaned++;
        });

        $container->service = new \stdClass();
        unset($owner);

        $invoker();

        self::assertSame(1, $cleaned);
        self::assertSame(['owner_collected'], array_map(
            static fn(LiveUnregistrationInfoDTO $dto): string => $dto->phase->value,
            $payloads,
        ));
        self::assertSame(UnregistrationSource::Lifetime, $payloads[0]->result?->requestedBy);
        self::assertFalse($payloads[0]->before->ownerAlive);
    }
}
