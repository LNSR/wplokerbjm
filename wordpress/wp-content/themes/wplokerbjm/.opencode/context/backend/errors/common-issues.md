<!-- Context: backend/errors | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Errors: Common Issues

**Purpose**: Common bootstrap, container, cache, and hook errors with fixes
**Last Updated**: 2026-07-27

## Error: Container Compilation Failure

**Symptom**: `Fatal error: Container compilation failed — CompiledContainer.php not found`

**Cause**: Cache directory not writable, or class structure changed without rebuild.

**Solution**:
```bash
chmod 755 cache/
rm -f cache/CompiledContainer.php        # Force rebuild
composer generate-meta-hooks             # Regenerate hook types
```

**Prevention**: Run `composer dump-autoload --apcu -a -o` after class changes. Delete `CompiledContainer.php` after structural changes.
**Frequency**: occasional

---

## Error: Hook Not Firing / Class Not Discovered

**Symptom**: `#[Action]` or `#[Filter]` method never executes. No error.

**Cause**: Wrong namespace, `$defer = true` without registration, or class not in scan path.

**Solution**:
```php
// ❌ Wrong namespace — hook never fires
namespace App\Controllers;
class MyController { #[Action('init')] public function onInit() {} }

// ✅ Correct
namespace WPLokerBJM\Controllers;
class MyController { #[Action('init')] public function onInit() {} }
```

1. Verify class is in `server/` under `WPLokerBJM\` namespace
2. Check filename matches class name (PSR-4)
3. If `defer: true`, must call `WPHooksContainerRegistry::registerByMethod()` explicitly
4. Delete `cache/CompiledContainer.php` + reload

**Prevention**: Always use `WPLokerBJM\` root namespace.
**Frequency**: common

---

## Error: Cloudflare Purge Fails Silently

**Symptom**: Content changes but CDN cache not purged. In dev, purges are intentionally skipped.

**Cause**: Missing/invalid Cloudflare credentials, or running in development mode.

**Solution**:
1. Check `.env`: `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ZONE_ID`
2. Check logs for `Logger::warning('WebHook', 'Cloudflare credentials are missing.')`
3. If `SharedUtils::isDevelopment()` is true, purges are skipped by design

**Prevention**: Validate credentials in `.env` and `.env.example`.
**Frequency**: occasional

---

## Error: Cache Miss on Expected Data

**Symptom**: `Cache::get()` returns `false` for data that should be cached.

**Cause**: Key typo, Redis not configured, or premature invalidation.

**Solution**:
1. Verify Redis object cache plugin is active
2. Always use `CacheKey::*` constants — never hardcode keys
3. Check key prefix: all use `CacheKey::OBJECT_CACHE_PREFIX` (`wplokerbjm_obj_`)

**Prevention**: Add new keys to `CacheKey` class; use constants exclusively.
**Frequency**: occasional

---

## Error: PHP-DI Autowiring Exception

**Symptom**: `DI\Definition\Exception\InvalidDefinition: Entry "WPLokerBJM\..." cannot be resolved`

**Cause**: Class not discoverable, missing interface binding, or circular dependency.

**Solution**:
1. Verify class exists at correct PSR-4 path
2. Add interface bindings to `Core::getDefinitions()` or `Factory::getDefinitions()`
3. Use `#[Injectable(lazy: true)]` for expensive classes
4. Avoid circular refs (A → B → A)
5. Delete `cache/CompiledContainer.php` and reload

**Prevention**: Keep definitions updated in `Core.php` and `Factory.php`.
**Frequency**: occasional

---

## Error: Brain Monkey Not Mocking (Tests)

**Symptom**: `Brain\Monkey\Exception: Function was not mocked: wp_cache_get`

**Solution**: Extend `WplokerbjmTestCase`, call `parent::setUp()`, use `Functions\when()`:
```php
protected function setUp(): void { parent::setUp(); }
// In test: Functions\when('wp_cache_get')->justReturn(false);
```

**Code**: `tests/Support/WplokerbjmTestCase.php`, `tests/Support/ProxyContainer.php`
**Frequency**: common

---

## 📂 Codebase References

- `server/Core/Container/Container.php` — Container build errors
- `server/Shared/Cache/Cache.php` — Cache failures
- `server/Services/WebHooks/Cloudflare.php` — CDN credential validation
- `server/Shared/Log/Logger.php` — Buffered error logger
- `tests/Support/WplokerbjmTestCase.php` — Test base class

## Related
- `concepts/architecture.md` — Bootstrap flow
- `concepts/hook-system.md` — Hook registration debugging
- `concepts/caching.md` — Cache key reference
