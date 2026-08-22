<?php

namespace WPLokerBJM\Core;

use WPLokerBJM\Core\Container\Attributes\Action;
use WPLokerBJM\Core\Container\Attributes\Filter;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\Abstract\AsChildClass;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\AnonClassHookMetadata;
use WPLokerBJM\Core\Container\Support\InstanceDiscovery\DependencyInjector;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksRuntimeRegistry;

/**
 * @suppress PHP7104
 */
class ContainerRegistryActions
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
    private function activateDeferredByHook(string $hook): void
    {
        $this->containerRegistry->activateDeferredByHook($hook);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_CLASS, 10, 1)]
    private function activateDeferredByClass(string $class): void
    {
        $this->containerRegistry->activateDeferredByClass($class);
    }

    #[Action(self::ACTIVATE_DEFERRED_BY_CALLABLE, 10, 1)]
    private function activateDeferredByCallable(callable|string|array $target): void
    {
        $this->containerRegistry->activateDeferredByCallable($target);
    }

    #[Filter(self::ACTIVATE_DEFERRED_BY_TAGS, 10, 1)]
    private function activateDeferredByTags(array $tags): int
    {
        return $this->containerRegistry->activateDeferredByTags($tags);
    }

    #[Filter(self::ACTIVATE_DEFERRED_BY_NAMESPACE, 10, 1)]
    private function activateDeferredByNamespace(string $namespace): int
    {
        return $this->containerRegistry->activateDeferredByNamespace($namespace);
    }

    #[Filter(self::ACTIVATE_DEFERRED_BY_HOOK_PATTERN, 10, 1)]
    private function activateDeferredByHookPattern(string $pattern): int
    {
        return $this->containerRegistry->activateDeferredByHookPattern($pattern);
    }

    #[Filter(self::ACTIVATE_DEFERRED_BY_TAG_PATTERN, 10, 1)]
    private function activateDeferredByTagPattern(array $patterns): int
    {
        return $this->containerRegistry->activateDeferredByTagPattern($patterns);
    }
    #endregion

    #region deferred handlers unregistration methods
    #[Action(self::UNREGISTER_DEFERRED_BY_CALLABLE, 10, 1)]
    private function unregisterDeferredByCallable(callable|string|array $target): void
    {
        $this->containerRegistry->unregisterDeferredByCallable($target);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_HOOK, 10, 1)]
    private function unregisterDeferredByHook(string $hook): void
    {
        $this->containerRegistry->unregisterDeferredByHook($hook);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_CLASS, 10, 1)]
    private function unregisterDeferredByClass(string $class): void
    {
        $this->containerRegistry->unregisterDeferredByClass($class);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_NAMESPACE, 10, 1)]
    private function unregisterDeferredByNamespace(string $namespace): void
    {
        $this->containerRegistry->unregisterDeferredByNamespace($namespace);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_TAGS, 10, 1)]
    private function unregisterDeferredByTags(array $tags): void
    {
        $this->containerRegistry->unregisterDeferredByTags($tags);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_HOOK_PATTERN, 10, 1)]
    private function unregisterDeferredByHookPattern(string $pattern): void
    {
        $this->containerRegistry->unregisterDeferredByHookPattern($pattern);
    }

    #[Action(self::UNREGISTER_DEFERRED_BY_TAG_PATTERN, 10, 1)]
    private function unregisterDeferredByTagPattern(array $patterns): void
    {
        $this->containerRegistry->unregisterDeferredByTagPattern($patterns);
    }
    #endregion

    #region active handlers unregistration methods
    #[Action(self::UNREGISTER_BY_CALLABLE, 10, 1)]
    private function unregisterByCallable(callable|string|array $target): void
    {
        $this->containerRegistry->unregisterByCallable($target);
    }

    #[Action(self::UNREGISTER_BY_HOOK, 10, 1)]
    private function unregisterByHook(string $hook): void
    {
        $this->containerRegistry->unregisterByHook($hook);
    }

    #[Action(self::UNREGISTER_BY_CLASS, 10, 1)]
    private function unregisterByClass(string $class): void
    {
        $this->containerRegistry->unregisterByClass($class);
    }

    #[Action(self::UNREGISTER_BY_NAMESPACE, 10, 1)]
    private function unregisterByNamespace(string $namespace): void
    {
        $this->containerRegistry->unregisterByNamespace($namespace);
    }

    #[Action(self::UNREGISTER_BY_TAGS, 10, 1)]
    private function unregisterByTags(array $tags): void
    {
        $this->containerRegistry->unregisterByTags($tags);
    }

    #[Action(self::UNREGISTER_BY_HOOK_PATTERN, 10, 1)]
    private function unregisterByHookPattern(string $pattern): void
    {
        $this->containerRegistry->unregisterByHookPattern($pattern);
    }

    #[Action(self::UNREGISTER_BY_TAG_PATTERN, 10, 1)]
    private function unregisterByTagPattern(array $patterns): void
    {
        $this->containerRegistry->unregisterByTagPattern($patterns);
    }
    #endregion
}

/**
 * @suppress PHP7104
 */
class HooksRuntimeRegistryActions
{
    public const REGISTER_HOOKS = 'wplokerbjm_register_runtime_hook';
    public const UNREGISTER_HOOKS = 'wplokerbjm_unregister_runtime_hook';

    public function __construct(private readonly WPHooksRuntimeRegistry $runtimeRegistry) {}

    #[Action(self::REGISTER_HOOKS, 10, 1)]
    private function registerRuntimeHook(AnonClassHookMetadata $target): void
    {
        $this->runtimeRegistry->registerHooksOn($target);
    }
    #[Action(self::UNREGISTER_HOOKS, 10, 1)]
    private function unregisterRuntimeHook(AnonClassHookMetadata $target): void
    {
        $this->runtimeRegistry->unregisterHooksOn($target);
    }
}

/**
 * @suppress PHP7104
 */
class DependencyInjectorHookActions
{
    public const INJECT_ON = 'wplokerbjm_inject_on';
    public function __construct(private readonly DependencyInjector $injector) {}

    #[Action(self::INJECT_ON, 10, 1)]
    private function injectOn(AsChildClass $target): void
    {
        $this->injector->injectOn($target);
    }
}
