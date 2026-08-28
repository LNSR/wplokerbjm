<!-- Context: backend/lookup | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Lookup: Namespaces

**Purpose**: Quick reference for all 33 namespaces under `WPLokerBJM\`
**Last Updated**: 2026-07-27

## Full Namespace Map

| Namespace | Directory | Purpose |
|-----------|-----------|---------|
| `Controllers\GraphQL\Resolvers\` | `server/Controllers/GraphQL/Resolvers/` | GraphQL resolver classes |
| `Controllers\GraphQL\Resolvers\Auth\` | `server/Controllers/GraphQL/Resolvers/Auth/` | JWT auth resolver |
| `Controllers\REST\` | `server/Controllers/REST/` | REST API controllers |
| `Controllers\Utilities\` | `server/Controllers/Utilities/` | Shared controller utilities |
| `Services\GraphQL\` | `server/Services/GraphQL/` | GraphQL registration + data |
| `Services\REST\` | `server/Services/REST/` | REST service layer |
| `Services\REST\Route\` | `server/Services/REST/Route/` | REST route definitions |
| `Services\WebHooks\` | `server/Services/WebHooks/` | Cloudflare CDN purge |
| `Services\Schema\` | `server/Services/Schema/` | Schema.org JSON-LD |
| `Services\Utilities\` | `server/Services/Utilities/` | Service utility classes |
| `Models\Schema\` | `server/Models/Schema/` | PostTypes, Taxonomies, CustomFields |
| `Repositories\` | `server/Repositories/` | Data access layer |
| `Factories\` | `server/Factories/` | Data factories |
| `Presenters\Components\` | `server/Presenters/Components/` | UI components (Carousel, Grid) |
| `QueryBuilders\` | `server/QueryBuilders/` | WP_Query wrappers |
| `Core\Container\` | `server/Core/Container/` | DI container, Init, bootstrap |
| `Core\Container\Attributes\` | `server/Core/Container/Attributes/` | #[Action], #[Filter] attributes |
| `Core\Container\Definitions\` | `server/Core/Container/Definitions/` | Core + Factory definitions |
| `Core\Container\Support\WPHooks\` | `server/Core/Container/Support/WPHooks/` | Scanner, Registry, ContainerLazyHookHandler |
| `Core\Abilities\` | `server/Core/Abilities/` | User capability management |
| `Core\Cron\` | `server/Core/Cron/` | WP-Cron jobs |
| `Core\Cron\Posts\` | `server/Core/Cron/Posts/` | Post lifecycle cron |
| `Core\Cron\Taxonomy\` | `server/Core/Cron/Taxonomy/` | Taxonomy cleanup cron |
| `Core\Plugins\` | `server/Core/Plugins/` | Plugin integrations |
| `Core\Plugins\ThirdParty\` | `server/Core/Plugins/ThirdParty/` | MetaBox, RankMath, WPGraphQL, LiteSpeed, JWT |
| `Core\Theme\` | `server/Core/Theme/` | Theme support, site icon, hooks |
| `Shared\Cache\` | `server/Shared/Cache/` | Redis object cache wrapper |
| `Shared\Log\` | `server/Shared/Log/` | Buffered logger |
| `Shared\Utilities\` | `server/Shared/Utilities/` | Sanitizer, SharedUtils |
| `Adapter\` | `server/Adapter/` | Redis adapter |
| `Configs\` | `server/Configs/` | Credential configuration |

## Naming Conventions

| Convention | Example |
|-----------|---------|
| Controllers | `*Controller.php` (e.g., `LowonganIngestController`) |
| Services | `*Service.php` (e.g., `LowonganIngestService`) |
| Resolvers | `*Resolver.php` (e.g., `JobsDataResolver`) |
| Repositories | `*Repository.php` (e.g., `JobRepository`) |
| Schema | `*s.php` plural (e.g., `PostTypes`, `Taxonomies`) |
| Query Builders | `*Query.php` (e.g., `JobQuery`) |

## 📂 Codebase References

**Autoload Config**:
- `composer.json` — PSR-4: `WPLokerBJM\` → `server/`

**Bootstrap**:
- `mu-plugins/wplokerbjm-bootstrap.php` — Entry point

## Related
- `concepts/architecture.md` — Directory layout overview
- `lookup/hooks.md` — Hooks registered per class
- `lookup/dependencies.md` — Composer packages
