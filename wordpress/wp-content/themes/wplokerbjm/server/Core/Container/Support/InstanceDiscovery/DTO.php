<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\InstanceDiscovery;

use WPLokerBJM\Shared\Utilities\DTO\AbstractDTO;

/**
 * DTO for callable injection entries.
 *
 * This class holds metadata about properties or methods that should be
 * treated as callable injection points.
 * @template TClass
 * @phpstan-type CallableEntry array{class: class-string<TClass>, member: method-string<TClass>|property-string<TClass>, kind: 'property'|'method', lazy: bool}
 * @extends parent<CallableEntry>
 */
final readonly class CallableEntryDTO extends AbstractDTO
{
    /**
     * Create a new instance.
     *
     * @param CallableEntry['class'] $class      The class containing the callable member
     * @param CallableEntry['member'] $member  The property or method name
     * @param CallableEntry['kind'] $kind  The type of callable member
     * @param CallableEntry['lazy'] $lazy  Whether the callable should be lazy-loaded
     */
    public function __construct(
        public string $class,
        public string $member,
        public string $kind,
        public bool $lazy,
    ) {}
}
