---
applyTo: "**/*.{js,ts,php,css,html,json,yml,md,vue,scss,less,sass,cjs,mjs,jsx,tsx,sh}"
---

# Production Instructions for WPLokerBJM

## Cache

- Cache plugin used: **LiteSpeed Cache** with object caching redis.
- Dynamic data framework: **Metabox Lite**

## SEO

- SEO plugin used: **Rank Math Free**
- Adsense, Analytics, Google Tag Manager integrated via: **Site Kit**

# Development Instructions for WPLokerBJM

## General

- Context mostly related to Wordpress **astra-child** development for Job Platform
- Style with **Tailwind CSS** and **DaisyUI**.

## Backend

- Use **Composer** for PHP dependencies.
- For PHP DI structure, refer to file:  
  [Container.php](../../../wordpress/wp-content/themes/astra-child/inc/Core/Container.php)

## Frontend

- Use **Bun** for JavaScript/TypeScript package management.
- Frontend tooling is using Vite
- Use [inversify.config.ts](../../wordpress/wp-content/themes/astra-child/src/inversify.config.ts) for dependency injection
- Prioritize OOP outside Vue ecosystem

## Agent tooling

- Automation/assistant note: when processing or acting on these repository instructions, always prefer using the `memory`, `sequentialthinking` and other MCP tools when available.
  - `memory`: persist safe workspace and environment facts that help future interactions (repository, branch, key folders, shell/OS). Store secrets if necessary.
  - `sequentialthinking`: use for structured, multi-step analysis, planning, and explanations when modifying code or configs.

These preferences aim to improve context awareness and produce stepwise, auditable changes.
