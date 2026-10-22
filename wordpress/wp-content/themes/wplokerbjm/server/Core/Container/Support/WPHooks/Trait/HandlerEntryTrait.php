<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\ContainerLazyHookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\ContainerLazyPropertyHookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{RuntimeRegistryHandlerEntry, ContainerRegistryHandlerEntry};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * @mixin ContainerRegistryHandlerEntry&RuntimeRegistryHandlerEntry
 */
trait HandlerEntryTrait
{
    public function register(): static
    {
        match ($this->type) {
            'action' => add_action($this->hook, $this->handler, $this->priority, $this->acceptedArgs),
            'filter' => add_filter($this->hook, $this->handler, $this->priority, $this->acceptedArgs),
        };
        return $this;
    }

    public function unregister(): static
    {
        match ($this->type) {
            'action' => remove_action($this->hook, $this->handler, $this->priority),
            'filter' => remove_filter($this->hook, $this->handler, $this->priority),
        };
        SharedUtils::isDevelopment() && Logger::info(static::class, 'Handler unregistered: ' . $this->handler->label);
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
