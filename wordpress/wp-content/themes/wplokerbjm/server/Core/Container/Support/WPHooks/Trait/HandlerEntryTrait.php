<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Trait;

use AvailaibleHandlerType;
use WeakReference;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{
    LiveUnregistrationPhase,
    LiveUnregistrationResultDTO,
    UnregistrationSource,
};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{
    RuntimeRegistryHandlerEntry,
    ContainerRegistryHandlerEntry,
    WPHooksInstanceRegistry
};
use WPLokerBJM\Shared\Log\Logger;
use WPLokerBJM\Shared\Utilities\DataObject\TraitDataObject;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
/**
 * @mixin ContainerRegistryHandlerEntry&RuntimeRegistryHandlerEntry
 * @template TShape
 */
trait HandlerEntryTrait
{
    /** @use TraitDataObject<TShape> */
    use TraitDataObject;
    public private(set) null|WeakReference|HookInvoker $handler = null {
        get => $this->handler instanceof WeakReference ? $this->handler->get() : $this->handler = null;
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
        $this->unregister(UnregistrationSource::Destructor);
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
            Logger::error(__METHOD__, 'Failed to prepare onUnregisterDo for ', [
                'invokerLabel' => $this->handler->label,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
        $this->handler->emitLiveDebug(\WPLokerBJM\Core\Container\Support\WPHooks\Debug\LiveDebugPhase::Registered);
        return $this;
    }

    public function unregister(UnregistrationSource $requestedBy = UnregistrationSource::Manual): bool
    {
        $startedAt = \hrtime(true);

        $this->emitUnregistration(LiveUnregistrationPhase::UnregisterRequested, new LiveUnregistrationResultDTO(
            requestedBy: $requestedBy,
            removed: false,
            failureCount: $this->unregistrationFailureCount,
            stillDispatching: $this->stillDispatchingSameHook(),
        ));

        if (!$this->hasCallbackRegisteredOnWordpress()) {
            $this->emitUnregistration(LiveUnregistrationPhase::UnregisterFailed, new LiveUnregistrationResultDTO(
                requestedBy: $requestedBy,
                removed: false,
                failureCount: $this->unregistrationFailureCount,
                stillDispatching: $this->stillDispatchingSameHook(),
                elapsedUs: 0,
                errorMessage: 'callback_not_registered',
            ));
            return false;
        }

        $result = match ($this->type) {
            'action' => remove_action($this->hook, $this->handler, $this->priority),
            'filter' => remove_filter($this->hook, $this->handler, $this->priority),
        };

        $elapsedUs = (int) ((\hrtime(true) - $startedAt) / 1000);

        if (!$result) {
            $this->unregistrationFailureCount++;
            $this->emitUnregistration(LiveUnregistrationPhase::UnregisterFailed, new LiveUnregistrationResultDTO(
                requestedBy: $requestedBy,
                removed: false,
                failureCount: $this->unregistrationFailureCount,
                stillDispatching: $this->stillDispatchingSameHook(),
                elapsedUs: $elapsedUs,
                errorMessage: 'remove_callback_returned_false',
            ));
            return $result;
        }

        $this->registered = false;
        if (is_callable($this->handler?->onUnregisterDoCallback ?? null)) ($this->handler->onUnregisterDoCallback)();

        $this->emitUnregistration(LiveUnregistrationPhase::Unregistered, new LiveUnregistrationResultDTO(
            requestedBy: $requestedBy,
            removed: true,
            failureCount: $this->unregistrationFailureCount,
            stillDispatching: $this->stillDispatchingSameHook(),
            elapsedUs: (int) ((\hrtime(true) - $startedAt) / 1000),
        ));

        return $result;
    }

    /**
     * Emit one unregistration phase through the invoker that owns the probe.
     */
    private function emitUnregistration(
        LiveUnregistrationPhase $phase,
        ?LiveUnregistrationResultDTO $result = null,
    ): void {
        $handler = $this->handler;
        if ($handler === null) {
            return;
        }

        $handler->emitLiveUnregistrationDebug($phase, $this->registered, $result);
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
