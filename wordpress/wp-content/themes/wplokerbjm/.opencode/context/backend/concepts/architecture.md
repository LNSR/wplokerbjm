<!-- Context: backend/concepts | Priority: critical | Version: 1.0 | Updated: 2026-07-27 -->

# Concept: Architecture

**Purpose**: Understand the DI container, bootstrap flow, and directory structure
**Last Updated**: 2026-07-27

## Core Idea

The WPLokerBJM theme uses **PHP-DI** for dependency injection, **attribute-driven WordPress hooks**, and **Nette RobotLoader** for autoloading. All logic lives in `server/` — no inline `add_action`/`add_filter` calls. The container is compiled to `cache/CompiledContainer.php` for production performance.

## Bootstrap Flow

1. `mu-plugins/wplokerbjm-bootstrap.php` → `WPLokerBJM\Bootstrap::boot()`
2. Loads Composer autoloader + Nette RobotLoader
3. `WPLokerBJMContainer::getContainer()` → builds PHP-DI container
4. Merged definitions: `Core::getDefinitions()` + `Factory::getDefinitions()`
5. `Init::initialize()` → `WPHooksContainerRegistry::initialize()` → registers all hooks
6. Container compiled to `cache/CompiledContainer.php` (skipped if cache exists)

## Directory Layout

```
server/
├── Controllers/      # REST/GraphQL controllers
├── Core/             # Container, Init, hooks, cron, theme, plugins
├── Factories/        # Data factories (JobDataFactory)
├── Models/Schema/    # PostTypes, Taxonomies, CustomFields
├── Presenters/       # UI components (JobCarousel, JobGrid)
├── QueryBuilders/    # JobQuery, TaxonomyQuery
├── Repositories/     # Data access layer
├── Services/         # GraphQL, REST, Schema, WebHooks
├── Shared/           # Cache, Logger, Utilities
├── Adapter/          # Redis adapter
└── Configs/          # CredentialConfig (Cloudflare, Redis)
```

## Key Points

- **No `add_action`/`add_filter` in source** — all via `#[Action]`/`#[Filter]` attributes
- **Lazy hooks** — services resolved at hook-fire time, not registration time
- **Singleton container** — `WPLokerBJMContainer::getContainer()` returns cached instance
- **Environment-aware**: `.env` loaded via `vlucas/phpdotenv`, `SharedUtils::isDevelopment()`
- **Headless theme** — WP admin blocked via redirect hooks, data served via GraphQL/REST

## 📂 Codebase References

**Bootstrap/Entry**:
- `mu-plugins/wplokerbjm-bootstrap.php` — Theme bootstrap entry point
- `server/Core/Container/Container.php` — PHP-DI container builder with compilation
- `server/Core/Container/Init.php` — Hook initialization via WPHooksContainerRegistry
- `server/Core/Container/Definitions/` — Core + Factory definition providers

**Environment**:
- `.env` / `.env.example` — Environment configuration
- `server/Configs/CredentialConfig.php` — Cloudflare + Redis credentials

## Related
- `concepts/hook-system.md` — How attribute hooks work
- `concepts/autoloading.md` — Nette RobotLoader integration
- `concepts/caching.md` — Redis + Cloudflare strategy
- `lookup/namespaces.md` — Full namespace reference
