<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Outcome of one deferred activation attempt (`after` side of the DTO).
 *
 * `reason` is intentionally a string (not an enum) so emitters can add
 * diagnostics without a BC break. Conventional values:
 * `activated`, `gate_rejected`, `owner_gone`, `activation_failed`.
 *
 * @phpstan-type DeferredActivationResultData array{
 *  gatePassed: bool,
 *  activated: bool,
 *  reason: string,
 *  elapsedUs: int,
 *  poolSizeBefore: int,
 *  poolSizeAfter: int,
 *  errorClass: ?string,
 *  errorMessage: ?string,
 * }
 * @extends parent<DeferredActivationResultData>
 */
final readonly class DeferredActivationResultDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param DeferredActivationResultData['gatePassed'] $gatePassed
     * @param DeferredActivationResultData['activated'] $activated
     * @param DeferredActivationResultData['reason'] $reason
     * @param DeferredActivationResultData['elapsedUs'] $elapsedUs
     * @param DeferredActivationResultData['poolSizeBefore'] $poolSizeBefore
     * @param DeferredActivationResultData['poolSizeAfter'] $poolSizeAfter
     * @param DeferredActivationResultData['errorClass'] $errorClass
     * @param DeferredActivationResultData['errorMessage'] $errorMessage
     */
    public function __construct(
        public bool $gatePassed,
        public bool $activated,
        public string $reason,
        public int $elapsedUs,
        public int $poolSizeBefore,
        public int $poolSizeAfter,
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
    ) {}
}
