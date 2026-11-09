<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use AvailaibleHandlerType;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\{
    ContainerLazyHookInvoker,
    ContainerLazyPropertyHookInvoker,
    RuntimeCallableHookInvoker,
    RuntimeInstanceHookInvoker,
    RuntimeInstancePropertyHookInvoker
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    RuntimeRegistryHandlerEntry,
    ContainerRegistryHandlerEntry,
    WPHooksInstanceRegistry
};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\DataObject\TraitDataObject;
use WPLokerBJM\Shared\Utilities\SharedUtils;

/**
 * @mixin ContainerRegistryHandlerEntry&RuntimeRegistryHandlerEntry
 * @phpstan-type AvailaibleHandlerType ContainerLazyHookInvoker|ContainerLazyPropertyHookInvoker|RuntimeCallableHookInvoker|RuntimeInstanceHookInvoker|RuntimeInstancePropertyHookInvoker
 * @template TShape
 */
trait HandlerEntryTrait
{
    /** @use TraitDataObject<TShape> */
    use TraitDataObject;

    /**
     * @var WeakReference<AvailaibleHandlerType>|AvailaibleHandlerType|null
     */
    public private(set) ?object $handler = null {
        get => $this->handler instanceof WeakReference ? $this->handler->get() : null;
        set => \is_object($value) ? WeakReference::create($value) : $value;
    }
    /** Cached invoker label in case destroyed */
    public readonly string $invokerLabel;

    public private(set) int $unregistrationFailureCount = 0;
    public private(set) bool $registered = false;

    /**
     * Automatically unregister handler when this entry have 0 reference, useful for @see WPHooksInstanceRegistry.
     */
    public function __destruct()
    {
        if (!$this->registered) return;
        $this->unregister();
    }

    public function register(): static
    {
        if ($this->registered || $this->handler === null) return $this;
        match ($this->type) {
            'action' => add_action($this->hook, $this->handler, $this->priority, $this->acceptedArgs),
            'filter' => add_filter($this->hook, $this->handler, $this->priority, $this->acceptedArgs),
        };
        $this->registered = true;
        try {
            $this->handler->initOnUnregisterDoCallback();
        } catch (\Throwable $e) {
            Logger::error(static::class, 'Failed to prepare onUnregisterDo for ', [
                'invokerLabel' => $this->handler->label,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
        return $this;
    }

    public function unregister(): bool
    {
        $hasCallback = $this->hasCallbackRegisteredOnWordpress();
        if (!$hasCallback || $this->handler === null) return false;

        $result = match ($this->type) {
            'action' => remove_action($this->hook, $this->handler, $this->priority),
            'filter' => remove_filter($this->hook, $this->handler, $this->priority),
        };

        if (!$result) $this->unregistrationFailureCount++;
        if ($result) {
            $this->registered = false;
            if (is_callable($this->handler?->onUnregisterDoCallback ?? null)) ($this->handler->onUnregisterDoCallback)();
        }
        return $result;
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
        return $this->handler?->dispatching ?? false;
    }

    public function hasCallbackRegisteredOnWordpress(): bool
    {
        if ($this->handler === null) return false;
        return match ($this->type) {
            'action' => has_action($this->hook, $this->handler, $this->priority),
            'filter' => has_filter($this->hook, $this->handler, $this->priority),
        };
    }
}
