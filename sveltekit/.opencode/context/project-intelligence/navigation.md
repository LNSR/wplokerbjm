<!-- Context: project-intelligence/nav | Priority: high | Version: 1.0 | Updated: 2026-06-21 -->

# Project Intelligence — SvelteKit Frontend

> SvelteKit frontend context: architecture, components, data fetching, deployment.

## Quick Routes

| What You Need                   | File                                                                                      |
| ------------------------------- | ----------------------------------------------------------------------------------------- |
| Frontend architecture & stack   | `./technical-domain.md`                                                                   |
| Parent infrastructure context   | `../../.opencode/context/project-intelligence/technical-domain.md`                         |
| WordPress backend context       | `../../wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/technical-domain.md` |

## Usage

**Working on Frontend**:
1. Start here (`navigation.md`)
2. Read `technical-domain.md` for SvelteKit patterns
3. Reference root context for infrastructure details
4. Reference WordPress context for API contracts

## Component Relationships

```
Root (.opencode/)           ← Docker, Caddy, CI/CD, Python bot
    └── sveltekit/          ← YOU ARE HERE: SvelteKit frontend
    └── wordpress/          ← WordPress backend (API provider)
```

The SvelteKit app consumes WordPress's GraphQL and REST APIs. Infrastructure (Docker, Caddy, Cloudflare) is managed at the root level.

## Maintenance

Keep this folder current:
- Update when frontend patterns change (new Svelte features, new libraries)
- Add new documentation when significant components are added
- Review stack versions for accuracy

**Parent Context**: See `../../.opencode/context/project-intelligence/navigation.md` for the full project overview.
**Management Guide**: See `/home/maulana/.config/opencode/context/core/standards/project-intelligence-management.md` for complete lifecycle management.
