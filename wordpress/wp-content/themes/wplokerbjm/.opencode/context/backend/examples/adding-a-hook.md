<!-- Context: backend/examples | Priority: critical | Version: 1.1 | Updated: 2026-11-27 -->

# Example: Adding a Hook

**Purpose**: Shows how to use `#[Action]` and `#[Filter]` attributes instead of `add_action`/`add_filter`
**Last Updated**: 2026-11-27

## Use case

You need to register a WordPress action or filter hook. The class must be autowirable (discoverable by `DependencyAutowireScanner`). The container wires dependencies automatically.

## Code

```php
<?php
namespace WPLokerBJM\Controllers;

use WPLokerBJM\Core\Container\Attributes\{Action, Filter};

class MyController
{
    #[Action('init')]
    public function onInit(): void
    {
        // Runs on WordPress 'init' at priority 10.
    }

    #[Filter('the_content', 10, 1)]
    public function modifyContent(string $content): string
    {
        return str_replace('old', 'new', $content);
    }
}
```

## Explanation

1. **Add the attribute** above the method: `#[Action('hook_name')]` or `#[Filter('hook_name')]`.
2. **Optional parameters** include `priority` (default 10), `acceptedArgs` (default 1), `deferRegister`, `deferRegisterUntilHook`, `registerIf`, `executeIf`, `once`, and `tag`.
3. **DI autowiring** injects constructor dependencies automatically.
4. **That's it** — no direct `add_action()` / `add_filter()` calls.

**Deferred registration**:
```php
#[Action('init', deferRegister: true)]
public function onInit(): void {}

#[Action('init', deferRegisterUntilHook: 'mu_plugin_loaded')]
public function onPluginsLoaded(): void {}
```
Activate held entries with `activateDeferredByHook('init')`, `activateDeferredByClass(self::class)`, `activateDeferredByCallable([$this, 'onInit'])`, or the matching tag/pattern selector. There is no `registerByMethod()` method.

**Key points**:
- Class must be in `server/` under the `WPLokerBJM\` namespace.
- PHP-DI resolves constructor parameters from the container.
- `#[Action]` and `#[Filter]` are repeatable on the same method.
- `deferRegisterUntilHook` implies deferral and re-checks `registerIf` on each activation attempt.
- WPHooks logging goes through the injected `HookLoggerInterface`; `ModuleHookMetadata` is the temporary shared-logger exception.

## 📂 Codebase References

**Attribute definitions**:
- `server/Core/Container/Attributes/WPHooksAttributes.php` — `#[Action]` and `#[Filter]`.

**Real examples**:
- `server/Models/Schema/PostTypes.php` — multiple `#[Action('init')]` examples.
- `server/Services/WebHooks/CloudflareCachePurger.php` — multi-attribute cache-purge hooks.
- `server/Core/Wordpress/GlobalHooks.php` — global WordPress hooks.

## Related

- `concepts/hook-system.md` — full hook system concept
- `lookup/hooks.md` — all registered hooks
- `examples/adding-a-service.md` — creating the service used by your hook
- `lookup/wphooks-api.md` — deferred selectors and invoker API
