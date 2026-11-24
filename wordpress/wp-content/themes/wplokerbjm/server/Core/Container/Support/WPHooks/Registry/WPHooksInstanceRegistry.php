<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Support\WPHooks\Registry;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use Brick\VarExporter\VarExporter;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\HookKey;
use WPLokerBJM\Core\Container\Support\WPHooks\Invoker\HookInvoker;
use WPLokerBJM\Core\Container\Support\WPHooks\Janitor\QueuedHooksCleaner;
use WPLokerBJM\Core\Container\Support\WPHooks\Provider\WPHookPlanProvider;
use WPLokerBJM\Core\Container\Support\WPHooks\Logger\{HookLoggerInterface, NullHookLogger};
use WPLokerBJM\Core\Container\Support\WPHooks\Utilities\{HookPattern, HookTagUtilities};
use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Trait\{DeferredHooksTrait, HookScannerTrait};
use WPLokerBJM\Core\Container\Support\WPHooks\Indexers\EntriesIndexer;
use WPLokerBJM\Shared\Utilities\SharedUtils;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Resolver\InstanceRegistryRuntimeHookResolver;
use WPLokerBJM\Core\Container\Support\WPHooks\Registrations\InstanceHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Exceptions\NonNullableSelfParameterException;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\Handlers\RuntimeRegistryHandlerEntry;
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\{InstanceRegistryDeferredHookManager};
use WPLokerBJM\Core\Container\Support\WPHooks\Registry\DeferredManager\Handlers\DeferredHookEntryHandler;

/**
 *  @example on Property Hook:
 * ```php
 *  class Service {
 *   public $service { get =>$this->service ??= new class(self::class, __PROPERTY__, $this->registry) extends ModuleClassHookMetadata {
 *       public function __construct(
 *           string $parentClass,
 *           string $propertyName,
 *           private WPHooksInstanceRegistry $registry
 *       ) {
 *           parent::__construct($parentClass,$propertyName); 
 *           $registry->registerHooksOn($this); // register
 *           $registry->unregisterHooksOn($this); // unregister (optional)
 *       }
 *       #[Action(hook: 'init')]
 *       public function onInit(): void { ... }
 *      }
 *  } 
 * ```
 *
 *! `deferRegister` holds the entry in the deferred pool: together with
 *! `deferRegisterUntilHook` it activates automatically when the trigger
 *! fires; on its own it waits for
 *! {@see activateDeferredByHookInLiveInstance()} (one hook),
 *! {@see activateDeferredByHookPatternInLiveInstance()} (matching hooks),
 *! {@see activateDeferredByTagsInLiveInstance()} (matching tags),
 *! {@see activateDeferredByCallableInLiveInstance()} (one member), or
 *! {@see activateDeferredByClassInLiveInstance()} (every deferred member of
 *! the instance). Static methods are
 *! silently skipped.
 *! Hooks are instance-lifetime scoped: the registry keeps only a weak
 *! reference to the owner, so when the instance is garbage-collected the
 *! handler nukes itself from the pool and wp_filter (GC-aware auto-cleanup).
 *! The WPHookPlanProvider resolves closure parameters (hook args by name,
 *! then the container, then defaults) — identical semantics to the container
 *! registry.
 * @phpstan-type ActivateRuntimeEntry \Closure(DeferredHookEntryHandler): bool
 */
class WPHooksInstanceRegistry
{
    use HookScannerTrait;

    public const REGISTER_HOOKS = 'wphooks_instance_register_runtime_hook';
    public const UNREGISTER_HOOKS = 'wphooks_instance_unregister_runtime_hook';
    public const UNREGISTER_BY_HOOK_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_by_hook_in_live_instance';
    public const UNREGISTER_BY_HOOK_PATTERN_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_by_hook_pattern_in_live_instance';
    public const UNREGISTER_BY_CLASS_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_by_class_in_live_instance';
    public const UNREGISTER_BY_TAGS_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_by_tags_in_live_instance';
    public const UNREGISTER_BY_TAG_PATTERN_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_by_tag_pattern_in_live_instance';
    public const UNREGISTER_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_deferred_by_hook_in_live_instance';
    public const UNREGISTER_DEFERRED_BY_HOOK_PATTERN_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_deferred_by_hook_pattern_in_live_instance';
    public const UNREGISTER_DEFERRED_BY_TAGS_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_deferred_by_tags_in_live_instance';
    public const UNREGISTER_DEFERRED_BY_TAG_PATTERN_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_deferred_by_tag_pattern_in_live_instance';
    public const ACTIVATE_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE = 'wphooks_instance_activate_deferred_by_hook_in_live_instance';
    public const ACTIVATE_DEFERRED_BY_HOOK_PATTERN_IN_LIVE_INSTANCE = 'wphooks_instance_activate_deferred_by_hook_pattern_in_live_instance';
    public const ACTIVATE_DEFERRED_BY_TAGS_IN_LIVE_INSTANCE = 'wphooks_instance_activate_deferred_by_tags_in_live_instance';
    public const ACTIVATE_DEFERRED_BY_TAG_PATTERN_IN_LIVE_INSTANCE = 'wphooks_instance_activate_deferred_by_tag_pattern_in_live_instance';
    public const UNREGISTER_BY_CALLABLE_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_by_callable_in_live_instance';
    public const UNREGISTER_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_deferred_by_callable_in_live_instance';
    public const ACTIVATE_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE = 'wphooks_instance_activate_deferred_by_callable_in_live_instance';
    public const UNREGISTER_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE = 'wphooks_instance_unregister_deferred_by_class_in_live_instance';
    public const ACTIVATE_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE = 'wphooks_instance_activate_deferred_by_class_in_live_instance';

