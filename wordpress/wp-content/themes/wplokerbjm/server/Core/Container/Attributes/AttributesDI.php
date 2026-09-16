<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;
use WPLokerBJM\Shared\Utilities\DTO\AbstractDTO;

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
 * - #[Inject]                                   → resolve from the property's declared type.
 * - #[Inject('entry.name')]                     → resolve a named container entry.
 * - #[Inject([Class::class, 'method'])]         → call the method and inject its return value.
 * - #[Inject([Class::class, 'method'], lazy: true)] → inject a closure bound to the instance scope (FCC).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Inject extends AbstractDTO
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
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Injectable extends AbstractDTO
{
    /**
     * @param TInjectable['lazy'] $lazy Should the object be lazy-loaded.
     * @param TInjectable['skip'] $skip Skip the object from being lazy-loaded.
     */
    public function __construct(
        public ?bool $lazy = null,
        public bool $skip = false
    ) {
    }
}
