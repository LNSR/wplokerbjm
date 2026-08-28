# Error: Hook Attribute on Magic Method

**Purpose**: Fix the loud scanner failure when `#[Action]` / `#[Filter]` is placed on a magic method, plus related scanner contract notes.

## Error: Hook Attribute on Magic Method

**Symptom**: `RuntimeException: Hook attribute on magic method ... is not allowed — only __invoke can be hooked`

**Cause**: `HookScannerTrait::scanMethodHooks()` rejects hook attributes on `__construct`, `__get`, `__set`, `__call`, `__toString`, etc. The lazy handler would call them on a container-built instance — double construction for `__construct`, nonsense for the others. The failure is intentional and loud: silently registering a broken hook is worse.

**Solution**:
```php
// ❌ Fails loudly
#[Action('init')]
public function __construct() { /* ... */ }

// ✅ Move the attribute to a regular instance method
public function __construct() { /* ... */ }

#[Action('init')]
public function onInit(): void { /* ... */ }
```

`__invoke` is the one allowed exception — anonymous-class and property-hook callables use it.

**Prevention**: Only place hook attributes on normal instance methods or properties; never on magic methods except `__invoke`.
**Frequency**: rare

## Related scanner contract (by design, not errors)

- **Declared-only**: inherited methods/properties are excluded — a subclass must re-declare the method with its own attribute and `parent::method()` call to opt in.
- **Static members skipped**: hooks must be instance members (the container must instantiate the owning service).
- **Property targets**: attributes on PHP 8.4 hooked properties get `target: 'property-hook'`; plain properties get `target: 'property'`.
- **Cache path**: `WPHooksScanner::$cacheLocation` normalizes a directory path by appending `WPHooksCache.php`.

## 📂 Codebase References

- `server/Core/Container/Support/WPHooks/Trait/HookScannerTrait.php` — magic-method guard + declared-only scanning.
- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — cache export and cacheLocation normalization.

## Related
- `concepts/hook-system.md` — attribute placement rules
- `errors/common-issues.md` — other hook/container errors