    /**
     * Raw control-plane bridge: event name → registry method.
     *
     * Every runtime API method is listed — including REGISTER_HOOKS — so the
     * optional raw bridge is a complete standalone registration path. In
     * production the same listeners come from the `#[Action]` attributes,
     * registered by the container scan, and this bridge is not booted.
     *
     * Selectors that take `($instance, $selector)` register with two accepted
     * arguments; see {@see self::EVENT_BRIDGE_ACCEPTED_ARGS}.
     *
     * @var array<string, non-empty-string>
     */
    public const EVENT_BRIDGE_HOOKS = [
        self::REGISTER_HOOKS => 'registerHooksOn',
        self::UNREGISTER_HOOKS => 'unregisterHooksOn',
        self::UNREGISTER_BY_HOOK_IN_LIVE_INSTANCE => 'unregisterByHookInLiveInstance',
        self::UNREGISTER_BY_HOOK_PATTERN_IN_LIVE_INSTANCE => 'unregisterByHookPatternInLiveInstance',
        self::UNREGISTER_BY_CLASS_IN_LIVE_INSTANCE => 'unregisterByClassInLiveInstance',
        self::UNREGISTER_BY_TAGS_IN_LIVE_INSTANCE => 'unregisterByTagsInLiveInstance',
        self::UNREGISTER_BY_TAG_PATTERN_IN_LIVE_INSTANCE => 'unregisterByTagPatternInLiveInstance',
        self::UNREGISTER_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE => 'unregisterDeferredByHookInLiveInstance',
        self::UNREGISTER_DEFERRED_BY_HOOK_PATTERN_IN_LIVE_INSTANCE => 'unregisterDeferredByHookPatternInLiveInstance',
        self::UNREGISTER_DEFERRED_BY_TAGS_IN_LIVE_INSTANCE => 'unregisterDeferredByTagsInLiveInstance',
        self::UNREGISTER_DEFERRED_BY_TAG_PATTERN_IN_LIVE_INSTANCE => 'unregisterDeferredByTagPatternInLiveInstance',
        self::ACTIVATE_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE => 'activateDeferredByHookInLiveInstance',
        self::ACTIVATE_DEFERRED_BY_HOOK_PATTERN_IN_LIVE_INSTANCE => 'activateDeferredByHookPatternInLiveInstance',
        self::ACTIVATE_DEFERRED_BY_TAGS_IN_LIVE_INSTANCE => 'activateDeferredByTagsInLiveInstance',
        self::ACTIVATE_DEFERRED_BY_TAG_PATTERN_IN_LIVE_INSTANCE => 'activateDeferredByTagPatternInLiveInstance',
        self::UNREGISTER_BY_CALLABLE_IN_LIVE_INSTANCE => 'unregisterByCallableInLiveInstance',
        self::UNREGISTER_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE => 'unregisterDeferredByCallableInLiveInstance',
        self::ACTIVATE_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE => 'activateDeferredByCallableInLiveInstance',
        self::UNREGISTER_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE => 'unregisterDeferredByClassInLiveInstance',
        self::ACTIVATE_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE => 'activateDeferredByClassInLiveInstance',
    ];

    /**
     * Every registration — attribute-discovered — is owned by
     * exactly one object; unregisterHooksOn($owner) removes all of them at once.
     *
     * @var \WeakMap<object, list<RuntimeRegistryHandlerEntry>>
     */
    protected \WeakMap $weakRegistry;
    /** @var \WeakMap<HookInvoker, \WeakReference<RuntimeRegistryHandlerEntry>> */
    protected \WeakMap $entriesByInvokers;
    private bool $bridgeInitialized = false;

    /**
     * Ensures registerHooksOn() runs exactly once per object.
     *
     * @var \WeakMap<object, bool>
     */
    protected \WeakMap $scanned;
    /** @var \WeakReference<self> */
    private \WeakReference $self;
    /**
     * @param WPHookPlanProvider $provider resolves closure parameters from the container
     */
    public function __construct(
        private InstanceRegistryDeferredHookManager $instanceRegistryDeferredHookManager,
        private WPHookPlanProvider $provider,
        private QueuedHooksCleaner $queuedHooksCleaner,
        private InstanceRegistryRuntimeHookResolver $runtimeResolver,
        public readonly WPHooksInstanceObjectCache $cache,
        private readonly HookLoggerInterface $logger = new NullHookLogger(),
    ) {
        $this->weakRegistry = new \WeakMap();
        $this->scanned = new \WeakMap();
        $this->entriesByInvokers = new \WeakMap();
        $this->self = \WeakReference::create($this);
    }

