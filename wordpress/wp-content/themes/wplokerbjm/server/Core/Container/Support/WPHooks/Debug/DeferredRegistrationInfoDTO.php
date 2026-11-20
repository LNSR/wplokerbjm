<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Payload passed to `#[Action(debugDeferredRegistration: ...)]` closures.
 *
 * Built fresh for every phase and handed to the consumer; the framework keeps
 * no reference to it. `after` is present only for outcome phases
 * (activated / rejected / failed / released).
 *
 * @phpstan-type DeferredRegistrationInfoData array{
 *  phase: DeferredDebugPhase,
 *  context: HookDebugContext,
 *  before: DeferredRegistrationSnapshotDTO,
 *  after: ?DeferredActivationResultDTO,
 *  at: ?float,
 * }
 * @extends parent<DeferredRegistrationInfoData>
 */
final readonly class DeferredRegistrationInfoDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param DeferredRegistrationInfoData['phase'] $phase
     * @param DeferredRegistrationInfoData['context'] $context
     * @param DeferredRegistrationInfoData['before'] $before
     * @param DeferredRegistrationInfoData['after'] $after
     * @param DeferredRegistrationInfoData['at'] $at
     */
    public function __construct(
        public DeferredDebugPhase $phase,
        public HookDebugContext $context,
        public DeferredRegistrationSnapshotDTO $before,
        public ?DeferredActivationResultDTO $after = null,
        public ?float $at = null,
    ) {}
}
