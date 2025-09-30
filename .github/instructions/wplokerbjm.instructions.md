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

- Always use the latest stable versions of PHP and JavaScript/TypeScript frameworks and syntaxes.
- Context mostly related to Wordpress **astra-child** development for Job Platform
- Style with **Tailwind CSS** and **DaisyUI**.
- No need to worry about backward compatibility, just override.
- If there errors, immediately fix them.
- Always ESNext for JavaScript/TypeScript files and PHP 8.4+ conventions.

## Backend(PHP)

- bootstrap: [astra-child-bootstrap.php](../../wordpress/wp-content/mu-plugins/astra-child-bootstrap.php)
- Use **Composer** for PHP dependencies, ensuring the latest PHP version is used.
- For PHP DI structure, refer to file:  
  [Container.php](../../wordpress/wp-content/themes/astra-child/inc/Core/Container.php)
- Use Cache if it's the best decision.
  Choose between:
  - [ObjectCache.php](../../wordpress/wp-content/themes/astra-child/inc/Core/ObjectCache.php) (Redis)
  - [Cache.php](../../wordpress/wp-content/themes/astra-child/inc/Core/Cache.php) (transients)

## Frontend(Vue/TypeScript)

- (Client-Side)[../../wordpress/wp-content/themes/astra-child/src]
- (Server-Side)[../../wordpress/wp-content/themes/astra-child/inc/Views]
- Use **Bun** for JavaScript/TypeScript package management, always using the latest versions of JavaScript/TypeScript frameworks and syntaxes.
- Frontend tooling is using Vite
- Use [inversify.config.ts](../../wordpress/wp-content/themes/astra-child/src/inversify.config.ts) for dependency injection if needed.
- Prioritize OOP in TypeScript files except Vue related.
