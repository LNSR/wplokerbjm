<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\ContainerLazyHookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\ContainerLazyPropertyHookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{RuntimeRegistryHandlerEntry, ContainerRegistryHandlerEntry, WPHooksInstanceRegistry};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\DataObject\TraitDataObject;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * @mixin ContainerRegistryHandlerEntry&RuntimeRegistryHandlerEntry
 * @template TShape
 */
trait HandlerEntryTrait
{
    /** @use TraitDataObject<TShape> */
    use TraitDataObject;
    private bool $registered = false;
    private bool $unregistered = false;

    /**
     * Automatically unregister handler when this entry have 0 reference, useful for @see WPHooksInstanceRegistry.
     */
    public function __destruct()
    {
        $this->unregister();
    }

    public function register(): static
    {
        if ($this->registered) return $this;
        match ($this->type) {
            'action' => add_action($this->hook, $this->handler, $this->priority, $this->acceptedArgs),
            'filter' => add_filter($this->hook, $this->handler, $this->priority, $this->acceptedArgs),
        };
        $this->registered = true;
        return $this;
    }

    public function unregister(): static
    {
        if ($this->unregistered) return $this;
        match ($this->type) {
            'action' => remove_action($this->hook, $this->handler, $this->priority),
            'filter' => remove_filter($this->hook, $this->handler, $this->priority),
        };
        $this->unregistered = true;
        return $this;
    }

    public function stillDispatchingSameHook(): bool
    {
        return match ($this->type) {
            'action' => doing_action($this->hook),
            'filter' => doing_filter($this->hook),
            default => false,
        };
    }

    public function isCurrentlyExecuting(): bool
    {
        return $this->handler->dispatching;
    }
}
