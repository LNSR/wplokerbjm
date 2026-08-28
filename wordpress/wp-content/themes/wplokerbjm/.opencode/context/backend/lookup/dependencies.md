<!-- Context: backend/lookup | Priority: medium | Version: 1.0 | Updated: 2026-07-27 -->

# Lookup: Dependencies

**Purpose**: Composer package reference for the WPLokerBJM theme
**Last Updated**: 2026-07-27

## Production Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `nette/robot-loader` | * | Auto-discovers classes in `server/`, caches index |
| `nikic/php-parser` | * | PHP code parser (used by RobotLoader + hook scanner) |
| `php-di/php-di` | * | Dependency injection container with compilation |
| `php-standard-library/php-standard-library` | * | Type-safe utility library (PSL) |

## Dev Dependencies

| Package | Purpose |
|---------|---------|
| `brain/monkey` | WordPress function mocking for unit tests |
| `phpunit/phpunit` | Test framework |
| `php-parallel-lint/php-parallel-lint` | Syntax checking |
| `php-parallel-lint/php-console-highlighter` | Console output formatting |
| `vlucas/phpdotenv` | `.env` file loading |

## Composer Scripts

| Script | Command | Purpose |
|--------|---------|---------|
| `post-install-cmd` | `dump-autoload --apcu -a -o` | Optimized autoload after install |
| `post-update-cmd` | `dump-autoload --apcu -a -o` | Optimized autoload after update |
| `generate-meta-hooks` | `php ./tools/Composer/Scripts/generate-meta-hooks.php` | Generate hook type hints |
| `lint:php` | `./tools/Composer/Scripts/lint.sh` | PHP syntax linting |
| `test` | `./tools/Composer/Scripts/test.sh` | Run PHPUnit tests |
| `watch:autoload` | generate-meta-hooks + autoloadwatcher | Dev autoload watch |

## Autoload Configuration

```json
{
  "autoload": {
    "psr-4": {
      "WPLokerBJM\\": "server/"
    }
  }
}
```

## Test Configuration

```xml
<!-- phpunit.xml -->
<testsuites>
    <testsuite name="WPLokerBJM">
        <directory>tests/</directory>
    </testsuite>
</testsuites>
```

## Key Imports by Feature

| Feature | Vendor Package |
|---------|---------------|
| DI Container | `php-di/php-di`, `php-di/invoker` |
| Autoloading | `nette/robot-loader`, `nette/utils` |
| Testing | `brain/monkey`, `phpunit/phpunit`, `mockery/mockery` |
| Type Utilities | `php-standard-library/php-standard-library` |
| Env Config | `vlucas/phpdotenv` (dev only) |

## 📂 Codebase References

**Config**:
- `composer.json` — All dependency declarations
- `composer.lock` — Locked versions
- `phpunit.xml` — Test configuration

**Custom Scripts**:
- `tools/Composer/Scripts/generate-meta-hooks.php` — Hook type generation
- `tools/Composer/Scripts/autoloadwatcher.sh` — Dev autoload watcher
- `tools/Composer/Scripts/lint.sh` — Lint script
- `tools/Composer/Scripts/test.sh` — Test runner

## Related
- `concepts/autoloading.md` — How Nette RobotLoader works
- `concepts/architecture.md` — How PHP-DI integrates
- `lookup/namespaces.md` — PSR-4 namespace map