    /**
     * Scan and register hooks on the given object instance.
     *
     * Non-static methods and properties annotated with #[Action] or #[Filter]
     * are registered via add_action() / add_filter() immediately. Calling
     * this on an already-registered instance is a no-op.
     *
     * ```
     * !DO NOT DROP TEMPORAL OBJECT:
     * !WRONG
     * @example
     * ```php
     * $runtimeRegistry->registerHooksOn(new Something(...)); // immediately unregistered after scope exit
     * ```
     * *CORRECT
     * @example
     * ```php
     * $service = new class(...$args) {
     *     #[Action(hook: 'init')]
     *     public function init(): void { ... }
     * }; // retain reference
     * $runtimeRegistry->registerHooksOn($service); // register hooks
     * ```
     * @api
     * @param object|ModuleClassHookMetadata $instance An instantiated object with hook-annotated methods/properties.
     * @param ?string $autoUnregisterAtHook automatic removal of whole hooks in object at certain hook lifecycle
     * @param bool $forceNoCache dynamic instance shape
     */
    #[Action(self::REGISTER_HOOKS, 10, 3)]
    public function registerHooksOn(object $instance, ?string $autoUnregisterAtHook = null, bool $forceNoCache = false): void
    {
        if (isset($this->scanned[$instance])) {
            return;
        }
        /** @var list<InstanceHookMetadata> $metadata */
        $metadata = [];
        /** @var RuntimeRegistryHandlerEntry[] $records */
        $records = [];
        /** @var array<string, int> $attributeOrdinals */
        $attributeOrdinals = [];
        $isModuleSite = $instance instanceof ModuleClassHookMetadata;
        $namedClass = !$isModuleSite && !\str_contains($instance::class, '@anonymous')
            ? $instance::class
            : null;
        $canCache = !$forceNoCache && ($isModuleSite || $namedClass !== null);

        if ($canCache) {
            $cached = $isModuleSite
                ? $this->cache->get($instance->parentClassString, $instance->parentProperty)
                : ($namedClass !== null ? $this->cache->getNamedClass($namedClass) : null);

            if ($cached !== null) {
                $this->scanned[$instance] = true;
                $this->weakRegistry[$instance] = $this->registerCachedEntries($cached, $instance);
                \is_string($autoUnregisterAtHook) && $this->scheduleAutoUnregister($instance, $autoUnregisterAtHook);
                return;
            }
        }

        $ref = new ReflectionClass($instance);

        $this->scanMethodHooks(
            $ref,
            function (\ReflectionMethod $method, Action|Filter $attr, string $visibility, string $type) use ($instance, &$records, &$metadata, $canCache, &$attributeOrdinals): void {
                $targetName = $method->getName();
                $ordinalKey = 'method:' . $targetName;
                $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;

                $entryMeta = new InstanceHookMetadata(
                    hook: $attr->hook,
                    type: $type,
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    once: $attr->once,
                    deferRegister: $attr->deferRegister,
                    executeIf: $attr->executeIf,
                    executeIfParams: $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null),
                    registerIf: $attr->registerIf,
                    registerIfParams: $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null),
                    deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                    deferRegisterUntilHookParams: $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                    hookParams: $this->provider->buildCallablePlan($attr->hook instanceof \Closure ? $attr->hook : null),
                    hookArgNames: $this->provider->buildCallablePlanFromReflector($method)['params'],
                    tags: $attr->tag instanceof \Closure ? [] : $attr->tag,
                    tagCallable: $attr->tag instanceof \Closure ? $attr->tag : null,
                    tagCallableParams: $this->provider->buildCallablePlan($attr->tag instanceof \Closure ? $attr->tag : null),
                    target: 'method',
                    targetName: $targetName,
                    visibility: $visibility,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null),
                    debugDeferredRegistration: $attr->debugDeferredRegistration,
                    debugDeferredRegistrationParams: $this->provider->buildCallablePlan($attr->debugDeferredRegistration instanceof \Closure ? $attr->debugDeferredRegistration : null),
                    debugLiveRegistration: $attr->debugLiveRegistration,
                    debugLiveRegistrationParams: $this->provider->buildCallablePlan($attr->debugLiveRegistration instanceof \Closure ? $attr->debugLiveRegistration : null),
                    debugLiveUnregistration: $attr->debugLiveUnregistration,
                    debugLiveUnregistrationParams: $this->provider->buildCallablePlan($attr->debugLiveUnregistration instanceof \Closure ? $attr->debugLiveUnregistration : null),
                );

