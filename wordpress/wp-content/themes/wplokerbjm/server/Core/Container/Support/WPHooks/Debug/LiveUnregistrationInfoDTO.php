<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Payload passed to `#[Action(debugLiveUnregistration: ...)]` closures.
 *
 * Emitted on every unregistration/lifecycle transition. `result` is present
 * when the phase has an outcome (removal attempted, once consumed, owner
 * collected, proxy released).
 *
 * @phpstan-type LiveUnregistrationInfoData array{
 *  phase: LiveUnregistrationPhase,
 *  context: HookDebugContext,
 *  before: LiveRegistrationSnapshotDTO,
 *  result: ?LiveUnregistrationResultDTO,
 *  at: ?float,
 * }
 * @extends parent<LiveUnregistrationInfoData>
 */
final readonly class LiveUnregistrationInfoDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param LiveUnregistrationInfoData['phase'] $phase
     * @param LiveUnregistrationInfoData['context'] $context
     * @param LiveUnregistrationInfoData['before'] $before
     * @param LiveUnregistrationInfoData['result'] $result
     * @param LiveUnregistrationInfoData['at'] $at
     */
    public function __construct(
        public LiveUnregistrationPhase $phase,
        public HookDebugContext $context,
        public LiveRegistrationSnapshotDTO $before,
        public ?LiveUnregistrationResultDTO $result = null,
        public ?float $at = null,
    ) {}
}
