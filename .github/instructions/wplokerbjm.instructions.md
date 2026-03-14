---
applyTo: "**/*.{js,ts,php,css,html,json,yml,md,scss,less,sass,cjs,mjs,jsx,tsx,sh,svelte}"
---

# Production Instructions for WPLokerBJM

## Plugins Used

1. tinymce-advanced
2. advanced-media-offloader
3. duplicate-wp-page-post
4. health-check
5. fast-indexing-api
6. jwt-authentication-for-wp-rest-api
7. litespeed-cache
8. meta-box
9. meta-box-lite
10. webp-uploads
11. performance-lab
12. seo-by-rank-math
13. updraftplus
14. view-admin-as
15. wp-crontrol
16. wp-graphql
17. wpgraphql-smart-cache
18. wps-hide-login
19. health-check-troubleshooting-mode
20. wplokerbjm-bootstrap <!-- Mandatory for the app to work, do not remove --> 
21. object-cache.php

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
- Production server are on shared hosting with 1GB RAM and 1 CPU core, it's using Litespeed Server and QUIC Cloud.

## Backend(PHP)

- Composer's "classmap" autoloading is used for PHP files in the theme's server directory.
- bootstrap: [wplokerbjm-bootstrap.php](../../wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php)
- Use **Composer** for PHP dependencies, ensuring the latest PHP version is used.
- For PHP DI structure, refer to file:  
  [Container.php](../../wordpress/wp-content/themes/wplokerbjm/server/Core/Container/Container.php)
- Use Cache if it's the best decision.
  - [Cache.php](../../wordpress/wp-content/themes/wplokerbjm/server/Shared/Cache/Cache.php)
  - Use APCu if is appropriate.

## Frontend(Svelte/TypeScript)

- Use **Bun** for JavaScript/TypeScript package management, always using the latest versions of JavaScript/TypeScript frameworks and syntaxes.