<?php

namespace WPLokerBJM\Core\Wordpress;

use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\{WPHooksContainerRegistry, WPHooksInstanceRegistry};

/**
 * @suppress PHP7104
 */
class ContainerRegistryHookEvent
{
    #region deferred handlers activation hooks
    public const ACTIVATE_DEFERRED_BY_HOOK = 'wplokerbjm_container_activate_deferred_by_hook';
    public const ACTIVATE_DEFERRED_BY_CLASS = 'wplokerbjm_container_activate_deferred_by_class';
    public const ACTIVATE_DEFERRED_BY_CALLABLE = 'wplokerbjm_container_activate_deferred_by_callable';
    public const ACTIVATE_DEFERRED_BY_TAGS = 'wplokerbjm_container_activate_deferred_by_tags';
    public const ACTIVATE_DEFERRED_BY_NAMESPACE = 'wplokerbjm_container_activate_deferred_by_namespace';
    public const ACTIVATE_DEFERRED_BY_HOOK_PATTERN = 'wplokerbjm_container_activate_deferred_by_hook_pattern';
    public const ACTIVATE_DEFERRED_BY_TAG_PATTERN = 'wplokerbjm_container_activate_deferred_by_tag_pattern';
    #endregion

    #region deferred handlers unregistration hooks
    public const UNREGISTER_DEFERRED_BY_CALLABLE = 'wplokerbjm_container_unregister_deferred_by_callable';
    public const UNREGISTER_DEFERRED_BY_HOOK = 'wplokerbjm_container_unregister_deferred_by_hook';
    public const UNREGISTER_DEFERRED_BY_CLASS = 'wplokerbjm_container_unregister_deferred_by_class';
    public const UNREGISTER_DEFERRED_BY_NAMESPACE = 'wplokerbjm_container_unregister_deferred_by_namespace';
    public const UNREGISTER_DEFERRED_BY_TAGS = 'wplokerbjm_container_unregister_deferred_by_tags';
    public const UNREGISTER_DEFERRED_BY_HOOK_PATTERN = 'wplokerbjm_container_unregister_deferred_by_hook_pattern';
    public const UNREGISTER_DEFERRED_BY_TAG_PATTERN = 'wplokerbjm_container_unregister_deferred_by_tag_pattern';
    #endregion

    #region active handlers unregistration hooks
    public const UNREGISTER_BY_CALLABLE = 'wplokerbjm_container_unregister_by_callable';
    public const UNREGISTER_BY_HOOK = 'wplokerbjm_container_unregister_by_hook';
    public const UNREGISTER_BY_CLASS = 'wplokerbjm_container_unregister_by_class';
    public const UNREGISTER_BY_NAMESPACE = 'wplokerbjm_container_unregister_by_namespace';
    public const UNREGISTER_BY_TAGS = 'wplokerbjm_container_unregister_by_tags';
    public const UNREGISTER_BY_HOOK_PATTERN = 'wplokerbjm_container_unregister_by_hook_pattern';
    public const UNREGISTER_BY_TAG_PATTERN = 'wplokerbjm_container_unregister_by_tag_pattern';
    #endregion

    public function __construct(private readonly WPHooksContainerRegistry $containerRegistry) {}

