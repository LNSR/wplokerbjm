<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleHookMetadata;
use WPLokerBJM\Core\Container\Support\WPHooks\Debug\{DeferredRegistrationInfoDTO, LiveRegistrationInfoDTO, LiveUnregistrationInfoDTO};
use WPLokerBJM\Core\Container\Support\WPHooks\WPHooksScanner;

/**
 * Declarative WordPress hook binding: `#[Action]` / `#[Filter]` on instance
 * methods and properties.
 *
 * The scanner turns every attribute into a `HookRegistration`; one of the two
 * registries then attaches it to WordPress. Attributes are inert on their own —
 * they only describe *what* to register, never perform the registration.
 *
 * ---------------------------------------------------------------------------
 * ## Two registries, one attribute
 * ---------------------------------------------------------------------------
 *
 * - `WPHooksContainerRegistry(primary registry)` — attribute-discovered **services**. The handler/invoker
 *   is a lazy proxy: the service is resolved with `$container->get()` on first
 *   dispatch, so its constructor (DB clients, HTTP clients, …) does not run
 *   until the hook actually fires. Supports tags, deferred activation and the
 *   batch activation/unregistration APIs.
 * - `WPHooksInstanceRegistry(secondary registry)` — **runtime instances**, normally anonymous
 *   classes extending {@see ModuleHookMetadata}. The handler keeps only a
 *   `WeakReference` to the live instance; when that instance is garbage
 *   collected the handler becomes inert and detaches itself. Supports tags,
 *   deferred activation and owner-scoped selectors.
 *
 * The same member is scanned by exactly one registry — never both.
 *
 * ---------------------------------------------------------------------------
 * ## Closure rules (every closure parameter in this attribute)
 * ---------------------------------------------------------------------------
 *
 * Attribute arguments must be valid constant expressions, so every closure here
 * MUST be `static` and cannot capture `$this`. It is still scoped to the
 * declaring class, so `self::`, private constants and private static helpers of
 * that class are reachable.
 *
 * - Closure is internally cached, static `$variables` persist across all execution cycles
 *
 * Closures execute in three very different moments:
 *
 * - `$hook`, `$tag`, `$deferRegisterUntilHook` → **eagerly**, during
 *   registration (boot and cache generation). Keep them pure and cheap:
 *   no I/O, no request-dependent state, deterministic result.
 * - `$registerIf` → at **registration** time, and again at **every** deferred
 *   activation attempt.
 * - `$executeIf` → at **dispatch** time, once per invocation.
 *
 * Container-backed closures resolve parameters by type from the DI container
 * (`$container->has($type)` → `get($type)`), then fall back to the parameter
 * default, then throw. A parameter typed with a non-class type (`string`,
 * `int`, `array`, …) and no default is therefore unresolvable.
 *
 * **InstanceRegistry contract:** every owner parameter on an instance-registry
 * closure MUST be declared `?self`. The owner lives behind a `WeakReference`
 * and may already be gone (or be mid-teardown) when a deferred gate,
 * `$onUnregisterDo`, or a debug probe runs. Non-nullable `self`/`static`
 * parameters are rejected at registration time with
 * {@see \WPLokerBJM\Core\Container\Support\WPHooks\Exceptions\NonNullableSelfParameterException},
 * and `self` is never fabricated from the container on the instance path.
 * Container-backed closures keep the documented `self` → container fallback.
 *
 * ---------------------------------------------------------------------------
 * ## Scanning rules
 * ---------------------------------------------------------------------------
 *
 * - Only members **declared on the class** are scanned. Inherited hook methods
 *   and properties are ignored — a subclass must re-declare them (with its own
 *   attribute and a `parent::method()` call) to opt in.
 * - `static` members are skipped silently.
 * - Magic methods other than `__invoke` are rejected with a
 *   `RuntimeException` at scan time (`__construct` would double-construct,
 *   `__get`/`__call`/`__toString` are nonsensical as lazy handlers).
 *   `__invoke` **is** supported.
 * - `private` / `protected` members are supported (invokers bind a closure into
 *   the declaring scope).
 * - `Action` and `Filter` are `IS_REPEATABLE`: stacking several attributes on
 *   one member registers several hooks — that is the intended multi-priority
 *   pattern.
 *
 * ---------------------------------------------------------------------------
 * ## Feature matrix
 * ---------------------------------------------------------------------------
 *
 * | Feature                   | ContainerRegistry | InstanceRegistry        |
 * |---------------------------|-------------------|-------------------------|
 * | `$hook` closure           | yes               | yes                     |
 * | `$tag`                    | yes               | yes                     |
 * | `$deferRegister`          | yes               | yes                     |
 * | `$deferRegisterUntilHook` | yes               | yes                     |
 * | `$registerIf`             | yes               | yes                     |
 * | `$executeIf`              | yes               | yes                     |
 * | `$once`                   | yes               | yes                     |
 * | `$onUnregisterDo`         | yes               | yes                     |
 * | `$debugDeferredRegistration` | yes            | yes                     |
 * | `$debugLiveRegistration`  | yes               | yes                     |
 * | `$debugLiveUnregistration` | yes              | yes                     |
 *
 * ---------------------------------------------------------------------------
 * ## Known limitations
 * ---------------------------------------------------------------------------
 *
 * - **WordPress does not distinguish actions from filters.** Stacking
 *   `#[Action]` and `#[Filter]` on the *same* hook name registers two callbacks
 *   and both run for `do_action()` and `apply_filters()` alike. The attribute
 *   class only affects internal bookkeeping (`HookKey`) and the invoker's
 *   pass-through behaviour.
 * - **`$executeIf` hook-argument injection is unreliable on `get`-hooked
 *   properties** — see that parameter below.
 * - **Closures cannot capture `$this`.** Use a container service, or the
 *   instance registry, when instance state is required.
 * - **A deferred entry that nothing activates never registers**, silently.
 * - **Registration-time work is boot-time work**: `hook`/`tag`/
 *   `deferRegisterUntilHook` closures run on every cold boot during
 *   registration. Keep them deterministic.
 *
 * Required PHP: >= 8.5.
 *
 * ! Recommended to boot from the MU-Plugin so scanning happens before any
 * ! plugin registers its own hooks.
 *
 * @see WPHooksScanner
 * @see \WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry
 * @see \WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry
 * @see ModuleHookMetadata
 *
 * @template TParams
 */
