---
applyTo: "**/*.{js,ts,php,css,html,json}"
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
- For PHP DI structure, refer to the namespace:  
  `AstraChild\Core\Container`

## Frontend

- Use **Bun** for JavaScript/TypeScript package management.
- Frontend tooling is using Vite
- Use Inversify for dependency injection
- Prioritize OOP outside Vue ecosystem
