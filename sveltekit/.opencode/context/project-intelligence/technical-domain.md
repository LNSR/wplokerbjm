<!-- Context: project-intelligence/technical | Priority: critical | Version: 2.0 | Updated: 2026-11-01 -->

# Technical Domain — Lowker Site Frontend

> SvelteKit 3 frontend — headless job board consuming a WordPress GraphQL API. Svelte 5 runes, TypeScript 6, Vite 8, Tailwind CSS 4, Cloudflare Workers.

## Quick Reference

- **Purpose**: Understand frontend architecture, component patterns, data fetching, styling, and deployment
- **Update When**: Components, data patterns, build/config changes, dependency updates, Kit/Vite majors
- **Audience**: Frontend developers working on the SvelteKit app

## Primary Stack

| Layer | Technology | Version | Notes |
|---|---|---|---|
| Framework | SvelteKit | 3.x (3.1.1) | File-based routing; SSR-first with SPA navigation |
| UI | Svelte 5 + runes | 5.57.x | `$props()`, `$state()`, `$derived()`, `$derived.by()` |
| Language | TypeScript | 6.0.x | Strict mode; typia runtime validation |
| Bundler | Vite + Rolldown | 8.3.x | Config lives in `vite.config.ts` |
| Styling | Tailwind CSS + DaisyUI | 4.x + 5.x | CSS-first Tailwind 4; `@reference` for component styles |
| Data | GraphQL (urql + gql.tada) | 5.x / 1.x | Typed queries; urql cache |
| Adapter | `@sveltejs/adapter-cloudflare` | 8.x | Workers + static assets via `wrangler.jsonc` |
| Runtime | Cloudflare Workers | wrangler 4.x | `.svelte-kit/cloudflare/_worker.js` |
| Package manager | Bun | 1.4.x | `bun install`, `bun run …` |

## Architecture Pattern

```
Type: Headless SSR app with SPA navigation
Flow: +page.server.ts (server load) → APIServiceServer → GraphQL (urql) → components
      Browser interactions → APIServiceBrowser → comlink worker → GraphQL
```

SvelteKit renders HTML on the server for SEO and first paint, then hydrates into an SPA. Server-only data access goes through `src/lib/server` and `*.server.ts` modules. Browser-side GraphQL runs in a Web Worker wrapped with comlink.

## Project Structure

```
sveltekit/
├── src/
│   ├── lib/
│   │   ├── assets/css/           # Tailwind entry + WordPress styles
│   │   ├── components/           # UI, layout, and page components
│   │   ├── composables/          # Class-based UI controllers (*.svelte.ts)
│   │   ├── features/             # Device detection, virtualization
│   │   ├── server/               # Server-only cache, constants, middleware, utils
│   │   ├── services/             # Client-side service helpers
│   │   ├── stores/               # Runes-based global stores (*.svelte.ts)
│   │   └── utils/                # Shared utilities
│   ├── routes/                   # SvelteKit routes (+page, +layout, +server)
│   ├── services/
│   │   └── graphql/              # urql config, fetch classes, query documents
│   ├── types/                    # App, WordPress, and worker types
│   ├── utils/                    # Environment, navigation, decorators
│   ├── workers/                  # Web Workers (bookmark, clock, network/GraphQL)
│   ├── app.d.ts                  # Global App types and CacheStorage augmentation
│   ├── app.html                  # HTML shell
│   ├── env.ts                    # defineEnvVars declarations
│   └── hooks.server.ts           # Server hooks (auth, ETag, theme, device, cache)
├── public/                       # Static assets, including ~partytown/
├── vite.config.ts                # SvelteKit + Vite config (sveltekit({...}))
├── tsconfig.json                 # Extends $app/tsconfig
├── eslint.config.ts              # Flat ESLint config
├── wrangler.jsonc                # Workers, assets, KV, routes
└── package.json                  # Bun scripts and dependencies
```

## Svelte 5 Runes Pattern

Runes mode is enabled globally in `sveltekit({ compilerOptions: { runes: true } })`.

| Rune | Purpose | Example |
|---|---|---|
| `$props()` | Component props | `let { title, jobs }: Props = $props();` |
| `$state()` | Reactive local state | `let count = $state(0);` |
| `$derived()` | Computed value | `let doubled = $derived(count * 2);` |
| `$derived.by()` | Computed value with logic | `let filtered = $derived.by(() => jobs.filter(...));` |

```svelte
<script lang="ts">
  interface Props {
    title: string;
    jobs: JobSummary[];
  }

  let { title, jobs }: Props = $props();
  let searchQuery = $state('');
  let filtered = $derived.by(() =>
    jobs.filter((j) => j.title.toLowerCase().includes(searchQuery.toLowerCase()))
  );
</script>
```

Prefer a named `Props` type with `$props()` over `$props<Props>()`; the latter is not accepted by the current Svelte tooling.

## Module Imports

`package.json#imports` declares Node subpath imports:

```json
{
  "imports": {
    "#lib/*": "./src/lib/*",
    "#src/*": "./src/*"
  }
}
```

- `#lib/*` covers `src/lib`; `#src/*` covers every other directory under `src`.
- Every package-subpath import **must** include a file extension:
  - TypeScript modules: `#src/services/graphql/APIService.js`
  - Runes modules: `#lib/stores/Route.svelte.js`
  - Components: `#lib/components/ui/Shared/JobCard.svelte`
  - Type declarations: `#src/graphql-env.d.ts`
  - Directory entry points: `#src/types/index.js`
  - Vite query imports keep `.ts`: `#src/workers/network/graphql/fetch.worker.ts?worker`