    #region deferred handlers activation methods
    #[Action(self::ACTIVATE_DEFERRED_BY_HOOK, 10, 1)]
    protected function activateDeferredByHook(string $hook): void
    {
        $this->containerRegistry->activateDeferredByHook($hook);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_CLASS, 10, 1)]
    protected function activateDeferredByClass(string $class): void
    {
        $this->containerRegistry->activateDeferredByClass($class);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_CALLABLE, 10, 1)]
    protected function activateDeferredByCallable(callable|string|array $target): void
    {
        $this->containerRegistry->activateDeferredByCallable($target);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_TAGS, 10, 1)]
    protected function activateDeferredByTags(array $tags): void
    {
        $this->containerRegistry->activateDeferredByTags($tags);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_NAMESPACE, 10, 1)]
    protected function activateDeferredByNamespace(string $namespace): void
    {
        $this->containerRegistry->activateDeferredByNamespace($namespace);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_HOOK_PATTERN, 10, 1)]
    protected function activateDeferredByHookPattern(string $pattern): void
    {
        $this->containerRegistry->activateDeferredByHookPattern($pattern);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_TAG_PATTERN, 10, 1)]
    protected function activateDeferredByTagPattern(array $patterns): void
    {
        $this->containerRegistry->activateDeferredByTagPattern($patterns);
    }
    #endregion

    #region deferred handlers unregistration methods
    #[Action(self::UNREGISTER_DEFERRED_BY_CALLABLE, 10, 1)]
    protected function unregisterDeferredByCallable(callable|string|array $target): void
    {
        $this->containerRegistry->unregisterDeferredByCallable($target);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_HOOK, 10, 1)]
    protected function unregisterDeferredByHook(string $hook): void
    {
        $this->containerRegistry->unregisterDeferredByHook($hook);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_CLASS, 10, 1)]
    protected function unregisterDeferredByClass(string $class): void
    {
        $this->containerRegistry->unregisterDeferredByClass($class);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_NAMESPACE, 10, 1)]
    protected function unregisterDeferredByNamespace(string $namespace): void
    {
        $this->containerRegistry->unregisterDeferredByNamespace($namespace);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_TAGS, 10, 1)]
    protected function unregisterDeferredByTags(array $tags): void
    {
        $this->containerRegistry->unregisterDeferredByTags($tags);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_HOOK_PATTERN, 10, 1)]
    protected function unregisterDeferredByHookPattern(string $pattern): void
    {
        $this->containerRegistry->unregisterDeferredByHookPattern($pattern);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_TAG_PATTERN, 10, 1)]
    protected function unregisterDeferredByTagPattern(array $patterns): void
    {
        $this->containerRegistry->unregisterDeferredByTagPattern($patterns);
    }
    #endregion

    #region active handlers unregistration methods
    #[Action(self::UNREGISTER_BY_CALLABLE, 10, 1)]
    protected function unregisterByCallable(callable|string|array $target): void
    {
        $this->containerRegistry->unregisterByCallable($target);
    }

    #[Action(self::UNREGISTER_BY_HOOK, 10, 1)]
    protected function unregisterByHook(string $hook): void
    {
        $this->containerRegistry->unregisterByHook($hook);
    }

    #[Action(self::UNREGISTER_BY_CLASS, 10, 1)]
    protected function unregisterByClass(string $class): void
    {
        $this->containerRegistry->unregisterByClass($class);
    }

    #[Action(self::UNREGISTER_BY_NAMESPACE, 10, 1)]
    protected function unregisterByNamespace(string $namespace): void
    {
        $this->containerRegistry->unregisterByNamespace($namespace);
    }

    #[Action(self::UNREGISTER_BY_TAGS, 10, 1)]
    protected function unregisterByTags(array $tags): void
    {
        $this->containerRegistry->unregisterByTags($tags);
    }

    #[Action(self::UNREGISTER_BY_HOOK_PATTERN, 10, 1)]
    protected function unregisterByHookPattern(string $pattern): void
    {
        $this->containerRegistry->unregisterByHookPattern($pattern);
    }

    #[Action(self::UNREGISTER_BY_TAG_PATTERN, 10, 1)]
    protected function unregisterByTagPattern(array $patterns): void
    {
        $this->containerRegistry->unregisterByTagPattern($patterns);
    }
    #endregion
}

/**
 * @suppress PHP7104
 */
class InstanceRuntimeRegistryHookEvent
{
    public const REGISTER_HOOKS = 'wplokerbjm_register_runtime_hook';
    public const UNREGISTER_HOOKS = 'wplokerbjm_unregister_runtime_hook';

    public function __construct(private readonly WPHooksInstanceRegistry $runtimeRegistry) {}

    #[Action(self::REGISTER_HOOKS, 10, 2)]
    protected function registerRuntimeHook(ModuleClassHookMetadata $target, ?string $autoUnregisterAtHook = null): void
    {
        $this->runtimeRegistry->registerHooksOn($target, $autoUnregisterAtHook);
    }
    #[Action(self::UNREGISTER_HOOKS, 10, 1)]
    protected function unregisterRuntimeHook(ModuleClassHookMetadata $target): void
    {
        $this->runtimeRegistry->unregisterHooksOn($target);
    }
}

/**
 * @suppress PHP7104
 */
class DependencyInjectorEvent
{
    public const INJECT_ON = 'wplokerbjm_inject_on';
    public function __construct(private readonly DependencyInjector $injector) {}

    #[Action(self::INJECT_ON, 10, 1)]
    protected function injectOn(AsChildClass $target): void
    {
        $this->injector->injectOn($target);
    }
}
