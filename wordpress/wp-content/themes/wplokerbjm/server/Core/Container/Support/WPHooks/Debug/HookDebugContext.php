<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Debug;

use WPLokerBJM\Shared\Utilities\DataObject\AbstractDataObject;

/**
 * Identity of the registration a debug event belongs to.
 *
 * Built at the emission site from the invoker/entry that already holds this
 * metadata — it is a transient value object, never cached and never retained
 * by the framework.
 *
 * `path` distinguishes the two registries:
 *  - `container`: lazy handlers resolved from the DI container (class-string identity)
 *  - `runtime`: handlers bound to a live object (`ownerId` = `spl_object_id()`)
 *
 * @phpstan-type HookDebugContextData array{
 *  path: 'container'|'runtime',
 *  hook: string,
 *  type: 'action'|'filter',
 *  priority: int,
 *  acceptedArgs: int,
 *  once: bool,
 *  label: string,
 *  class: ?string,
 *  member: ?string,
 *  target: 'method'|'property'|'property-hook'|null,
 *  visibility: 'public'|'protected'|'private'|null,
 *  attributeIndex: int,
 *  ownerId: ?int,
 * }
 * @extends parent<HookDebugContextData>
 */
final readonly class HookDebugContext extends AbstractDataObject
{
    use TraitDebugArray;

    /**
     * @param HookDebugContextData['path'] $path
     * @param HookDebugContextData['hook'] $hook
     * @param HookDebugContextData['type'] $type
     * @param HookDebugContextData['priority'] $priority
     * @param HookDebugContextData['acceptedArgs'] $acceptedArgs
     * @param HookDebugContextData['once'] $once
     * @param HookDebugContextData['label'] $label
     * @param HookDebugContextData['class'] $class
     * @param HookDebugContextData['member'] $member
     * @param HookDebugContextData['target'] $target
     * @param HookDebugContextData['visibility'] $visibility
     * @param HookDebugContextData['attributeIndex'] $attributeIndex
     * @param HookDebugContextData['ownerId'] $ownerId
     */
    public function __construct(
        public string $path,
        public string $hook,
        public string $type,
        public int $priority,
        public int $acceptedArgs,
        public bool $once,
        public string $label,
        public ?string $class = null,
        public ?string $member = null,
        public ?string $target = null,
        public ?string $visibility = null,
        public int $attributeIndex = 0,
        public ?int $ownerId = null,
    ) {}
}
