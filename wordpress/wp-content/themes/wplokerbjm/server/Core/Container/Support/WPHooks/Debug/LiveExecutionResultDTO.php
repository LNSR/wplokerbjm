<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Outcome of one hook execution (`after` side of the live DTO).
 *
 * `status` mirrors the invoker outcome:
 * `completed`, `failed`, `skipped` (executeIf rejected), `passthrough`
 * (error fallback: filters return the original value, actions return null).
 *
 * @phpstan-type LiveExecutionResultData array{
 *  status: 'completed'|'failed'|'skipped'|'passthrough',
 *  elapsedUs: int,
 *  returnType: ?string,
 *  errorClass: ?string,
 *  errorMessage: ?string,
 *  executionsAfter: int,
 * }
 * @extends parent<LiveExecutionResultData>
 */
final readonly class LiveExecutionResultDTO extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param LiveExecutionResultData['status'] $status
     * @param LiveExecutionResultData['elapsedUs'] $elapsedUs
     * @param LiveExecutionResultData['returnType'] $returnType
     * @param LiveExecutionResultData['errorClass'] $errorClass
     * @param LiveExecutionResultData['errorMessage'] $errorMessage
     * @param LiveExecutionResultData['executionsAfter'] $executionsAfter
     */
    public function __construct(
        public string $status,
        public int $elapsedUs,
        public ?string $returnType = null,
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
        public int $executionsAfter = 0,
    ) {}
}
