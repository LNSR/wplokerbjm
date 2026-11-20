<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Deferred-pool state captured immediately before a deferred phase.
 *
 * @phpstan-type DeferredRegistrationSnapshotData array{
 *  uniqueKey: string,
 *  deferRegister: bool,
 *  deferRegisterUntilHook: ?string,
 *  tags: list<string>,
 *  hasRegisterIf: bool,
 *  hasExecuteIf: bool,
 *  ownerAlive: bool,
 * }
 * @extends parent<DeferredRegistrationSnapshotData>
 */
final readonly class DeferredRegistrationSnapshotDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param DeferredRegistrationSnapshotData['uniqueKey'] $uniqueKey
     * @param DeferredRegistrationSnapshotData['deferRegister'] $deferRegister
     * @param DeferredRegistrationSnapshotData['deferRegisterUntilHook'] $deferRegisterUntilHook
     * @param DeferredRegistrationSnapshotData['tags'] $tags
     * @param DeferredRegistrationSnapshotData['hasRegisterIf'] $hasRegisterIf
     * @param DeferredRegistrationSnapshotData['hasExecuteIf'] $hasExecuteIf
     * @param DeferredRegistrationSnapshotData['ownerAlive'] $ownerAlive
     */
    public function __construct(
        public string $uniqueKey,
        public bool $deferRegister,
        public ?string $deferRegisterUntilHook,
        public array $tags,
        public bool $hasRegisterIf,
        public bool $hasExecuteIf,
        public bool $ownerAlive,
    ) {}
}
