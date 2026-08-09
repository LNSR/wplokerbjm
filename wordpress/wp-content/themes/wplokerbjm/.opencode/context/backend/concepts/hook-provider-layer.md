# Concept: Hook Provider Layer

**Purpose**: Explain how condition gates, dynamic hook names, and tag closures get DI-powered parameter resolution without reflection on the hot path.

## Core Idea

`HookProviderTrait` is the shared resolution core for both providers — `WPHookPlanProvider` (container path) and `RuntimeWPHookProvider` (runtime path). Callable metadata is captured into a serializable **plan at scan time**; the plan is exported to the hooks cache and resolved at fire time, so the hot path never needs reflection unless a plan is missing (stale cache / unexportable defaults).

## Plan model

```php
// CallablePlan (phpstan): precomputed once per closure
[
  'isStatic'   => true,               // closure-level metadata
  'scopeClass' => 'WPLokerBJM\...',   // declaring class
  'params'     => [                   // parameter metadata
    ['name' => 'search', 'type' => null, 'hasDefault' => false, 'default' => null],
  ],
]
```

## Key Points

- `buildCallablePlan()` returns an empty-shaped plan for null callables or non-exportable defaults (objects/resources) — callers fall back to reflection.
- `resolveCallableParameters()` injects **by exact parameter name** from named hook args first, then the container, then defaults.
- `evaluateExecuteIf()` / `evaluateRegistrationGate()` require bool returns; zero-parameter gates take a direct fast path.
- `bindToTarget()` scope-binds closures to the target class (WeakMap-memoized per closure+class pair); static attribute closures only need a scope-only bind.
- `resolveTagCallable()` resolves dynamic tag lists at registration time and must return an array.

## Minimal example

```php
#[Filter('posts_search', acceptedArgs: 2)]
public function search(string $sql, object $query): string
{
    // Parameters matching hook args are injected by name;
    // other typed params resolve from the container.
    return $sql;
}
```

## Related

- `concepts/wphooks-architecture.md` — where plans are consumed
- `lookup/wphooks-api.md` — provider and trait API rows
- `guides/wphooks-lifecycle.md` — scan-time plan build vs fire-time resolution

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Trait/HookProviderTrait.php` — `buildCallablePlan`, `resolveHookName`, `resolveCallableParameters`, `evaluateExecuteIf`, `evaluateRegistrationGate`, `resolveTagCallable`, `callableParamNames`, `extractPropertyCallableParamNames`.
- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — container-side provider.
- `server/Core/Container/Support/WPHooks/Provider/RuntimeWPHookProvider.php` — runtime-side provider with optional container (`resolveRuntimeHookName`, `evaluateRuntimeRegisterIf`, `evaluateRuntimeExecuteIf`).
