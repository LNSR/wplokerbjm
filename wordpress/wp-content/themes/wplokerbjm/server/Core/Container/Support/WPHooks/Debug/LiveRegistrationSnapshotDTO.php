<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Handler state captured immediately before a live phase.
 *
 * @phpstan-type LiveRegistrationSnapshotData array{
 *  registered: bool,
 *  consumed: bool,
 *  executions: int,
 *  ownerAlive: bool,
 * }
 * @extends parent<LiveRegistrationSnapshotData>
 */
final readonly class LiveRegistrationSnapshotDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param LiveRegistrationSnapshotData['registered'] $registered
     * @param LiveRegistrationSnapshotData['consumed'] $consumed
     * @param LiveRegistrationSnapshotData['executions'] $executions
     * @param LiveRegistrationSnapshotData['ownerAlive'] $ownerAlive
     */
    public function __construct(
        public bool $registered,
        public bool $consumed,
        public int $executions,
        public bool $ownerAlive,
    ) {}
}
