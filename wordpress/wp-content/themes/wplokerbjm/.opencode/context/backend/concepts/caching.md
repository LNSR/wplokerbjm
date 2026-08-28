<!-- Context: backend/concepts | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Concept: Caching

**Purpose**: Understand Redis object cache strategy, key management, and Cloudflare CDN purge
**Last Updated**: 2026-07-27

## Core Idea

Caching operates at two levels: **Redis object cache** (via WordPress `wp_cache_*` API with `wplokerbjm_obj_` prefix) for data (jobs, taxonomy terms, GraphQL responses), and **Cloudflare CDN** (full-zone purge on content change) for HTML/edge cache. The `Cache` class wraps WP object cache functions with logging and fallbacks.

## Key Points

- **Object cache**: Redis via `wp_cache_*` functions, prefix `wplokerbjm_obj_`
- **Cache keys**: Centralized in `CacheKey` class (no magic strings) — ~30 defined keys
- **ETag support**: GraphQL ETag prefix for conditional requests
- **CDN purge**: Cloudflare `purge_everything` on any content change (post, meta, term)
- **Self-unregister pattern**: Meta/term hooks unregister after first fire to avoid duplicate purges
- **Development mode**: CDN purge skipped when `SharedUtils::isDevelopment()` returns true

## Cache Key Structure

| Category | Key Prefix | Purpose |
|----------|-----------|---------|
| Jobs | `job_data_`, `job_schema_` | Job post data, schema.org |
| GraphQL | `graphql_job_card_`, `graphql_job_detail_` | GraphQL responses |
| Search | `auto_suggestion_`, `dynamic_search_` | Search + autocomplete |
| Taxonomy | `all_taxonomy_terms`, `post_taxonomies_` | Taxonomy queries |
| Presenters | `carousel_jobs`, `job_grid_` | UI component data |
| ETag | `graphql_etag_` | Conditional request headers |

## CDN Purge Flow

```
Post Saved → Cloudflare::purgeCache() → POST /zones/{zone}/purge_cache
Post Meta Changed → Cloudflare::purgeOnMetaChange() → self-unregister → purge
Term Changed → Cloudflare::purgeOnTermChange() → self-unregister → purge
```

## 📂 Codebase References

**Object Cache**:
- `server/Shared/Cache/Cache.php` — `Cache` class + `CacheKey` constants (~395 lines)
- `server/Adapter/Redis.php` — Redis-specific operations (pattern delete, direct connection)

**CDN Purge**:
- `server/Services/WebHooks/Cloudflare.php` — Cloudflare zone purge with credential check
- `server/Configs/CredentialConfig.php` — Token + zone credentials

## Related
- `concepts/architecture.md` — How caching fits in bootstrap
- `concepts/hook-system.md` — Hook lifecycle for purge-on-change
- `errors/common-issues.md` — Cache rebuild + credential issues
