---
applyTo: "**/*.{js,ts,php,css,html,json,yml,md,scss,less,sass,cjs,mjs,jsx,tsx,sh,svelte}"
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

- Always use the latest bleeding-edge versions of PHP and JavaScript/TypeScript frameworks and syntaxes.
- Context mostly related to Wordpress **wplokerbjm** development for Job Platform
- Style with **Tailwind CSS** and **DaisyUI**.
- No need to think about backward compatibility, prefer latest.
- If there errors, immediately fix them.
- Prioritize OOP.

## Backend(PHP)

- bootstrap: [wplokerbjm-bootstrap.php](../../wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php)
- Use **Composer** for PHP dependencies, ensuring the latest PHP version is used.
- For PHP DI structure, refer to file:  
  [Container.php](../../wordpress/wp-content/themes/wplokerbjm/server/Core/Container.php)
- Use Cache if it's the best decision.
  Choose between:
  - [ObjectCache.php](../../wordpress/wp-content/themes/wplokerbjm/server/Core/ObjectCache.php) (Redis)
  - [Cache.php](../../wordpress/wp-content/themes/wplokerbjm/server/Core/Cache.php) (transients)

## Frontend(Svelte/TypeScript)

- (Client-Side)[../../wordpress/wp-content/themes/wplokerbjm/src]
- (Server-Side)[../../wordpress/wp-content/themes/wplokerbjm/server/Views]
- Use **Bun** for JavaScript/TypeScript package management, always using the latest versions of JavaScript/TypeScript frameworks and syntaxes.
- Frontend tooling is using Vite.
- Svelte 5 Runes mode.
- Deprecated API are not allowed.