- There are no path aliases: `sveltekit({ alias })`, `@`, `@components`, and `@css` were removed from `vite.config.ts`.
- CSS imports and Tailwind `@reference` directives use relative paths instead of an alias.

## Data Fetching (`+page.server.ts`)

Server loads use `APIServiceServer` from `src/services/graphql/APIService.ts`. The contract is to return data with a graceful error shape and never throw to the client.

```typescript
import type { PageServerLoad } from './$types';
import { APIServiceServer } from '#src/services/graphql/APIService.js';

export const load: PageServerLoad = async ({ params }) => {
  try {
    const job = await APIServiceServer.fetchJobDetailGraphQL(params.slug);
    return { job, error: null };
  } catch (error) {
    return { job: null, error: 'Failed to load job' };
  }
};
```

**Rules**:

- Server-only modules live under folder `**/server/` or use the `*.server.ts` suffix.
- Never import server-only modules into browser code; Kit 3 enforces this at build time.
- Return empty/null data on failure so pages still render.

## GraphQL (urql + gql.tada)

- `urql` manages request lifecycle and caching.
- `gql.tada` provides compile-time typed documents.
- `src/services/graphql/fetch.ts` exports `BrowserFetch` and `ServerFetch`.
- `src/workers/network/graphql/fetch.worker.ts` exposes `BrowserFetch` through comlink.

### Query Organization

Query documents live in `src/services/graphql/query/`:

| Directory | Purpose | Files |
|---|---|---|
| `browser/` | Browser-only queries | `auth.ts`, `job.ts`, `taxonomy.ts`, `theme.ts` |
| `ssr/` | Server-side queries | `job.ts`, `theme.ts` |
| `shared/` | Fragments and shared queries | `job.ts` (`FRAGMENT_*`, `GET_CAROUSEL`, `GET_JOB_GRID`) |

`ssr/` is intentionally **not** named `server/`: Kit 3 treats any `server` directory as server-only, and `fetch.worker.ts` imports `fetch.ts`, which contains `ServerFetch`. A future refactor should split browser and server fetch modules so the directory can follow the stricter convention.

**Rules**:

- Modules in `browser/` must never import from `ssr/`.
- Shared fragments live in `shared/` and are imported by both sides.
- Place a new query by which fetch class consumes it.

## Reactive Stores

Global state lives in `src/lib/stores/*.svelte.ts`:

- `JobListingStore.svelte.ts` — job listing, filters, pagination
- `Taxonomy.svelte.ts` — taxonomy terms
- `Bookmark.svelte.ts` — bookmarks and IndexedDB sync
- `Route.svelte.ts` — route and side-panel state
- `Theme.svelte.ts` — theme selection
- `ComponentRegistry.svelte.ts` — component registry

Use classes with `$state()` fields and methods; import them as singletons.

## Styling (Tailwind 4 + DaisyUI 5)

- Entry point: `src/lib/assets/css/app.css`.
- WordPress-specific styles: `src/lib/assets/css/wordpress.css`.
- Tailwind 4 is CSS-first; configuration lives in `src/lib/assets/css/app.css`.
- DaisyUI 5 provides components and themes.
- In component `<style>` blocks, use a relative `@reference` to `src/lib/assets/css/app.css` before `@apply` or theme utilities.
- Responsive design is mobile-first (`sm:`, `md:`, `lg:`).

## Cloudflare Workers Deployment

- Adapter: `@sveltejs/adapter-cloudflare@8`.
- Config: `wrangler.jsonc` (compatibility date 2026-09-23, `nodejs_compat`, KV binding `wplokerbjm`, HTTPS dev port 8787).
- Build output: `.svelte-kit/cloudflare/_worker.js` plus assets in `.svelte-kit/cloudflare`.
- `sveltekit({ files: { assets: "public" } })` keeps static assets in `public/`; do not remove it.
- Cloudflare APIs are **not** on `platform`:
  - `env`, `ctx.waitUntil` → import from `cloudflare:workers`
  - `request.cf` → property on `Request`
  - `caches` → global (`caches.default` is used by `src/lib/server/cache/eTagCache.ts`)
- `src/app.d.ts` augments `CacheStorage` with `default: Cache`.

## TypeScript and Checks

- `tsconfig.json` extends `$app/tsconfig` and includes `src` plus `vite.config.ts`.
- `bun run check` runs `svelte-check` and `tsc --noEmit` in parallel.
- `bun run prepare` runs `ts-patch install` and `svelte-kit sync`.
- `typia` validates GraphQL responses at runtime; `@typia/unplugin` handles Vite transforms.
- `#lib/*` and `#src/*` imports require explicit file extensions.

## Security Requirements

- Server-only code stays in `src/lib/server/` or `*.server.ts`; Kit 3 blocks accidental browser imports.
- GraphQL calls that need secrets run through `APIServiceServer` / `event.fetch`.
- `hooks.server.ts` filters forwarded cookies to WordPress/wp/JWT cookies and injects `Authorization`.
- Environment variables are declared in `src/env.ts` with `defineEnvVars`; import public values from `$app/env/public` and server values from `$app/env/private`.
- Do not reintroduce `platform.env` or other removed adapter APIs.

## Codebase References

- Components: `src/lib/components/`
- Composables: `src/lib/composables/`
- Server utilities: `src/lib/server/`
- GraphQL: `src/services/graphql/`
- Queries: `src/services/graphql/query/{browser,ssr,shared}/`
- Workers: `src/workers/`
- Routes: `src/routes/`

## Related Files

- `./navigation.md` — Frontend context navigation
- `../../../../.opencode/context/project-intelligence/technical-domain.md` — Root infrastructure context
- `../../../../wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/technical-domain.md` — Backend API context
