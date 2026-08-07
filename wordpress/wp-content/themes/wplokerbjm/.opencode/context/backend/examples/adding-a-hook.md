<!-- Context: backend/examples | Priority: critical | Version: 1.0 | Updated: 2026-07-27 -->

# Example: Adding a Hook

**Purpose**: Shows how to use `#[Action]` and `#[Filter]` attributes instead of `add_action`/`add_filter`
**Last Updated**: 2026-07-27

## Use Case
You need to register a WordPress action or filter hook. The class must be autowirable (discoverable by `AutowireScanner`). The container wires dependencies automatically.

## Code

```php
<?php
namespace WPLokerBJM\Controllers;

use WPLokerBJM\Core\Container\Attributes\{Action, Filter};
use WPLokerBJM\Services\REST\LowonganIngestService;

class MyController
{
    public function __construct(
        private readonly LowonganIngestService $service,
    ) {}

    #[Action('init')]
    public function onInit(): void
    {
        // Runs on WordPress 'init' at priority 10
    }

    #[Filter('the_content', 10, 1)]
    public function modifyContent(string $content): string
    {
        return str_replace('old', 'new', $content);
    }
}
```

## Explanation
1. **Add attribute** above method: `#[Action('hook_name')]` or `#[Filter('hook_name')]`
2. **Optional params**: priority (default 10), acceptedArgs (default 1), defer (default false)
3. **DI autowiring**: Constructor dependencies injected automatically
4. **That's it** — no need for `add_action()` or `add_filter()` anywhere

**Key points**:
- Class must be in `server/` directory under `WPLokerBJM\` namespace
- PHP-DI resolves all constructor params from the container
- `#[Action]` and `#[Filter]` are repeatable on the same method
- Set `defer: true` to skip registration (activate by calling `WPHooksContainerRegistry::registerByMethod()`)

## 📂 Codebase References

**Attribute Definitions**:
- `server/Core/Container/Attributes/WPHooksAttributes.php` — `#[Action]` and `#[Filter]` classes

**Real Examples**:
- `server/Models/Schema/PostTypes.php` — Multiple `#[Action('init')]` examples
- `server/Services/WebHooks/Cloudflare.php` — Multi-attribute with self-unregister pattern
- `server/Core/GlobalHooks.php` — Global WordPress hooks (redirects, robots, search)

## Related
- `concepts/hook-system.md` — Full hook system concept
- `lookup/hooks.md` — All registered hooks
- `examples/adding-a-service.md` — Creating the service used by your hook
