<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Outcome block for unregistration/lifecycle phases.
 *
 * `removed` reflects the WordPress-side removal result where one exists; for
 * lifecycle phases without a removal attempt it states the cleanup state at
 * emission time. `elapsedUs` is the time spent inside the removal callback.
 *
 * @phpstan-type LiveUnregistrationResultData array{
 *  requestedBy: UnregistrationSource,
 *  removed: bool,
 *  failureCount: int,
 *  stillDispatching: bool,
 *  elapsedUs: int,
 *  errorClass: ?string,
 *  errorMessage: ?string,
 * }
 * @extends parent<LiveUnregistrationResultData>
 */
final readonly class LiveUnregistrationResultDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param LiveUnregistrationResultData['requestedBy'] $requestedBy
     * @param LiveUnregistrationResultData['removed'] $removed
     * @param LiveUnregistrationResultData['failureCount'] $failureCount
     * @param LiveUnregistrationResultData['stillDispatching'] $stillDispatching
     * @param LiveUnregistrationResultData['elapsedUs'] $elapsedUs
     * @param LiveUnregistrationResultData['errorClass'] $errorClass
     * @param LiveUnregistrationResultData['errorMessage'] $errorMessage
     */
    public function __construct(
        public UnregistrationSource $requestedBy,
        public bool $removed,
        public int $failureCount = 0,
        public bool $stillDispatching = false,
        public int $elapsedUs = 0,
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
    ) {}
}
