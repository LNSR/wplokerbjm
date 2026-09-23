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
 * @phpstan-type InjectionEntry array{class: class-string<TClass>, member: method-string<TClass>|property-string<TClass>, kind: 'property'|'method', lazy: bool}
 * @extends parent<InjectionEntry>
 */
final readonly class InjectionEntryDTO extends AbstractDTO
{
    /**
     * Create a new instance.
     *
     * @param InjectionEntry['class'] $class      The class containing the callable member
     * @param InjectionEntry['member'] $member  The property or method name
     * @param InjectionEntry['kind'] $kind  The type of callable member
     * @param InjectionEntry['lazy'] $lazy  Whether the callable should be lazy-loaded
     */
    public function __construct(
        public string $class,
        public string $member,
        public string $kind,
        public bool $lazy,
    ) {}
}
