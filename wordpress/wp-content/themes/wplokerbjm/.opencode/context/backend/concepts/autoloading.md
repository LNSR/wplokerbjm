<!-- Context: backend/concepts | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Concept: Autoloading

**Purpose**: How class autoloading works via Nette RobotLoader + Composer
**Last Updated**: 2026-07-27

## Core Idea

The project uses **Nette RobotLoader** to auto-discover PHP classes in `server/` without manual `require` statements. This works alongside **Composer's PSR-4 autoloader** for `vendor/`. RobotLoader scans directories once and caches the index to avoid filesystem scans on every request.

## Key Points

- RobotLoader scans `server/` for classes under `WPLokerBJM\` namespace
- Composer handles `vendor/` (PSR-4: `WPLokerBJM\` → `server/`)
- RobotLoader index cached to avoid disk I/O (cache file checked before scan)
- Bootstrap loads both: Composer autoloader first, then RobotLoader
- PHP-DI `AutowireScanner` further discovers `#[Injectable]` classes for the container
- Script `composer dump-autoload --apcu -a -o` on install/update for optimized autoloads

## Bootstrap Autoload Setup

```php
// 1. Composer (PSR-4 for vendor + server/)
require_once __DIR__ . '/vendor/autoload.php';

// 2. Nette RobotLoader (discovers all server/ classes)
$loader = new RobotLoader();
$loader->addDirectory(__DIR__ . '/server');
$loader->setTempDirectory(__DIR__ . '/cache');
$loader->register();
```

## 📂 Codebase References

**Autoload Configuration**:
- `composer.json` — PSR-4 mapping: `WPLokerBJM\` → `server/`
- `composer.json` scripts — `dump-autoload --apcu -a -o` on install/update

**Bootstrap**:
- `mu-plugins/wplokerbjm-bootstrap.php` — Loads both autoloaders
- `vendor/nette/robot-loader/` — Nette RobotLoader library

**Container Discovery**:
- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — Discovers hook attributes
- `server/Core/Container/Support/AutowireScanner.php` — Discovers autowirable classes

## Related
- `concepts/architecture.md` — Bootstrap flow in context
- `concepts/hook-system.md` — How discovered classes get hooks registered