abstract class BaseHookAttribute
{
    /**
     * Attribute autocomplete DX is generated by `tools/Composer/Scripts/generate-meta-hooks.php`.
     *
     * @param string|(\Closure(TParams...): string) $hook Target WordPress hook name.
     * * A `string` is used verbatim.
     * * A closure is evaluated **eagerly** at registration time. Its
     *   parameters resolve from the DI container only — no hook arguments
     *   exist yet.
     * * It must return a non-empty string. `null`, `''` or a non-string aborts
     *   that single registration with a warning (no exception, boot continues).
     * ! The raw closure and its callable plan are retained in registration
     * ! metadata/cache and resolved per registration: keep it deterministic
     * ! (no request, user or DB state).
     *
     * @param int $priority Standard WordPress priority. Default 10.
     * ! `PHP_INT_MIN` is used internally for deferred-activation listeners;
     * ! avoid it for application hooks so activation always runs first.
     *
     * @param int $acceptedArgs Maximum number of arguments WordPress passes to
     *                          the handler (default 1).
     * * For property hooks these are the arguments WordPress forwards to the
     *   callable **read from the property at dispatch time** — it does not
     *   declare that callable's arity (the property may be lazily initialized
     *   to a closure with a different signature).
     * * It caps what `$executeIf` can see as named hook arguments.
     * ! WordPress passes *at most* this many args — declaring more arguments
     * ! than the hook provides yields missing/`null` values, not an error.
     *
     * @param (\Closure(TParams...): bool)|null $executeIf Dispatch-time guard.
     * * Return `true` to run the handler, `false` to skip it.
     * * Parameter resolution order:
     *   1. a hook argument whose **name** matches the closure parameter name;
     *   2. a DI container service matching the parameter type;
     *   3. the parameter default;
     *   4. otherwise `RuntimeException`.
     * * Name matching is **positional against the handler's own parameter
     *   list**: the gate parameter must be spelled exactly like the handler
     *   parameter that receives that hook argument (e.g. handler
     *   `onSearch(string $search, \WP_Query $wp_query)` → gate
     *   `fn(string $search)`).
     * * A `variadic` gate parameter whose name equals the handler's variadic
     *   parameter receives the whole remainder bucket (splatted).
     * * Must return `bool`; a non-bool result is a `RuntimeException`.
     *
     * **Failure semantics — the important part:**
     * * gate returns `false` → handler skipped. With `once: true` the hook is
     *   **consumed anyway**; a false gate is final and never retried.
     * * gate **throws**, returns a non-bool, or cannot resolve a dependency
     *   → handler is **declined** (skipped): filters pass the original value
     *   through, actions are no-ops, and an ERROR is logged. A gate failure
     *   never falls through as `true`; with `once: true` the hook is consumed
     *   anyway, so that gate is never retried.
     *
     * ! Property-hook limitation: for a `get`-hooked property the callable is
     * ! produced lazily. To learn its parameter names the scanner would have to
     * ! read the property — running the getter (side effects, eager service
     * ! construction, broken lazy-loading). `hookArgs` therefore stays empty
     * ! when the class is not container-resolvable, and for container-backed
     * ! classes the recorded plan describes whatever the getter returns at scan
     * ! time — which may differ from the callable actually registered for
     * ! another attribute on the same property. Do not rely on hook-argument
     * ! injection for property hooks; prefer container-resolvable or
     * ! zero-parameter gates there.
     *
     * @param (\Closure(TParams...): bool)|null $registerIf Registration gate.
     * * Return `true` to attach the hook, `false` to skip it.
     * * Non-deferred entries are evaluated once, at registration time.
     * * Deferred entries (`deferRegister` / `deferRegisterUntilHook`) are
     *   **not** gated during the scan: the gate runs at activation, and again
     *   on every activation attempt.
     * * Parameters resolve from the DI container only. There are no hook
     *   arguments at registration/activation time, so a parameter named like a
     *   hook argument will **not** receive it (it will throw unless it is
     *   container-resolvable or has a default).
     * * A non-bool result is treated as `false`.
     * ! A `false` gate is silent apart from a warning log — the hook simply
     * ! does not exist.
     *
     * @param (\Closure(TParams...): array)|array $tag Tag(s) used by the
     *        activation / unregistration APIs (`activateDeferredByTags`,
     *        `activateDeferredByTagPattern`, `unregisterByTags`,
     *        `unregisterDeferredByTags`, and their `...InLiveInstance()`
     *        counterparts).
     * * Accepts a flat array of `string` or string-backed `BackedEnum`, or a
     *   static closure returning such an array.
     * * Matching is **OR**: an entry matches when **any** supplied tag is
     *   present (e.g. `['A', 'B']` → matches `A` or `B`).
     * * Duplicates are removed; string-backed enums are normalized to their
     *   `->value`.
     * * A closure is evaluated **eagerly** at registration time and must
     *   return a flat array — nested arrays are rejected. Container-path
     *   parameters resolve from the DI container. Instance-path parameters may
     *   also bind the live owner as `?self`; the tag callable is retained in
     *   the instance metadata cache and resolved **per registration**, so
     *   owner-specific tags never leak between sibling instances.
     * ! Any other tag type (`int`, non-backed enum, object, nested array)
     * ! raises a `RuntimeException` while normalizing. Both registries catch
     * ! it per registration: that single hook is skipped and logged as an
     * ! ERROR — boot continues, nothing else is affected.
     *
     * @param string|(\Closure(TParams...): string)|null $deferRegisterUntilHook
     *        Defer registration until the named trigger hook fires. Implies
     *        `deferRegister: true` (the constructor forces it).
     * * A closure is resolved like every other attribute closure (container
     *   parameters, must return a non-empty hook name).
     * * A filter listener is attached to the trigger at `PHP_INT_MIN` priority
     *   and activates **only this entry** — not every entry listening on the
     *   same target hook, and not other entries sharing the same trigger. It
     *   returns the trigger value unchanged, so an `apply_filters()` trigger
     *   keeps its value while activation runs as a side effect.
     * * If the trigger already fired (`did_action($triggerHook) >= 1 || did_filter($triggerHook) >= 1`) the entry is activated immediately.
     * ! The trigger is resolved **once**, at registration
     * ! A trigger that never fires leaves the entry deferred forever, silently.
     *
     * @param bool $deferRegister Hold the registration in the deferred pool
     *        until it is activated explicitly (`activateDeferredByHook`,
     *        `activateDeferredByClass`, `activateDeferredByCallable`,
     *        `activateDeferredByTags`, `activateDeferredByNamespace`,
     *        `activateDeferredByHookPattern`, `activateDeferredByTagPattern`).
     * * Forced to `true` whenever `$deferRegisterUntilHook` is set.
     * * `registerIf` is re-evaluated at activation time (not only at
     *   registration time).
     * * Runtime path: activated through the owner-scoped
     *   `activateDeferredByHookInLiveInstance()`,
     *   `activateDeferredByHookPatternInLiveInstance()`,
     *   `activateDeferredByTagsInLiveInstance()`,
     *   `activateDeferredByTagPatternInLiveInstance()`,
     *   `activateDeferredByCallableInLiveInstance()`, or
     *   `activateDeferredByClassInLiveInstance()` family (or automatically
     *   when `$deferRegisterUntilHook` is also set).
     * ! A deferred entry that nothing activates never registers; no warning is
     * ! emitted.
     *
     * @param bool $once Consume the registration on first dispatch.
     * * Consumed on **any** dispatch attempt: gate `false`, gate error, handler
     *   exception — all consume. A once-hook is never retried.
     * * The invoker becomes logically inert immediately (internal `consumed`
     *   flag), while the **physical** `remove_action()` may be deferred to a
     *   temporary `all` hook. This avoids corrupting `WP_Hook`'s iteration when
     *   a priority bucket empties mid-dispatch:
     * @link https://core.trac.wordpress.org/ticket/64653
     * * Until that flush the callback is still present in `$wp_filter` but
     *   cannot run twice.
     * * For deferred entries the counter starts at **activation**, not at
     *   declaration.
     * 
     * @param (\Closure(TParams...): void)|null $onUnregisterDo Cleanup side effect for live wordpress registration:
     * !        fired once when the invoker object is released (its PHP
     * !       `__destruct()`): after an explicit unregister, or its removal is flushed out of `$wp_filter`.
     * * **No hook arguments**: parameters resolve from the DI container, then
     *   defaults — exactly like `$registerIf`.
     * * Resolution is lazy and both resolution and execution are guarded: a
     *   broken cleanup closure is logged and never aborts registration or the
     *   destructor.
     * * Deferred entries activate at activation time, failed registerIf gate for InstanceRegistry will never activate it
     * * If registered on hook `shutdown`, it's noop, registry internally guard using this condition.
     * ! The instance registry binds the cleanup to the invoker, not the owner:
     * ! if the owner dies first the chain event command cleanup still runs from the invoker's
     * ! destructor.
     * ! This assume no application smell code mutate global $wp_filter directly
     * 
     * @param (\Closure(DeferredRegistrationInfoDTO|TParams...): void)|null $debugDeferredRegistration
     *   Observability probe for the deferred-pool lifecycle. Invoked with {@see DeferredRegistrationInfoDTO}
     *   on every deferred phase (`queued`, `activation_attempt`,
     *   `activation_rejected`, `activated`, `activation_failed`, `released`).
     *   ! At least must request DeferredRegistrationInfoDTO via parameter type-hint.
     * * Opt-in: `null` costs nothing — no DTO is built and no branch is taken
     *   beyond the null check.
     * * The DTO is transient: the framework keeps no reference. Call
     *   `$dto->toArray()` if you need to retain the snapshot.
     * * Parameters resolve from the DTO (by type), `self`/the live owner,
     *   then the DI container, then defaults. `LoggerInterface` works when
     *   the application binds it upstream — the hook system ships no logger
     *   binding of its own.
     * ! `self` injection may **construct the service** when it is resolved
     * ! from the container. Prefer `?self` (null before an owner exists) or
     * ! pass the values you actually need.
     * * Never invoked from PHP destructors; release events come from explicit
     *   release paths only.
     * * A throwing probe is logged and never interrupts the hook lifecycle.
     * 
     * @param (\Closure(LiveRegistrationInfoDTO|TParams...): void)|null $debugLiveRegistration
     *   Observability probe for live registrations. Invoked with {@see LiveRegistrationInfoDTO}
     *   on `registered`, `dispatching`, `completed`, `failed` and `skipped`.
     *   ! At least must request LiveRegistrationInfoDTO via parameter type-hint.
     * * Same opt-in, transient-DTO, resolution and `self`-construction rules
     *   as `$debugDeferredRegistration`.
     * * Executed synchronously on the dispatch path: keep it cheap, and never
     *   dispatch the same hook from inside the probe.
     * 
     * @param (\Closure(LiveUnregistrationInfoDTO|TParams...): void)|null $debugLiveUnregistration
     *   Observability probe for the unregistration/lifecycle side of a live
     *   registration. Invoked with {@see LiveUnregistrationInfoDTO}
     *   on `unregister_requested`, `unregistered`, `unregister_failed`,
     *   `once_consumed`, `owner_collected` and `proxy_released`.
     *   ! At least must request LiveUnregistrationInfoDTO via parameter type-hint.
     * * Owner-optional emission: these phases may run while the owner or the
     *   container is being torn down. `self`/`static` parameters are NEVER
     *   resolved from the container — declare them `?self`; other dependencies
     *   still resolve through the container (with defaults as a mid-teardown
     *   fallback).
     * * `proxy_released` fires from the invoker destructor: keep the probe
     *   synchronous and cheap, and do not rely on the request being active.
     * * Same opt-in, transient-DTO and throwing-probe rules as the other
     *   probes.
     */
    public function __construct(
        public readonly string|\Closure $hook,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1,
        public readonly ?\Closure $executeIf = null,
        public readonly ?\Closure $registerIf = null,
        public readonly array|\Closure $tag = [],
        public readonly string|\Closure|null $deferRegisterUntilHook = null,
        public bool $deferRegister = false,
        public readonly bool $once = false,
        public readonly ?\Closure $onUnregisterDo = null,
        public readonly ?\Closure $debugDeferredRegistration = null,
        public readonly ?\Closure $debugLiveRegistration = null,
        public readonly ?\Closure $debugLiveUnregistration = null,
    ) {
        $this->deferRegister = $deferRegisterUntilHook !== null ? true : $deferRegister;
    }
}

