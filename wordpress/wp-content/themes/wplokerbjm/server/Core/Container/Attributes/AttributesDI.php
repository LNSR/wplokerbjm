<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;
use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * @template TClass
 * @phpstan-type TInject array{
 *  name: (class-string<TClass>&array{class-string<TClass>, property-string<TClass>&callable-string<TClass>})|null,
 *  lazy: bool
 * }
 * @extends parent<TInject>
 * #[Inject] attribute for the DependencyInjector.
 *
 * Marks a property as an injection point on an anonymous child object:
 *
 * - #[Inject]                                                  → resolve from the property's declared type.
 * - #[Inject('entry.name')]                                    → resolve a named container entry.
 * - #[Inject([Class::class, 'method/property'])]               → call the method/property/property and inject its return value.
 * - #[Inject([Class::class, 'method/property'], lazy: true)]   → inject a method/property bound to the instance scope:
 * * the declared type runtime contract specified must be closure, TIP: lazy is good if wanted to get live value property
 * @example
 * ```php
 * class Example {
 * #[Inject([Class::class, 'method'], lazy: true)] OR #[Inject([Class::class, 'property'], lazy: true)]
 *  private \Closure $resolve
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Inject extends AbstractDataObject
{
    /**
     * @param TInject['name'] $name
     * @param TInject['lazy'] $lazy
     */
    public function __construct(
        public readonly string|array|null $name = null,
        public readonly bool $lazy = false,
    ) {
    }
}
/**
 * @phpstan-type TInjectable array{
 *  lazy: bool,
 *  skip: bool
 * }
 * @extends parent<TInjectable>
 * "Injectable" attribute.
 *
 * Marks a class as injectable
 * PHP-DI replacement for \DI\Attribute\Injectable
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Injectable extends AbstractDataObject
{
    /**
     * @param TInjectable['lazy'] $lazy Should the class be lazy-loaded.
     * @param TInjectable['skip'] $skip Skip the class from being scanned.
     */
    public function __construct(
        public ?bool $lazy = null,
        public bool $skip = false
    ) {
    }
}
