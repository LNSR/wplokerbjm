<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Provider;

use ArrayAccess;
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Immutable reflection metadata for one callable parameter.
 *
 * Plans are built once at scan time, embedded in DTOs, exported to the hooks
 * cache and re-hydrated with {@see self::__set_state()} on subsequent
 * requests.
 * @phpstan-type CallableHookParamsArray array{
 *  name: string, 
 *  type: string|class-string|null, 
 *  hasDefault: bool, 
 *  default: mixed, 
 *  isVariadic: bool, 
 *  isSelf: bool, 
 *  allowsNull: bool
 * }
 *
 * @extends AbstractDataObject<CallableHookParamsArray>
 */
final readonly class CallableHookParams extends AbstractDataObject
{
    /**
     * @param CallableHookParamsArray['name'] $name
     * @param CallableHookParamsArray['type'] $type
     * @param CallableHookParamsArray['hasDefault'] $hasDefault
     * @param CallableHookParamsArray['default'] $default
     * @param CallableHookParamsArray['isVariadic'] $isVariadic
     * @param CallableHookParamsArray['isSelf'] $isSelf
     * @param CallableHookParamsArray['allowsNull'] $allowsNull
     */
    public function __construct(
        public string $name,
        public ?string $type = null,
        public bool $hasDefault = false,
        public mixed $default = null,
        public bool $isVariadic = false,
        public bool $isSelf = false,
        public bool $allowsNull = false,
    ) {}
}
