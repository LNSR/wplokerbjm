---
applyTo: "**/*.{js,ts,php,css,html,json,yml,md,scss,less,sass,cjs,mjs,jsx,tsx,sh,svelte}"
---

# Production Instructions for WPLokerBJM

## Cache

- Cache plugin used: **LiteSpeed Cache** with object caching redis.

## Dynamic Data Framework

- Dynamic data framework: **Metabox Lite**

## SEO

- SEO plugin used: **Rank Math Free**

# Development Instructions for WPLokerBJM

## General

- Check [wplokerbjm.code-workspace.json](../../wplokerbjm.code-workspace) for workspace settings.
- Develeper of this project uses:
  - Laptop: FX505DD
    - OS: CachyOS
    - Filesystem: ZFS with all datasets sync=disabled
    - RAM: 32GB
- Always use the latest bleeding-edge versions of PHP and JavaScript/TypeScript frameworks and syntaxes.
- Context mostly related to Wordpress **wplokerbjm** development for Job Platform
- Style with **Tailwind CSS** and **DaisyUI**.
- No need to think about backward compatibility, prefer latest.
- If there errors, immediately fix them.
- Never run Vite dev and Vite build.
- These repositories are using Docker for development and production(simulated configs only, not resources).
- Production server are on shared hosting with 1GB RAM and 1 CPU core, its using Litespeed Server and QUIC Cloud.

## Backend(PHP)

- Composer's "classmap" autoloading is used for PHP files in the theme's server directory.
- bootstrap: [wplokerbjm-bootstrap.php](../../wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php)
- Use **Composer** for PHP dependencies, ensuring the latest PHP version is used.
- For PHP DI structure, refer to file:  
  [Container.php](../../wordpress/wp-content/themes/wplokerbjm/server/Core/Container//Container.php)
- Use Cache if it's the best decision.
  - [Cache.php](../../wordpress/wp-content/themes/wplokerbjm/server/Shared/Cache/Cache.php)
  - Use APCu if is appropriate.

## Frontend(Svelte/TypeScript)

- Use **Bun** for JavaScript/TypeScript package management, always using the latest versions of JavaScript/TypeScript frameworks and syntaxes.
- Frontend tooling is using Vite.
- Strict Svelte 5 Runes mode.
- Tailwind CSS with DaisyUI for styling.