                if ($canCache) {
                    $metadata[] = $entryMeta;
                }
                $this->provider->assertNullableSelfClosures($entryMeta, $instance);
                $this->processMetadataEntry($entryMeta, $instance, $attributeIndex, $records);
            }
        );

        $this->scanPropertyHooks(
            $ref,
            function (\ReflectionProperty $property, Action|Filter $attr, string $visibility, string $type, string $target) use ($instance, &$records, &$metadata, $canCache, &$attributeOrdinals): void {
                $targetName = $property->getName();
                $ordinalKey = 'property:' . $targetName;
                $attributeIndex = $attributeOrdinals[$ordinalKey] = ($attributeOrdinals[$ordinalKey] ?? -1) + 1;

                $entryMeta = new InstanceHookMetadata(
                    hook: $attr->hook,
                    type: $type,
                    priority: $attr->priority,
                    acceptedArgs: $attr->acceptedArgs,
                    once: $attr->once,
                    deferRegister: $attr->deferRegister,
                    executeIf: $attr->executeIf,
                    executeIfParams: $this->provider->buildCallablePlan($attr->executeIf instanceof \Closure ? $attr->executeIf : null),
                    registerIf: $attr->registerIf,
                    registerIfParams: $this->provider->buildCallablePlan($attr->registerIf instanceof \Closure ? $attr->registerIf : null),
                    deferRegisterUntilHook: $attr->deferRegisterUntilHook,
                    deferRegisterUntilHookParams: $this->provider->buildCallablePlan($attr->deferRegisterUntilHook instanceof \Closure ? $attr->deferRegisterUntilHook : null),
                    hookParams: $this->provider->buildCallablePlan($attr->hook instanceof \Closure ? $attr->hook : null),
                    hookArgNames: $this->provider->extractPropertyCallablePlan($property, $instance)['params'],
                    tags: $attr->tag instanceof \Closure ? [] : $attr->tag,
                    tagCallable: $attr->tag instanceof \Closure ? $attr->tag : null,
                    tagCallableParams: $this->provider->buildCallablePlan($attr->tag instanceof \Closure ? $attr->tag : null),
                    target: $target,
                    targetName: $targetName,
                    visibility: $visibility,
                    onUnregisterDo: $attr->onUnregisterDo,
                    onUnregisterDoParams: $this->provider->buildCallablePlan($attr->onUnregisterDo instanceof \Closure ? $attr->onUnregisterDo : null),
                    debugDeferredRegistration: $attr->debugDeferredRegistration,
                    debugDeferredRegistrationParams: $this->provider->buildCallablePlan($attr->debugDeferredRegistration instanceof \Closure ? $attr->debugDeferredRegistration : null),
                    debugLiveRegistration: $attr->debugLiveRegistration,
                    debugLiveRegistrationParams: $this->provider->buildCallablePlan($attr->debugLiveRegistration instanceof \Closure ? $attr->debugLiveRegistration : null),
                    debugLiveUnregistration: $attr->debugLiveUnregistration,
                    debugLiveUnregistrationParams: $this->provider->buildCallablePlan($attr->debugLiveUnregistration instanceof \Closure ? $attr->debugLiveUnregistration : null),
                );

                if ($canCache) {
                    $metadata[] = $entryMeta;
                }
                $this->provider->assertNullableSelfClosures($entryMeta, $instance);
                $this->processMetadataEntry($entryMeta, $instance, $attributeIndex, $records);
            }
        );

        $this->scanned[$instance] = true;
        $this->weakRegistry[$instance] = $records;

        if ($canCache && $metadata !== []) {
            if ($isModuleSite) {
                $this->cache->set($instance->parentClassString, $instance->parentProperty, $metadata);
            } elseif ($namedClass !== null) {
                $this->cache->setNamedClass($namedClass, $metadata);
            }
        }
        \is_string($autoUnregisterAtHook) && $this->scheduleAutoUnregister($instance, $autoUnregisterAtHook);
    }
    /**
     * Remove all hooks previously registered for the given instance.
     *
     * Calls remove_action() / remove_filter() for each registered hook and
     * clears internal tracking. Calling this on an instance that was never
     * registered is a no-op.
     * @api
     * @param object|ModuleClassHookMetadata $instance The instance whose hooks should be removed.
     */
    #[Action(self::UNREGISTER_HOOKS)]
    public function unregisterHooksOn(object $instance): void
    {
        (void) $this->instanceRegistryDeferredHookManager->unregisterInstanceClassDeferredEntries($instance);

        if (!isset($this->weakRegistry[$instance])) {
            return;
        }

        foreach ($this->weakRegistry[$instance] as $record) {
            $this->queuedHooksCleaner->immediatelyScheduleRemove($record);
        }

        unset($this->weakRegistry[$instance]);
        unset($this->scanned[$instance]);
    }

    /**
     * Activate every deferred registration owned by one live instance that
     * targets the given WordPress hook name.
     *
     * Scoped to the owner: sibling instances keep their matching deferred
     * entries parked. The `registerIf` gate is re-evaluated immediately
     * before activation, exactly like the instance/callable-scoped variants.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should activate.
     * @param string $hook WordPress hook name.
     * @return int Number of newly activated registrations.
     */
    #[Action(self::ACTIVATE_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE, 10, 2)]
    public function activateDeferredByHookInLiveInstance(object $instance, string $hook): int
    {
        return $this->instanceRegistryDeferredHookManager->activateInstanceHookDeferredEntries(
            $instance,
            $hook,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Activate every deferred registration owned by one live instance whose
     * hook name matches the given wildcard pattern.
     *
     * Pattern rules come from {@see HookPattern}: exactly one trailing `*`
     * with a literal prefix of at least two characters.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should activate.
     * @param string $pattern Wildcard hook-name pattern.
     * @return int Number of newly activated registrations.
     */
    #[Action(self::ACTIVATE_DEFERRED_BY_HOOK_PATTERN_IN_LIVE_INSTANCE, 10, 2)]
    public function activateDeferredByHookPatternInLiveInstance(object $instance, string $pattern): int
    {
        return $this->instanceRegistryDeferredHookManager->activateInstanceHookPatternDeferredEntries(
            $instance,
            $pattern,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Activate every deferred registration owned by one live instance carrying
     * at least one of the supplied tags.
     *
     * Tag matching is OR; tags are normalized through {@see HookTagUtilities}.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should activate.
     * @param array<int, string|\BackedEnum> $tags Tag list.
     * @return int Number of newly activated registrations.
     */
    #[Action(self::ACTIVATE_DEFERRED_BY_TAGS_IN_LIVE_INSTANCE, 10, 2)]
    public function activateDeferredByTagsInLiveInstance(object $instance, array $tags): int
    {
        return $this->instanceRegistryDeferredHookManager->activateInstanceTagsDeferredEntries(
            $instance,
            $tags,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Activate every deferred registration owned by one live instance carrying
     * at least one tag matching any supplied wildcard pattern.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should activate.
     * @param array<int, string> $patterns Tag wildcard patterns.
     * @return int Number of newly activated registrations.
     */
    #[Action(self::ACTIVATE_DEFERRED_BY_TAG_PATTERN_IN_LIVE_INSTANCE, 10, 2)]
    public function activateDeferredByTagPatternInLiveInstance(object $instance, array $patterns): int
    {
        return $this->instanceRegistryDeferredHookManager->activateInstanceTagPatternDeferredEntries(
            $instance,
            $patterns,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Activate every deferred registration bound to one callable target of a
     * live instance (all priorities / attributes of that member).
     *
     * Mirrors {@see WPHooksContainerRegistry::activateDeferredByCallable()} on
     * the runtime path. Entries carrying `deferRegister` without a trigger sit
     * in the deferred pool until this call; entries with
     * `deferRegisterUntilHook` may also be activated early. The `registerIf`
     * gate is re-evaluated immediately before activation, so a rejecting gate
     * leaves the entry parked for a later attempt.
     *
     * @api
     * @param array{0: object, 1: string}|\Closure|object $target `[$instance, 'member']`,
     *        a first-class callable/property-hook closure bound to the live instance,
     *        or an invokable object (targets its `__invoke`).
     * @return int Number of newly activated registrations.
     */
    #[Action(self::ACTIVATE_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE)]
    public function activateDeferredByCallableInLiveInstance(object|callable|array|string $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        return $this->instanceRegistryDeferredHookManager->activateInstanceCallableDeferredEntries(
            $instance,
            $targetName,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Activate every deferred registration owned by one live instance
     * (all members and priorities).
     *
     * Runtime "class scope" is the registration site of the given object:
     * ownership is per instance (`spl_object_id`), so sibling instances of the
     * same class stay deferred. `registerIf` is re-evaluated immediately
     * before each activation, exactly like the callable-scoped variant.
     *
     * @api
     * @param object $target Live instance whose deferred registrations should activate.
     * @return int Number of newly activated registrations.
     */
    #[Action(self::ACTIVATE_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE)]
    public function activateDeferredByClassInLiveInstance(object $target): int
    {
        return $this->instanceRegistryDeferredHookManager->activateInstanceClassDeferredEntries(
            $target,
            $this->activateRuntimeEntry,
        );
    }

    /**
     * Remove every deferred registration owned by one live instance
     * (all members and priorities), keeping active registrations untouched.
     *
     * Use it when the instance scope must not activate later (runtime disable,
     * module teardown) without sweeping hooks that are already live.
     *
     * @api
     * @param object $target Live instance whose deferred registrations should be dropped.
     * @return int Number of removed deferred registrations.
     */
    #[Action(self::UNREGISTER_DEFERRED_BY_CLASS_IN_LIVE_INSTANCE)]
    public function unregisterDeferredByClassInLiveInstance(object $target): int
    {
        return $this->instanceRegistryDeferredHookManager->unregisterInstanceClassDeferredEntries($target);
    }

    /**
     * Remove every deferred registration owned by one live instance that
     * targets the given WordPress hook name, keeping active registrations
     * untouched.
     *
     * Only touches the deferred pool: already-active hooks stay live. Use
     * {@see self::unregisterByHookInLiveInstance()} for the active pool.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should be dropped.
     * @param string $hook WordPress hook name.
     * @return int Number of removed deferred registrations.
     */
    #[Action(self::UNREGISTER_DEFERRED_BY_HOOK_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterDeferredByHookInLiveInstance(object $instance, string $hook): int
    {
        return $this->instanceRegistryDeferredHookManager->unregisterInstanceHookDeferredEntries(
            $instance,
            $hook,
        );
    }

    /**
     * Remove every deferred registration owned by one live instance whose
     * hook name matches the given wildcard pattern, keeping active
     * registrations untouched.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should be dropped.
     * @param string $pattern Wildcard hook-name pattern.
     * @return int Number of removed deferred registrations.
     */
    #[Action(self::UNREGISTER_DEFERRED_BY_HOOK_PATTERN_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterDeferredByHookPatternInLiveInstance(object $instance, string $pattern): int
    {
        return $this->instanceRegistryDeferredHookManager->unregisterInstanceHookPatternDeferredEntries(
            $instance,
            $pattern,
        );
    }

    /**
     * Remove every deferred registration owned by one live instance carrying
     * at least one of the supplied tags, keeping active registrations
     * untouched.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should be dropped.
     * @param array<int, string|\BackedEnum> $tags Tag list.
     * @return int Number of removed deferred registrations.
     */
    #[Action(self::UNREGISTER_DEFERRED_BY_TAGS_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterDeferredByTagsInLiveInstance(object $instance, array $tags): int
    {
        return $this->instanceRegistryDeferredHookManager->unregisterInstanceTagsDeferredEntries(
            $instance,
            $tags,
        );
    }

    /**
     * Remove every deferred registration owned by one live instance carrying
     * at least one tag matching any supplied wildcard pattern.
     *
     * @api
     * @param object $instance Live instance whose deferred registrations should be dropped.
     * @param array<int, string> $patterns Tag wildcard patterns.
     * @return int Number of removed deferred registrations.
     */
    #[Action(self::UNREGISTER_DEFERRED_BY_TAG_PATTERN_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterDeferredByTagPatternInLiveInstance(object $instance, array $patterns): int
    {
        return $this->instanceRegistryDeferredHookManager->unregisterInstanceTagPatternDeferredEntries(
            $instance,
            $patterns,
        );
    }

    /**
     * Remove every active registration owned by one live instance that
     * targets the given WordPress hook name.
     *
     * @api
     * @param object $instance Live instance whose active registrations should be removed.
     * @param string $hook WordPress hook name.
     * @return int Number of removed active registrations.
     *
     * ! Only touches the active pool — deferred entries are never swept and
     * ! still activate when their trigger fires or when
     * ! {@see self::activateDeferredByHookInLiveInstance()} is called. Use
     * ! {@see self::unregisterDeferredByHookInLiveInstance()} for the deferred pool.
     */
    #[Action(self::UNREGISTER_BY_HOOK_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterByHookInLiveInstance(object $instance, string $hook): int
    {
        return $this->removeActiveRecords(
            $instance,
            static fn(RuntimeRegistryHandlerEntry $record): bool => $record->hook === $hook,
        );
    }

    /**
     * Remove every active registration owned by one live instance whose hook
     * name matches the given wildcard pattern.
     *
     * @api
     * @param object $instance Live instance whose active registrations should be removed.
     * @param string $pattern Wildcard hook-name pattern.
     * @return int Number of removed active registrations.
     *
     * ! Only touches the active pool — deferred entries are never swept.
     */
    #[Action(self::UNREGISTER_BY_HOOK_PATTERN_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterByHookPatternInLiveInstance(object $instance, string $pattern): int
    {
        HookPattern::assertValid($pattern);

        return $this->removeActiveRecords(
            $instance,
            static fn(RuntimeRegistryHandlerEntry $record): bool => HookPattern::matches($record->hook, $pattern),
        );
    }

    /**
     * Remove every active registration owned by one live instance carrying at
     * least one of the supplied tags.
     *
     * @api
     * @param object $instance Live instance whose active registrations should be removed.
     * @param array<int, string|\BackedEnum> $tags Tag list.
     * @return int Number of removed active registrations.
     */
    #[Action(self::UNREGISTER_BY_TAGS_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterByTagsInLiveInstance(object $instance, array $tags): int
    {
        $tags = HookTagUtilities::normalizeTags($tags);
        if ($tags === []) return 0;

        return $this->removeActiveRecords(
            $instance,
            static fn(RuntimeRegistryHandlerEntry $record): bool => \array_intersect($tags, $record->tags) !== [],
        );
    }

    /**
     * Remove every active registration owned by one live instance carrying at
     * least one tag matching any supplied wildcard pattern.
     *
     * @api
     * @param object $instance Live instance whose active registrations should be removed.
     * @param array<int, string> $patterns Tag wildcard patterns.
     * @return int Number of removed active registrations.
     */
    #[Action(self::UNREGISTER_BY_TAG_PATTERN_IN_LIVE_INSTANCE, 10, 2)]
    public function unregisterByTagPatternInLiveInstance(object $instance, array $patterns): int
    {
        if ($patterns === []) return 0;
        HookPattern::assertValidAll($patterns);

        return $this->removeActiveRecords(
            $instance,
            static fn(RuntimeRegistryHandlerEntry $record): bool => HookPattern::matchesAny($record->tags, $patterns),
        );
    }

    /**
     * Remove every active registration owned by one live instance, keeping
     * its deferred registrations untouched.
     *
     * Runtime "class scope" is the live owner itself: sibling instances of
     * the same class are never touched. Use
     * {@see self::unregisterDeferredByClassInLiveInstance()} for the deferred
     * pool.
     *
     * @api
     * @param object $instance Live instance whose active registrations should be removed.
     * @return int Number of removed active registrations.
     */
    #[Action(self::UNREGISTER_BY_CLASS_IN_LIVE_INSTANCE)]
    public function unregisterByClassInLiveInstance(object $instance): int
    {
        return $this->removeActiveRecords(
            $instance,
            static fn(RuntimeRegistryHandlerEntry $record): bool => true,
        );
    }

    /**
     * Remove every active hook registration bound to one callable target of a
     * live instance (all priorities / attributes of that member).
     *
     * @api
     * @param array{0: object, 1: string}|\Closure|object $target `[$instance, 'member']`,
     *        a first-class callable/property-hook closure bound to the live instance,
     *        or an invokable object (targets its `__invoke`).
     * @return int Number of removed active registrations.
     *
     * ! Only touches the active pool — deferred entries are never swept and
     * ! still activate when their trigger fires or when
     * ! {@see self::activateDeferredByCallableInLiveInstance()} is called. Use
     * ! {@see self::unregisterDeferredByCallableInLiveInstance()} for the deferred pool.
     */
    #[Action(self::UNREGISTER_BY_CALLABLE_IN_LIVE_INSTANCE)]
    public function unregisterByCallableInLiveInstance(object|callable|array|string $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        return $this->removeActiveRecords(
            $instance,
            fn(RuntimeRegistryHandlerEntry $record): bool => $this->runtimeResolver->matchesCallableTarget(
                $record->hookKey,
                $targetName,
            ),
        );
    }

    /**
     * Remove every active registration matching the supplied predicate.
     *
     * @param callable(RuntimeRegistryHandlerEntry): bool $matches
     * @return int Number of removed active registrations.
     */
    private function removeActiveRecords(object $instance, callable $matches): int
    {
        $records = $this->weakRegistry[$instance] ?? [];
        if ($records === []) {
            return 0;
        }

        $count = 0;
        $remaining = [];

        foreach ($records as $record) {
            if (!$matches($record)) {
                $remaining[] = $record;
                continue;
            }

            $this->queuedHooksCleaner->immediatelyScheduleRemove($record);
            $count++;
        }

        $this->weakRegistry[$instance] = $remaining;

        return $count;
    }

    /**
     * Remove every deferred registration bound to one callable target of a
     * live instance, without touching its active registrations.
     *
     * The counterpart of {@see self::activateDeferredByCallableInLiveInstance()}:
     * use it when the member must not activate later, e.g. after a runtime
     * disable, while still keeping any already-active priorities.
     *
     * @api
     * @param array{0: object, 1: string}|\Closure|object $target `[$instance, 'member']`,
     *        a first-class callable/property-hook closure bound to the live instance,
     *        or an invokable object (targets its `__invoke`).
     * @return int Number of removed deferred registrations.
     */
    #[Action(self::UNREGISTER_DEFERRED_BY_CALLABLE_IN_LIVE_INSTANCE)]
    public function unregisterDeferredByCallableInLiveInstance(object|callable|array|string $target): int
    {
        [$instance, $targetName] = $this->runtimeResolver->resolveLiveCallableTarget($target);

        if ($instance === null || $targetName === null) {
            return 0;
        }

        return $this->instanceRegistryDeferredHookManager->unregisterInstanceCallableDeferredEntries(
            $instance,
            $targetName,
        );
    }

    /**
     * Optionally register the runtime control-plane event bridge with raw WordPress actions.
     * in case if wanted to use this registry via wordpress.
     * * Normally already registered by @see WPHooksContainerRegistry
     * * Init this if @see WPHooksContainerRegistry absent or this registry used independently
     *
     * Idempotent; {@see removeEventBridge()} clears the flag so the bridge can
     * be re-registered.
     *
     * @internal Subject to change
     * @private
     */
    public function initEventBridge(): void
    {
        if ($this->bridgeInitialized) {
            return;
        }
        $this->bridgeInitialized = true;
        $this->registerHooksOn($this);
    }

    /**
     * Remove the runtime control-plane event bridge listeners.
     *
     * Flips `$bridgeInitialized` back to false so {@see initEventBridge()} can
     * boot the bridge again — the pair is a reversible toggle.
     *
     * @internal Subject to change
     * @private
     */
    public function removeEventBridge(): void
    {
        if (!$this->bridgeInitialized) {
            return;
        }
        $this->bridgeInitialized = false;

        $this->unregisterHooksOn($this);
    }

    private function scheduleAutoUnregister(object $instance, string $hook): void
    {
        if (\did_action($hook) >= 1) {
            $this->logger->warning(__METHOD__, 'Hook ' . $hook . ' has passed lifecycle execution, cannot schedule unregister there');
            return;
        }
        $owner = \WeakReference::create($instance);
        $self = $this->self;
        \add_action($hook, static function () use ($owner, $hook, $self): void {
            if (($instance = $owner->get()) !== null) {
                $self->get()?->unregisterHooksOn($instance);
            }
            \remove_action($hook, \Closure::getCurrent(), PHP_INT_MIN);
        }, PHP_INT_MIN, 0);
    }

    /**
     * Re-hydrate cached metadata entries into live handlers bound to
     * $instance, applying the registerIf gate per entry. Entries carrying
     * `deferRegisterUntilHook` are re-hydrated into the deferred pool
     * (trigger listener included) instead of being registered eagerly.
     *
     * @param list<InstanceHookMetadata> $entries Cached metadata entries.
     * @param object|ModuleClassHookMetadata $instance Owner instance to bind handlers to.
     *
     * @return list<RuntimeRegistryHandlerEntry>
     */
    private function registerCachedEntries(array $entries, object $instance): array
    {
        /** @var list<RuntimeRegistryHandlerEntry> $records */
        $records = [];

        foreach ($entries as $attributeIndex => $entry) {
            $this->processMetadataEntry($entry, $instance, $attributeIndex, $records);
        }

        return $records;
    }

    /**
     * Process an individual hook metadata entry: checks the registration gate, 
     * creates the handler and entry via createHandlerAndEntry, and registers 
     * or defers the hook.
     */
    private function processMetadataEntry(
        InstanceHookMetadata $entryMeta,
        object $instance,
        int $attributeIndex,
        array &$records
    ): void {
        $isDeferred = $entryMeta->deferRegister || $entryMeta->deferRegisterUntilHook !== null;

        if (!$isDeferred) {
            $gatePassed = $this->provider->evaluateRegistrationGate(
                $entryMeta->registerIf,
                $entryMeta->registerIfParams ?? $this->provider->buildCallablePlan($entryMeta->registerIf instanceof \Closure ? $entryMeta->registerIf : null),
                $entryMeta->targetName,
                $instance
            );
            if (!$gatePassed) {
                $ownerLabel = $instance instanceof ModuleClassHookMetadata
                    ? $instance->parentClassString . '->' . $instance->parentProperty
                    : $instance::class;
                $this->logger->info(
                    __METHOD__,
                    'registerIf for ' . $ownerLabel . '->' . $entryMeta->targetName . ' with hook ' . self::describeHook($entryMeta->hook) . ' and priority ' . (string) $entryMeta->priority . ' skipped'
                );
                return;
            }
        }

        try {
            [$handler, $record] = $this->createHandlerAndEntry($entryMeta, $instance, $attributeIndex, $isDeferred);
        } catch (\Throwable $e) {
            $this->logger->error(
                __METHOD__,
                'Skipping hook ' . $entryMeta->targetName . ' on ' . self::describeHook($entryMeta->hook) . ' — ' . $e->getMessage(),
            );

            return;
        }

        if ($isDeferred) {
            $this->instanceRegistryDeferredHookManager->deferManualRegistration(
                entry: $record,
                deferRegisterUntilHook: $entryMeta->deferRegisterUntilHook,
                activateEntry: $this->activateRuntimeEntry
            );
        } else {
            $records[] = $record->register();
            $weakRefRecord = \WeakReference::create($record);
            $this->entriesByInvokers[$handler] = $weakRefRecord;
            $this->queuedHooksCleaner->addRuntimeRegistryItsEntries([$weakRefRecord]);
        }
    }

    /**
     * Human-readable hook name for logs. Dynamic hooks are still closures at
     * this point, so they cannot be interpolated directly.
     */
    private static function describeHook(string|\Closure $hook): string
    {
        return $hook instanceof \Closure ? '(closure)' : $hook;
    }

    /**
     * Resolve the effective tag list for one metadata entry.
     *
     * Static tags and a tag closure are normalized/resolved per registration,
     * so cached metadata stays safe for instance-specific tags.
     *
     * @return array<int, string>
     */
    private function resolveInstanceTags(InstanceHookMetadata $metadata, object $instance): array
    {
        if ($metadata->tagCallable === null) return HookTagUtilities::normalizeTags($metadata->tags ?? []);

        return HookTagUtilities::normalizeTags(
            $this->provider->resolveTagCallable(
                $metadata->tagCallable,
                $metadata->tagCallableParams ?? [],
                $metadata->targetName,
                $instance,
            ),
        );
    }

    /**
     * Resolve a dynamic hook name against the live owner at registration time.
     *
     * Cached metadata keeps the raw closure and its callable plan, so sibling
     * instances never inherit the first instance's resolved hook name.
     */
    private function resolveInstanceHookName(InstanceHookMetadata $metadata, object $instance): string
    {
        if (!$metadata->hook instanceof \Closure) return $metadata->hook;

        $ownerLabel = $instance instanceof ModuleClassHookMetadata
            ? $instance->parentClassString . '->' . $instance->parentProperty
            : $instance::class;

        return $this->provider->resolveHookName(
            $metadata->hook,
            $metadata->hookParams ?? [],
            $ownerLabel . '->' . $metadata->targetName,
            $instance,
        );
    }

    /**
     * @param bool $isDeferred
     * @return array{
     *  HookInvoker, 
     *  ($isDeferred is true ? DeferredHookEntryHandler : RuntimeRegistryHandlerEntry)
     * }
     */
    private function createHandlerAndEntry(
        InstanceHookMetadata $metadata,
        object $instance,
        int $attributeIndex,
        bool $isDeferred = false
    ): array {
        $weakRef = \WeakReference::create($instance);
        $hookName = $this->resolveInstanceHookName($metadata, $instance);
        $resolvedTags = $this->resolveInstanceTags($metadata, $instance);
        $hookKey = new HookKey(
            class: (string) \spl_object_id($instance),
            method: $metadata->targetName,
            target: $metadata->target,
            priority: $metadata->priority,
            type: $metadata->type,
            acceptedArgs: $metadata->acceptedArgs,
            attributeIndex: $attributeIndex,
        );
        $handler = match ($metadata->target) {
            'method' => HookInvoker::runtimeMethod(
                instanceRef: $weakRef,
                hookName: $hookName,
                priority: $metadata->priority,
                method: $metadata->targetName,
                visibility: $metadata->visibility,
                type: $metadata->type,
                acceptedArgs: $metadata->acceptedArgs,
                executeIf: $metadata->executeIf,
                hookPlanProvider: $this->provider,
                executeIfParams: $metadata->executeIfParams,
                hookArgNames: $metadata->hookArgNames,
                once: $metadata->once,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
                debugDeferredRegistration: $metadata->debugDeferredRegistration,
                debugDeferredRegistrationParams: $metadata->debugDeferredRegistrationParams,
                debugLiveRegistration: $metadata->debugLiveRegistration,
                debugLiveRegistrationParams: $metadata->debugLiveRegistrationParams,
                debugLiveUnregistration: $metadata->debugLiveUnregistration,
                debugLiveUnregistrationParams: $metadata->debugLiveUnregistrationParams,
                logger: $this->logger,
            ),
            'property', 'property-hook' => HookInvoker::runtimeProperty(
                instanceRef: $weakRef,
                hookName: $hookName,
                priority: $metadata->priority,
                property: $metadata->targetName,
                visibility: $metadata->visibility,
                type: $metadata->type,
                acceptedArgs: $metadata->acceptedArgs,
                executeIf: $metadata->executeIf,
                hookPlanProvider: $this->provider,
                executeIfParams: $metadata->executeIfParams,
                hookArgNames: $metadata->hookArgNames,
                once: $metadata->once,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
                debugDeferredRegistration: $metadata->debugDeferredRegistration,
                debugDeferredRegistrationParams: $metadata->debugDeferredRegistrationParams,
                debugLiveRegistration: $metadata->debugLiveRegistration,
                debugLiveRegistrationParams: $metadata->debugLiveRegistrationParams,
                debugLiveUnregistration: $metadata->debugLiveUnregistration,
                debugLiveUnregistrationParams: $metadata->debugLiveUnregistrationParams,
                logger: $this->logger,
            ),
        };
        $entry = match ($isDeferred) {
            true => new DeferredHookEntryHandler(
                hook: $hookName,
                handler: $handler,
                type: $metadata->type,
                priority: $metadata->priority,
                acceptedArgs: $metadata->acceptedArgs,
                tags: $resolvedTags,
                registerIf: $metadata->registerIf,
                registerIfParams: $metadata->registerIfParams,
                executeIf: $metadata->executeIf,
                executeIfParams: $metadata->executeIfParams,
                once: $metadata->once,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
                key: $hookKey,
                instance: $weakRef,
                logger: $this->logger,
            ),
            default => new RuntimeRegistryHandlerEntry(
                hook: $hookName,
                handler: $handler,
                type: $metadata->type,
                priority: $metadata->priority,
                acceptedArgs: $metadata->acceptedArgs,
                tags: $resolvedTags,
                registerIf: $metadata->registerIf,
                registerIfParams: $metadata->registerIfParams,
                once: $metadata->once,
                executeIf: $metadata->executeIf,
                executeIfParams: $metadata->executeIfParams,
                onUnregisterDo: $metadata->onUnregisterDo,
                onUnregisterDoParams: $metadata->onUnregisterDoParams,
                hookKey: $hookKey,
                owner: $weakRef,
                logger: $this->logger,
            ),
        };
        $handler->setCleanupCallback($this->removeRuntimeHook);
        return [$handler, $entry];
    }

    /**
     * Activate a deferred runtime entry: register the handler with WordPress
     * and record it under the owner instance.
     * @var ActivateRuntimeEntry
     * @return bool True when the entry was activated.
     */
    private ?\Closure $activateRuntimeEntry = null {
        get {
            if ($this->activateRuntimeEntry !== null && $this->self->get() === $this) return $this->activateRuntimeEntry;
            $self = $this->self;
            return $this->activateRuntimeEntry = static function (DeferredHookEntryHandler $data) use ($self): bool {
                $self = $self->get() ?? null;
                if ($self === null) return false;
                $owner = $data->instance?->get();
                if ($owner === null) return false;

                $record = RuntimeRegistryHandlerEntry::fromDeferredEntryDTO($data);
                $weakRefRecord = \WeakReference::create($record);

                $records = $self->weakRegistry[$owner] ?? [];
                $records[] = $record->register();
                $self->weakRegistry[$owner] = $records;
                $self->queuedHooksCleaner->addRuntimeRegistryItsEntries([$weakRefRecord]);
                $self->entriesByInvokers[$record->handler] = $weakRefRecord;
                SharedUtils::isDevelopment() && $self->logger->debug(__METHOD__, 'Activated deferred hook ' . $data->handler->label);

                return true;
            };
        }
    }

    /**
     * Detach a runtime handler from WordPress (once-consume or
     * instance-lifetime cleanup). The owner is passed as a WeakReference so
     * the callback never keeps the instance alive — only the record drop
     * touches it, and only when it still exists.
     * @internal
     * @var (\Closure(HookInvoker, bool = false): void) $removeRuntimeHook
     */
    private ?\Closure $removeRuntimeHook = null {
        get {
            if ($this->removeRuntimeHook !== null && $this->self->get() === $this) return $this->removeRuntimeHook;
            $self = $this->self;
            return $this->removeRuntimeHook = static function (
                HookInvoker $invoker,
                bool $invokerDied = false,
            ) use ($self): void {
                $self = $self->get() ?? null;
                if ($self === null) return;
                $entry = $self->queuedHooksCleaner->getEntry($invoker)
                    ?? isset($self->entriesByInvokers[$invoker])
                    ? $self->entriesByInvokers[$invoker]->get() : null;
                if ($entry === null) return;

                if ($invokerDied) {
                    unset($self->entriesByInvokers[$invoker]);
                    $self->queuedHooksCleaner->immediatelyScheduleRemove($entry);
                    $self->cleanUpInstance($entry);
                    SharedUtils::isDevelopment() && $self->logger->debug(__METHOD__, "Entry Died: {$invoker->label}");
                    return;
                }

                /**
                 * Skip unregister hook while still in the same callback stack
                 * 
                 * Skip the WordPress-side removal while the hook is being dispatched:
                 * self removal during dispatch corrupts WP_Hook's iteration
                 * (resort_active_iterations skips the immediately-following priority).
                 * The consumed flag already prevents re-firing, so the callback can
                 * safely linger in $wp_filter until the next invoker order or beginning hook(all).
                 * @link https://core.trac.wordpress.org/ticket/64653
                 */
                $self->queuedHooksCleaner->cleanUpCallback($invoker);
                $self->cleanUpInstance($entry);
            };
        }
    }

    private function cleanUpInstance(RuntimeRegistryHandlerEntry $entry): void
    {
        $owner = $entry->owner?->get();
        if ($owner === null || !isset($this->weakRegistry[$owner])) return;
        $this->weakRegistry[$owner] = array_filter(
            $this->weakRegistry[$owner],
            static fn(RuntimeRegistryHandlerEntry $record): bool => $record !== $entry,
        );
    }
}
