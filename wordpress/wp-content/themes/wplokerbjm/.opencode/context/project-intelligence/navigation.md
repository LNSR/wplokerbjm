<!-- Context: project-intelligence/nav | Priority: high | Version: 1.7 | Updated: 2026-11-19 -->

# Project Intelligence

> Start here for quick project understanding across the theme's technical domains.

## Structure

```
/home/maulana/Project/Lowker-site/wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/
├── navigation.md              # This file - quick overview
├── technical-domain.md        # Stack, architecture, technical decisions
├── wphooks-ownership-raii.md  # Hook ownership, weak refs, RAII cleanup chain
└── wphooks-registry-events.md # Registry event bridge (control-plane selectors)
```

## Quick Routes

| What You Need | File | Description |
|---------------|------|-------------|
| Understand the "how" | `technical-domain.md` | Stack, architecture, integrations |
| Hook ownership / cleanup | `wphooks-ownership-raii.md` | Strong vs weak owners, RAII destructor chain, leak rules |
| Registry events / adapters | `wphooks-registry-events.md` | Event bridge, `EVENT_BRIDGE_HOOKS`, boot modes |
| All of the above | Read all files | Full project intelligence |

## Usage

**New Team Member / Agent**:
1. Start with `navigation.md` (this file)
2. Read `technical-domain.md` for stack and architecture
3. Drill into `wphooks-ownership-raii.md` for lifecycle/cleanup and `wphooks-registry-events.md` when calling registries through WordPress events

**Quick Reference**:
- Technical focus → `technical-domain.md`
- Hook ownership / cleanup → `wphooks-ownership-raii.md`
- Registry events → `wphooks-registry-events.md`

## Parent Context

This is a component-level context. For infrastructure, CI/CD, and cross-component concerns, see the root context:

- `../../../../../../.opencode/context/project-intelligence/navigation.md` — Root project navigation
- `../../../../../../.opencode/context/project-intelligence/technical-domain.md` — Docker, Caddy, Cloudflare, CI/CD

## Integration

This folder is referenced from:
- `/home/maulana/.config/opencode/context/core/standards/project-intelligence.md` (standards and patterns)
- `/home/maulana/.config/opencode/context/core/system/context-guide.md` (context loading)

See `/home/maulana/.config/opencode/context/core/context-system.md` for the broader context architecture.

## Maintenance

Keep this folder current:
- Update when the stack or architecture changes
- Add focused concept files instead of growing `technical-domain.md` past 200 lines
- Bump `Version` + `Updated` in the frontmatter on every content change

**Management Guide**: See `/home/maulana/.config/opencode/context/core/standards/project-intelligence-management.md` for complete lifecycle management including:
- How to update, add, and remove files
- How to create new subfolders
- Version tracking and frontmatter standards
- Quality checklists and anti-patterns
- Governance and ownership

See `/home/maulana/.config/opencode/context/core/standards/project-intelligence.md` for the standard itself.
