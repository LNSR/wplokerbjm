<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;

/**
 * #[Inject] attribute for the DependencyInjector.
 *
 * Marks a property as an injection point on an anonymous child object:
 *
 * - #[Inject]                                   → resolve from the property's declared type.
 * - #[Inject('entry.name')]                     → resolve a named container entry.
 * - #[Inject([Class::class, 'method'])]         → call the method and inject its return value.
 * - #[Inject([Class::class, 'method'], lazy: true)] → inject a closure bound to the instance scope (FCC).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(
        public readonly string|array|null $name = null,
        public readonly bool $lazy = false,
    ) {
    }
}
/**
 * "Injectable" attribute.
 *
 * Marks a class as injectable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Injectable
{
    /**
     * @param bool|null $lazy Should the object be lazy-loaded.
     */
    public function __construct(
        public ?bool $lazy = null,
        public bool $skip = false
    ) {
    }
}