/**
 * Marks an instance method or property as a WordPress **action** callback.
 *
 * Dispatched by `do_action()`; the return value is ignored by WordPress (it is
 * still returned by the invoker, so a filter pipeline would thread it — see the
 * limitation on action/filter symmetry below).
 *
 * Repeatable: stack several attributes on one member to register several
 * hooks (multi-priority pattern).
 *
 * ! WordPress internally does not distinguish actions from filters. Placing an
 * ! `#[Action]` on a hook that is dispatched with `apply_filters()` still runs
 * ! the callback (and vice versa) — the class only affects `HookKey`
 * ! bookkeeping and filter pass-through behaviour.
 *
 * @see BaseHookAttribute for closure rules, scanning rules and limitations.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Action extends BaseHookAttribute {}
/**
 * Marks an instance method or property as a WordPress **filter** callback.
 *
 * Dispatched by `apply_filters()`; the handler's return value replaces the
 * filtered value. If the handler is skipped (gate `false`, gate evaluation
 * failure, exception) the incoming value passes through untouched.
 *
 * Repeatable: stack several attributes on one member to register several
 * hooks (multi-priority pattern).
 *
 * ! WordPress internally does not distinguish actions from filters. A
 * ! `#[Filter]` placed on an action hook still runs; its return value is
 * ! simply discarded by `do_action()`.
 *
 * @see BaseHookAttribute for closure rules, scanning rules and limitations.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Filter extends BaseHookAttribute {}
