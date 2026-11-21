<!-- Context: backend/concepts | Priority: high | Version: 1.0 | Updated: 2026-11-28 -->

# Concept: Container Lifetimes & WPHooks

**Purpose**: Explain which lifetimes WPHooks inherits from the consumer's container, and when a closure attribute parameter is actually a container lookup.

## Core contract

WPHooks receives a PSR-11 `ContainerInterface`. `Resolution/ArgumentResolver` only calls `get()`; it never calls `make()` and never owns or caches service instances. **The container's `get()` is the lifetime authority.** WPHooks respects whatever the upstream container returns.

## Lifetime modes

| Mode | How it is produced | What WPHooks sees |
|------|--------------------|-------------------|
| **Shared / singleton** | Default project definitions use `DI\autowire(...)`; PHP-DI `Container::get()` caches the resolved entry in `$resolvedEntries` | The same instance for the container/request, across every hook fire that asks for that type |
| **Lazy shared** | `DI\autowire(...)->lazy()` | One proxy entry; its target resolves once on first use, then stays at container lifetime |
| **Transient** | PHP-DI `make()` creates anew each call | **Not used by WPHooks.** A custom `get()` may return a new instance each call; WPHooks will respect it, but this is a custom-container contract |
| **Scoped** | No first-class project container scope | WPHooks owner scope controls hook registration lifetime, **not** dependency lifetime; pass per-owner/request state as a hook argument |

## Closure attribute resolution order

A closure attribute parameter is resolved as:

1. exact hook-argument name;
2. live owner for `self` / `static`;
3. container `get($type)` when the container has the type;
4. declared default, otherwise a resolution error.

`self` / `static` are the exception to container lookup: the live owner wins; `?self` without an owner resolves to `null`. This does not make other typed parameters owner-scoped.

## Consumer warning

- A typed closure parameter can be **shared** even when the hook is per-request, deferred, or owned by a short-lived instance. Do not assume hook lifetime equals dependency lifetime.
- WPHooks does not snapshot dependencies. If the container returns transient instances from `get()`, repeated resolutions may receive different instances.
- If a dependency must follow the hook owner or one specific fire, pass it as a hook argument, or use `self` / the owner — do not rely on a container transient.
- `registerIf`, `tag`, dynamic `hook`, and `deferRegisterUntilHook` resolve during registration/activation; `executeIf` resolves at fire time. The same upstream lifetime applies at each point.

```php
// Shared by default: every fire receives the same container instance.
#[Action('init')]
public function boot(HeavyService $service): void { /* ... */ }

// Per-fire request state should come from hook args, not a transient guess.
#[Filter('posts_search', acceptedArgs: 2)]
public function search(string $sql, \WP_Query $query): string
{
    return $sql;
}
```

## Related

- `concepts/hook-provider-layer.md` — plan and parameter resolution
- `guides/wphooks-lifecycle.md` — when each closure is evaluated
- `lookup/wphooks-api.md` — provider API rows

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Resolution/ArgumentResolver.php` — `get()` lookup and `self`/owner branch.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — facade used by hook attributes.
- `server/Core/Container/Definitions/Core.php` — `DI\autowire(...)` definitions and WPHooks wiring.
- `server/Core/Container/Support/InstanceDiscovery/DependencyAutowireScanner.php` — generated shared/lazy definitions.
- `vendor/php-di/php-di/src/Container.php` — `get()` cache vs `make()` transient behavior.
