<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use WPLokerBJM\Core\Container\Support\WPHooks\{RuntimeRegistryHandlerEntry, ContainerRegistryHandlerEntry};

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

        return $this;
    }

    public function stillInCallbackStack(): bool
    {
        return match ($this->type) {
            'action' => doing_action($this->hook),
            'filter' => doing_filter($this->hook),
            default => false,
        };
    }
}