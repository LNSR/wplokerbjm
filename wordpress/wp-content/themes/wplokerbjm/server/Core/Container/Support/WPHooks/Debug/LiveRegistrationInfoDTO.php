<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Payload passed to `#[Action(debugLiveRegistration: ...)]` closures.
 *
 * Built fresh per phase at the transition point (register / dispatch /
 * complete) and discarded immediately after the closure returns. `after` is
 * present only for outcome phases (completed / failed / skipped).
 *
 * @phpstan-type LiveRegistrationInfoData array{
 *  phase: LiveDebugPhase,
 *  context: HookDebugContext,
 *  before: LiveRegistrationSnapshotDTO,
 *  after: ?LiveExecutionResultDTO,
 *  at: ?float,
 * }
 * @extends parent<LiveRegistrationInfoData>
 */
final readonly class LiveRegistrationInfoDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param LiveRegistrationInfoData['phase'] $phase
     * @param LiveRegistrationInfoData['context'] $context
     * @param LiveRegistrationInfoData['before'] $before
     * @param LiveRegistrationInfoData['after'] $after
     * @param LiveRegistrationInfoData['at'] $at
     */
    public function __construct(
        public LiveDebugPhase $phase,
        public HookDebugContext $context,
        public LiveRegistrationSnapshotDTO $before,
        public ?LiveExecutionResultDTO $after = null,
        public ?float $at = null,
    ) {}
}
