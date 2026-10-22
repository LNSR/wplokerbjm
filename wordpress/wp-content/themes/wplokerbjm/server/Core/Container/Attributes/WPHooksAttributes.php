<?php

declare(strict_types=1);

namespace WPLokerBJM\Core\Container\Attributes;

use Attribute;
use WPLokerBJM\Core\Container\Support\WPHooks\Abstract\ModuleClassHookMetadata;
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
 * - `WPHooksContainerRegistry` — attribute-discovered **services**. The handler
 *   is a lazy proxy: the service is resolved with `$container->get()` on first
 *   dispatch, so its constructor (DB clients, HTTP clients, …) does not run
 *   until the hook actually fires. Supports tags, deferred activation and the
 *   batch activation/unregistration APIs.
 * - `WPHooksInstanceRegistry` — **runtime instances**, normally anonymous
 *   classes extending {@see ModuleClassHookMetadata}. The handler keeps only a
 *   `WeakReference` to the live instance; when that instance is garbage
 *   collected the handler becomes inert and detaches itself.
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
 * Closures execute in three very different moments:
 *
 * - `$hook`, `$tag`, `$deferRegisterUntilHook` → **eagerly**, during
 *   scan/registration (boot and cache generation). Keep them pure and cheap:
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
 * | `$tag`                    | yes               | **ignored**             |
 * | `$deferRegister`          | yes               | **ignored**             |
 * | `$deferRegisterUntilHook` | yes               | yes                     |
 * | `$registerIf`             | yes               | yes                     |
 * | `$executeIf`              | yes               | yes                     |
 * | `$once`                   | yes               | yes                     |
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
 * - **`did_action()` counts only `do_action()`.** A trigger hook that was only
 *   ever dispatched through `apply_filters()` before registration is not
 *   detected as "already fired".
 * - **Scan-time work is boot-time work**: `hook`/`tag`/`deferRegisterUntilHook`
 *   closures run on every cold boot (and during cache generation).
 *
 * Required PHP: >= 8.5.
 *
 * ! Recommended to boot from the MU-Plugin so scanning happens before any
 * ! plugin registers its own hooks.
 *
 * @see WPHooksScanner
 * @see \WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksContainerRegistry
 * @see \WPLokerBJM\Core\Container\Support\WPHooks\Registry\WPHooksInstanceRegistry
 * @see ModuleClassHookMetadata
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
     * * A closure is evaluated **eagerly** at scan/registration time. Its
     *   parameters resolve from the DI container only — no hook arguments
     *   exist yet.
     * * It must return a non-empty string. `null`, `''` or a non-string aborts
     *   that single registration with a warning (no exception, boot continues).
     * ! Because it runs during boot/cache generation the result is baked into
     * ! the hook cache: keep it deterministic (no request, user or DB state).
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
     * * gate **throws** (or returns non-bool) → without `once` the handler is
     *   skipped and an ERROR is logged; **with `once` the gate is treated as
     *   PASS** (INFO logged) and the handler still runs, so a once-hook can
     *   never leak. Consequence: a mis-configured gate on a once-hook silently
     *   always passes.
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
     * * Evaluated at registration time, and **re-evaluated at every deferred
     *   activation attempt**.
     * * Parameters resolve from the DI container only. There are no hook
     *   arguments at registration/activation time, so a parameter named like a
     *   hook argument will **not** receive it (it will throw unless it is
     *   container-resolvable or has a default).
     * * A non-bool result is treated as `false`.
     * ! A `false` gate is silent apart from a warning log — the hook simply
     * ! does not exist.
     * ! Registry asymmetry on rejection: the container registry keeps the
     * ! deferred trigger listener attached while the gate fails, so the next
     * ! trigger fire re-tries. The instance registry removes its one-shot
     * ! trigger listener after the first fire even when the entry was rejected —
     * ! a deferred instance entry rejected by `registerIf` normally never
     * ! activates.
     *
     * @param (\Closure(TParams...): array)|array $tag Tag(s) used by the
     *        activation / unregistration APIs (`activateDeferredByTags`,
     *        `activateDeferredByTagPattern`, `unregisterByTags`,
     *        `unregisterDeferredByTags`, …).
     * * Accepts a flat array of `string` or string-backed `BackedEnum`, or a
     *   static closure returning such an array.
     * * Matching is **OR**: an entry matches when **any** supplied tag is
     *   present (e.g. `['A', 'B']` → matches `A` or `B`).
     * * Duplicates are removed; string-backed enums are normalized to their
     *   `->value`.
     * * A closure is evaluated **eagerly** at scan time (container parameters
     *   only) and must return a flat array — nested arrays are rejected.
     * ! Any other tag type (`int`, non-backed enum, object, nested array)
     * ! raises a `RuntimeException` while normalizing. The container registry
     * ! catches it per registration: that single hook is skipped and logged
     * ! as an ERROR — boot continues, nothing else is affected.
     * ! Ignored entirely by the InstanceRegistry (it exposes no tag APIs).
     *
     * @param string|(\Closure(TParams...): string)|null $deferRegisterUntilHook
     *        Defer registration until the named trigger hook fires. Implies
     *        `deferRegister: true` (the constructor forces it).
     * * A closure is resolved like every other attribute closure (container
     *   parameters, must return a non-empty hook name).
     * * A listener is attached to the trigger at `PHP_INT_MIN` priority and
     *   activates **only this entry** — not every entry listening on the same
     *   target hook, and not other entries sharing the same trigger.
     * * If the trigger already fired (`did_action($trigger) >= 1`) the entry is
     *   activated immediately (container path defers this to the end of
     *   `initialize()`).
     * ! Only **actions** are detected as "already fired": `did_action()` is
     * ! incremented by `do_action()`, not by `apply_filters()`.
     * ! The trigger is resolved **once**, at registration: a closure that
     * ! returns different names per request is a footgun.
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
     * ! Ignored by the InstanceRegistry, which has no activation APIs — use
     * ! `$deferRegisterUntilHook` there.
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
     * @link https://core.trac.wordpress.org/ticket/61263
     * @link https://core.trac.wordpress.org/ticket/64653
     * * Until that flush the callback is still present in `$wp_filter` but
     *   cannot run twice.
     * * For deferred entries the counter starts at **activation**, not at
     *   declaration.
     * ! Remember the once/gate interaction from `$executeIf`: a throwing or
     * ! non-bool gate is treated as PASS on once-hooks, so a broken gate does
     * ! not prevent execution — it silently enables it.
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
 * filtered value. If the handler is skipped (gate `false`, unresolvable
 * `executeIf`, exception) the incoming value passes through untouched.
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
