<?php

declare(strict_types=1);

namespace WPLokerBJM\Tests\WPHookTests\Debug;

use PHPUnit\Framework\TestCase;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    DeferredActivationResultDTO,
    DeferredActivationSource,
    DeferredDebugPhase,
    DeferredRegistrationInfoDTO,
    DeferredRegistrationSnapshotDTO,
    HookDebugContext,
    LiveDebugPhase,
    LiveRegistrationInfoDTO,
    LiveRegistrationSnapshotDTO,
};

/**
 * Pins the debug payload contract: nested DTOs and backed enums must already
 * be scalars after `toArray()`, because consumers persist that array.
 */
final class DebugDtoTest extends TestCase
{
    public function testDeferredInfoSerialisesNestedDtosAndEnumsRecursively(): void
    {
        $dto = new DeferredRegistrationInfoDTO(
            phase: DeferredDebugPhase::Activated,
            context: new HookDebugContext(
                path: 'runtime',
                hook: 'hookDebug',
                type: 'action',
                priority: 10,
                acceptedArgs: 1,
                once: false,
                label: 'action::hookDebug::10::1::App\\X->debugHook',
                class: 'App\\X',
                member: 'debugHook',
                target: 'method',
                visibility: 'public',
                attributeIndex: 0,
                ownerId: 42,
            ),
            before: new DeferredRegistrationSnapshotDTO(
                uniqueKey: 'hookDebug:App\\X::debugHook::method::action::10::1::0',
                deferRegister: true,
                deferRegisterUntilHook: null,
                tags: ['mail'],
                hasRegisterIf: false,
                hasExecuteIf: false,
                ownerAlive: true,
            ),
            source: DeferredActivationSource::Scheduler,
            after: new DeferredActivationResultDTO(
                gatePassed: true,
                activated: true,
                reason: 'activated',
                elapsedUs: 120,
                poolSizeBefore: 3,
                poolSizeAfter: 2,
            ),
            at: 1_700_000_000.5,
        );

        $array = $dto->toArray();

        self::assertSame('activated', $array['phase'], 'Backed enum must flatten to its value.');
        self::assertSame('scheduler', $array['source'], 'Activation source must flatten to its value.');
        self::assertSame('runtime', $array['context']['path'], 'Nested context must flatten.');
        self::assertSame('activated', $array['after']['reason']);
        self::assertSame(120, $array['after']['elapsedUs']);
        self::assertNull($array['after']['errorClass']);
        self::assertSame(['mail'], $array['before']['tags']);
        self::assertIsArray($array['before']);
        self::assertIsScalar($array['at']);
    }

    public function testLiveInfoKeepsOutcomeOptional(): void
    {
        $dto = new LiveRegistrationInfoDTO(
            phase: LiveDebugPhase::Dispatching,
            context: new HookDebugContext(
                path: 'container',
                hook: 'hookDebug',
                type: 'action',
                priority: 10,
                acceptedArgs: 0,
                once: false,
                label: 'action::hookDebug::10::0::App\\X->debugHook',
                class: 'App\\X',
                member: 'debugHook',
                target: 'method',
                visibility: 'public',
            ),
            before: new LiveRegistrationSnapshotDTO(
                registered: true,
                consumed: false,
                executions: 2,
                ownerAlive: true,
            ),
        );

        $array = $dto->toArray();

        self::assertSame('dispatching', $array['phase']);
        self::assertNull($array['after'], 'Non-outcome phases carry no result.');
        self::assertNull($array['at']);
        self::assertSame(2, $array['before']['executions']);
    }
}
