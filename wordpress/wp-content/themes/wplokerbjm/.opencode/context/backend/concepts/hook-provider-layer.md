<!-- Context: backend/concepts | Priority: high | Version: 1.1 | Updated: 2026-11-27 -->

# Concept: Hook Provider Layer

**Purpose**: Explain how condition gates, dynamic hook names, and tag closures get DI-powered parameter resolution without reflection on the hot path.

## Core idea

`WPHookPlanProvider` is a backwards-compatible facade over four collaborators:

- `Plan/CallablePlanFactory` — reflection → plan arrays and per-callable caches;
- `Resolution/ArgumentResolver` — container-backed parameter resolution and scope binding;
- `Resolution/ConditionEvaluator` — hook names, `executeIf`/`registerIf`, tags, `onUnregisterDo`;
- `Debug/HookDebugDispatcher` — debug-probe dispatch and owner-optional parameter policy.

The provider is constructed with `ContainerInterface` plus an optional `HookLoggerInterface`; it passes the logger to the plan factory, condition evaluator, and debug dispatcher. New code should inject the collaborator it needs instead of the facade.

## Plan model

A `CallablePlan` is precomputed once per closure and exported to the hooks cache:

```php
// array{isStatic: bool, params: list<CallableHookParams>}
[
    'isStatic' => true,
    'params' => [
        new CallableHookParams(
            name: 'search',
            type: null,
            hasDefault: false,
            default: null,
            isVariadic: false,
            isSelf: false,
        ),
    ],
]
```

## Key points

- `buildCallablePlan()` returns an empty-shaped plan for null callables or non-exportable defaults (objects/resources); callers fall back to reflection.
- `buildCallablePlanFromReflector()` and `extractPropertyCallablePlan()` capture method/property parameters.
- `ArgumentResolver::resolveCallableParameters()` injects by exact parameter name from named hook args first, then the container, then defaults.
- `ConditionEvaluator` requires bool gate returns; zero-parameter gates take a direct fast path. `resolveTagCallable()` returns the full dynamic tag list.
- `bindToTarget()` scope-binds closures to the target class; `assertNullableSelfClosures()` rejects non-nullable `self` parameters on instance-registry plans.
- `resolveOnUnregisterDo()` and `invokeHookDebug()` are exposed through the facade.

## Minimal example

```php
#[Filter('posts_search', acceptedArgs: 2)]
public function search(string $sql, object $query): string
{
    // Matching hook args inject by name; other typed params resolve from the container.
    return $sql;
}
```

## Related

- `concepts/wphooks-architecture.md` — where plans are consumed
- `lookup/wphooks-api.md` — provider and collaborator API rows
- `guides/wphooks-lifecycle.md` — scan-time plan build vs fire-time resolution

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Provider/WPHookPlanProvider.php` — facade and public API.
- `server/Core/Container/Support/WPHooks/Plan/CallablePlanFactory.php` — plan building and caches.
- `server/Core/Container/Support/WPHooks/Resolution/ArgumentResolver.php` — parameter resolution and binding.
- `server/Core/Container/Support/WPHooks/Resolution/ConditionEvaluator.php` — gates, names, tags, cleanup callables.
- `server/Core/Container/Support/WPHooks/Debug/HookDebugDispatcher.php` — debug probe dispatch.
- `server/Core/Container/Support/WPHooks/Logger/HookLoggerInterface.php` — injected logging seam.
