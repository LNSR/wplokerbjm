<!-- Context: project-intelligence/technical | Priority: critical | Version: 1.1 | Updated: 2026-07-04 -->

# Technical Domain — Lowker Site Frontend

> SvelteKit frontend — headless job board consuming WordPress GraphQL API. Svelte 5 runes, TypeScript 6, Tailwind CSS 4, Cloudflare Workers.

## Quick Reference

- **Purpose**: Understand frontend architecture, component patterns, data fetching, styling
- **Update When**: New components, data patterns, build/config changes, dependency updates
- **Audience**: Frontend developers working on the SvelteKit app

## Primary Stack

| Layer           | Technology              | Version | Rationale                                                    |
| --------------- | ----------------------- | ------- | ------------------------------------------------------------ |
| Framework       | SvelteKit               | latest  | Svelte 5 runes mode; file-based routing; SSR/SSG support     |
| UI              | Svelte 5 + runes        | 5.x     | `$props()`, `$state()`, `$derived()`, `$derived.by()`        |
| Language        | TypeScript              | 6.x     | Strict mode; typia for runtime validation                    |
| Styling         | Tailwind CSS + DaisyUI  | 4 + 5   | Utility-first CSS; PostCSS with `@reference`; DaisyUI components |
| Data Fetching   | GraphQL (urql + gql.tada)| latest  | Typed GraphQL queries; urql client with cache                |
| Package Manager | Bun                     | latest  | Fast installs; native TypeScript support                     |
| Adapter         | Cloudflare Workers      | latest  | Edge deployment via Wrangler CLI                             |
| Workers         | Web Workers + comlink   | —       | Off-thread GraphQL data fetching for performance             |

## Architecture Pattern

```
Type: Headless SPA via SvelteKit (SSR-first, SPA navigation)
Pattern: +page.server.ts (server load) → GraphQL (urql) → $state() stores → Svelte 5 components
```

**Why this architecture?** SvelteKit provides SSR for SEO and initial load speed, with SPA-style client-side navigation. GraphQL via urql + gql.tada gives fully typed data fetching. Web Workers offload GraphQL queries off the main thread. Cloudflare Workers adapter enables edge deployment at global scale.

## Project Structure

```
sveltekit/
├── src/
│   ├── lib/
│   │   ├── components/          # Reusable UI components
│   │   ├── stores/              # $state()-based reactive stores
│   │   │   ├── JobListingStore.svelte.ts
│   │   │   └── taxonomyStore.svelte.ts
│   │   ├── graphql/             # GraphQL queries, urql client, gql.tada types
│   │   ├── workers/             # Web Workers + comlink for off-thread GraphQL
│   │   ├── utils/               # Utility functions
│   │   └── css/                 # Tailwind entry point, PostCSS config
│   ├── routes/                  # SvelteKit file-based routing
│   │   ├── +page.svelte         # Home page
│   │   ├── +page.server.ts      # Server load (SSR data fetching)
│   │   └── lowongan/            # Job listing routes
│   ├── app.css                  # Tailwind imports
│   ├── app.d.ts                 # TypeScript declarations
│   └── app.html                 # HTML shell
├── static/                      # Static assets
├── svelte.config.js             # SvelteKit config (adapter, aliases)
├── tailwind.config.ts           # Tailwind + DaisyUI config
├── postcss.config.js            # PostCSS (with @reference)
├── tsconfig.json                # TypeScript config
├── wrangler.toml               # Cloudflare Workers config
└── package.json                 # Dependencies (Bun)
```

## Svelte 5 Runes Pattern

**Concept**: Svelte 5's runes mode replaces legacy `$:` reactive declarations and `export let` props. All components use the new runes syntax.

**Key runes**:

| Rune              | Purpose                                 | Example                                      |
| ----------------- | --------------------------------------- | -------------------------------------------- |
| `$props()`        | Component props (replaces `export let`) | `let { title, jobs }: Props = $props();`     |
| `$state()`        | Reactive local state                    | `let count = $state(0);`                     |
| `$derived()`      | Computed value from state               | `let doubled = $derived(count * 2);`         |
| `$derived.by()`   | Complex derived with logic block        | `let filtered = $derived.by(() => {...});`   |

**Example**:

```svelte
<script lang="ts">
  interface Props {
    title: string;
    jobs: JobSummary[];
  }

  let { title, jobs }: Props = $props();
  let searchQuery = $state('');
  let filtered = $derived.by(() =>
    jobs.filter(j => j.title.toLowerCase().includes(searchQuery.toLowerCase()))
  );
</script>

<h1>{title}</h1>
{#each filtered as job}
  <JobCard {job} />
{/each}
```

## Path Aliases

Aliases configured in `svelte.config.js` and `tsconfig.json`:

| Alias            | Path               | Usage                             |
| ---------------- | ------------------ | --------------------------------- |
| `@components`    | `src/lib/components` | `import Card from '@components/Card.svelte'` |
| `@css`           | `src/lib/css`      | `import '@css/global.css'`        |
| `@`              | `src/lib`          | `import { store } from '@'`       |

## Data Fetching Pattern (+page.server.ts)

**Concept**: Page-level data fetching happens in `+page.server.ts` with `PageServerLoad`. Each function uses try/catch with graceful fallbacks — never throws to the client.

**Pattern**:

```typescript
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ fetch, url }) => {
  try {
    const response = await fetch('/api/graphql', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query: JOBS_QUERY, variables: { paged: 1 } }),
    });
    const data = await response.json();
    return { jobs: data.jobs, error: null };
  } catch (error) {
    return { jobs: [], error: 'Failed to load jobs' };
  }
};
```

**Key rules**:
- Always return `{ data, error }` shape — never throw
- Use typed queries via gql.tada for full type safety
- Return `null`/empty arrays on failure, not errors
- Pages render even when data fails (graceful degradation)

## GraphQL (urql + gql.tada)

**Concept**: Typed GraphQL queries using `gql.tada` for compile-time type generation. `urql` client handles caching and request lifecycle.

**Stack**:
- **urql**: GraphQL client with built-in caching (document cache)
- **gql.tada**: TypeScript-first GraphQL without code generation — types inferred from query strings
- **Web Workers**: comlink wraps urql client in a Web Worker for off-thread execution

**Web Worker Pattern**:

```typescript
// main thread
import { wrap } from 'comlink';
const worker = new Worker(new URL('./graphql.worker.ts', import.meta.url));
const { query } = wrap<WorkerAPI>(worker);

// Off-thread GraphQL query
const result = await query(JOBS_QUERY, { paged: 1 });
```

### Query File Organization

**Concept**: GraphQL query files are split into `browser/`, `server/`, and `shared/` subdirectories by which fetch class consumes them (`BrowserFetch`, `ServerFetch`, or both via `SharedFetch`).

**Why split**: `gql.tada`'s `graphql()` tagged template calls are seen as potentially side-effecting by Rollup/Rolldown — tree-shaking fails. When browser-only and server-only queries lived in the same file, unused query ASTs leaked into both the main-thread and web-worker bundles (~120KB wasted per query group). Splitting by usage boundary at the **import level** ensures each bundle only pulls the query modules it actually needs.

**Directory structure** (`src/services/graphql/query/`):

| Directory    | Purpose                         | Files                                     |
|-------------|----------------------------------|-------------------------------------------|
| `browser/`  | `BrowserFetch`-only queries    | `auth.ts`, `job.ts`, `taxonomy.ts`, `theme.ts` |
| `server/`   | `ServerFetch`-only queries     | `job.ts`, `theme.ts`                      |
| `shared/`   | Fragments + `SharedFetch` queries | `job.ts` (FRAGMENT_*, GET_CAROUSEL, GET_JOB_GRID) |

**Key rules**:
- Modules in `browser/` must **never** import from `server/` (or vice versa)
- Shared GraphQL fragments (`FRAGMENT_*`) live in `shared/` and are imported by both
- New query → determine which class uses it → place in the matching directory
- `apiservice-helper.ts` imports only from `shared/` (fragments)

**📂 Codebase References**: `src/services/graphql/query/{browser,server,shared}/`

## Reactive Stores Pattern

**Concept**: Global reactive state using `$state()`-based stores in `*.svelte.ts` files. Composables pattern inspired by Vue composition API.

**Store files** (in `src/lib/stores/`):
- `JobListingStore.svelte.ts` — job listing state, filters, pagination
- `taxonomyStore.svelte.ts` — taxonomy/category state

**Pattern**:

```typescript
// taxonomyStore.svelte.ts
export class taxonomyStore = {
  categories: $state<string[]>([]),
  selectedCategory: $state<string | null>(null),

  setCategory(cat: string) {
    this.selectedCategory = cat;
  },
};
```

## Styling (Tailwind CSS 4 + DaisyUI 5)

**Concept**: Utility-first CSS via Tailwind 4 with DaisyUI 5 component library. PostCSS with `@reference` for composable styles.

**Key points**:
- Tailwind 4: JIT mode by default, new config format
- DaisyUI 5: Pre-built components (buttons, cards, modals, navs)
- PostCSS `@reference`: Import Tailwind utilities without bundling full CSS
- Responsive: mobile-first with `sm:`, `md:`, `lg:` breakpoints
- Theming: DaisyUI theme system with custom Lowker color palette

## Cloudflare Workers Deployment

**Concept**: SvelteKit app deployed to Cloudflare Workers edge network via Wrangler.

**Key points**:
- Adapter: `@sveltejs/adapter-cloudflare-workers`
- Config: `wrangler.toml` with routes, bindings, KV namespaces
- Build: `bun run build` → Wrangler deploy
- Edge: Runs at Cloudflare's global edge network (low latency worldwide)

## TypeScript 6 + typia

**Concept**: TypeScript 6 with strict mode. `typia` for runtime type validation of API responses.

**Key points**:
- `strict: true` in tsconfig
- `typia` validates GraphQL responses at runtime (catches schema drift)
- Type-only imports for GraphQL types from gql.tada
- Path aliases shared between `svelte.config.js` and `tsconfig.json`

## Security Requirements

- GraphQL endpoint calls via server-side `fetch` in `+page.server.ts` (not exposed to client)
- Environment variables (`.env`) never bundled — server-only
- CSP headers configured in `svelte.config.js`
- Input validation: typia runtime checks on all API responses

## 📂 Codebase References

**Components**: `src/lib/components/`
**Stores**: `src/lib/stores/`
**GraphQL**: `src/services/graphql/query/{browser,server,shared}/`
**Workers**: `src/workers/`
**Routes**: `src/routes/`

## Related Files

- `./navigation.md` — Frontend context navigation
- `../../.opencode/context/project-intelligence/technical-domain.md` — Root infrastructure context
- `../../wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/technical-domain.md` — Backend API context
