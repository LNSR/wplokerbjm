<!-- Context: project-intelligence/nav | Priority: high | Version: 1.0 | Updated: 2026-06-21 -->

# Project Intelligence — Lowker Site (Root)

> Monorepo root: infrastructure, CI/CD, and microservice context. Start here for the big picture.

## Structure

```
Lowker-site/
├── .opencode/context/project-intelligence/
│   ├── navigation.md              # This file — quick overview
│   └── technical-domain.md        # Infra: Docker, Caddy, Cloudflare, CI/CD, microservice
├── sveltekit/.opencode/context/project-intelligence/
│   ├── navigation.md              # Frontend nav
│   └── technical-domain.md        # SvelteKit, GraphQL, TypeScript, Tailwind
└── wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/
    ├── navigation.md              # Backend nav (+ parent context link)
    ├── technical-domain.md        # PHP 8.5, DI container, WP hooks, REST, GraphQL
    ├── business-domain.md         # Business context
    ├── business-tech-bridge.md    # Business → technical mapping
    ├── decisions-log.md           # Architecture decisions
    └── living-notes.md            # Active issues, debt, questions
```

## Quick Routes

| What You Need                      | File                                                                                      |
| ---------------------------------- | ----------------------------------------------------------------------------------------- |
| Infrastructure & deployments       | `./.opencode/context/project-intelligence/technical-domain.md`                            |
| SvelteKit frontend architecture    | `./sveltekit/.opencode/context/project-intelligence/technical-domain.md`                  |
| WordPress backend architecture     | `./wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/technical-domain.md` |
| Business context & "why"           | `./wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/business-domain.md` |
| Decision history & rationale       | `./wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/decisions-log.md` |
| Current issues & open questions    | `./wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/living-notes.md` |

## Usage

**New Developer / Agent**:
1. Start here (root `navigation.md`)
2. Read `technical-domain.md` for infrastructure context
3. Navigate to the component you're working on (frontend or backend)
4. Read the full context chain in each component

**Working on Infrastructure**: Root `technical-domain.md` covers everything.

**Working on Frontend**: `sveltekit/.opencode/...` has SvelteKit-specific patterns.

**Working on Backend**: `wordpress/.../.opencode/...` has PHP/WP-specific patterns.

## Component Relationships

```
Root (.opencode/)           ← Docker, Caddy, CI/CD, Python bot
├── sveltekit/.opencode/    ← SvelteKit frontend (headless consumer)
└── wordpress/.../.opencode/← WordPress backend (API provider)
```

The root context covers infrastructure shared by ALL components. Each component has its own context for component-specific patterns.

## Maintenance

Keep this folder current:
- Update when infrastructure changes (new services, CI/CD changes)
- Add new components' context links when created
- Review component structure for accuracy

**Management Guide**: See `/home/maulana/.config/opencode/context/core/standards/project-intelligence-management.md` for complete lifecycle management.
