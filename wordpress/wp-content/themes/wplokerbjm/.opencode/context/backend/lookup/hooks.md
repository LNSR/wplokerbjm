<!-- Context: backend/lookup | Priority: high | Version: 1.0 | Updated: 2026-07-27 -->

# Lookup: Registered Hooks

**Purpose**: Reference of all registered WordPress actions and filters
**Last Updated**: 2026-07-27

## Actions

| Hook | Class | Method | Notes |
|------|-------|--------|-------|
| `init` | `PostTypes` | `registerLowonganPostType` | Registers `lowongan` CPT |
| `init` | `Taxonomies` | `registerPerusahaanTaxonomy` | Perusahaan taxonomy |
| `init` | `Taxonomies` | `registerKategoriTaxonomy` | Kategori lowongan |
| `init` | `Taxonomies` | `registerLokasiTaxonomy` | Lokasi pekerjaan |
| `init` | `Taxonomies` | `registerJenisPekerjaanTaxonomy` | Jenis pekerjaan |
| `init` | `Taxonomies` | `registerGenderTaxonomy` | Gender taxonomy |
| `init` | `Taxonomies` | `registerPendidikanTaxonomy` | Pendidikan taxonomy |
| `graphql_register_types` | `GraphQLRegistration` | `registerTypes` | All GraphQL types + fields |
| `save_post` | `Cloudflare` | `purgeCache` | CDN purge on post save |
| `deleted_post` | `Cloudflare` | `purgeCache` | CDN purge on post delete |
| `added_post_meta` | `Cloudflare` | `purgeOnMetaChange` | Self-unregister after first fire |
| `updated_post_meta` | `Cloudflare` | `purgeOnMetaChange` | Self-unregister after first fire |
| `deleted_post_meta` | `Cloudflare` | `purgeOnMetaChange` | Self-unregister after first fire |
| `created_term` | `Cloudflare` | `purgeOnTermChange` | Self-unregister after first fire |
| `edit_term` | `Cloudflare` | `purgeOnTermChange` | Self-unregister after first fire |
| `delete_term` | `Cloudflare` | `purgeOnTermChange` | Self-unregister after first fire |

## Global Hooks (GlobalHooks)

| Type | Hook | Purpose |
|------|------|---------|
| Action | `template_redirect` | Block WP admin for non-admins |
| Filter | `robots_txt` | Custom robots.txt |
| Filter | `wp_sitemaps_enabled` | Disable default sitemaps |
| Action | `wp_head` | Custom head output |
| Filter | `locale` | Language/locale handling |

## Theme Hooks (ThemeHooks)

| Type | Hook | Purpose |
|------|------|---------|
| Action | `after_setup_theme` | Theme support registration |
| Filter | `site_icon_meta_tags` | Custom site icon |

## Cron Hooks

| Hook | Purpose |
|------|---------|
| WP-Cron scheduled | Taxonomy cleanup (orphaned terms) |
| WP-Cron scheduled | Post status updates (expired jobs) |
| WP-Cron scheduled | Post deletion (auto-cleanup) |

## Plugin Integration Hooks

| Plugin | Hooks Used |
|--------|-----------|
| MetaBox | `rwmb_meta_boxes`, `rwmb_{$field_type}_html` |
| RankMath | `rank_math/frontend/head` |
| WPGraphQL | `graphql_register_types`, `graphql_{$type}_fields` |
| LiteSpeed Cache | Cache control hooks |
| JWT Auth | `jwt_auth_valid_credential_response` |

## 📂 Codebase References

**Hook Registration**:
- `server/Core/Container/Support/WPHooks/WPHooksContainerRegistry.php` — Central hook registry
- `server/Core/Container/Support/WPHooks/WPHooksScanner.php` — Attribute discovery
- `server/Core/Container/Definitions/Factory.php` — Lazy loading definitions

**Hook Sources**:
- `server/Core/GlobalHooks.php` — Global WP hooks
- `server/Core/Theme/ThemeHooks.php` — Theme support hooks
- `server/Core/Plugins/ThirdParty/` — Plugin integration hooks
- `server/Core/Cron/WPCron.php` — Scheduled cron hooks

## Related
- `concepts/hook-system.md` — How hooks work
- `examples/adding-a-hook.md` — Adding new hooks
- `lookup/namespaces.md` — Class locations
