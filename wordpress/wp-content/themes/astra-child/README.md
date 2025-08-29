# 🎨 Astra Child Theme - WPLokerBJM

> 🚀 Modern WordPress job portal theme built with Vue.js, TypeScript, and PHP-DI

![WordPress](https://img.shields.io/badge/WordPress-21759B?style=flat-square&logo=wordpress&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-4FC08D?style=flat-square&logo=vue.js&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=flat-square&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=flat-square&logo=vite&logoColor=white)

## 📁 Important Theme Files & Folders

1. 🔧 **`functions.php`** - Main theme functions file. Initializes custom features and hooks.
2. 🎨 **`style.css`** - Boilerplate WordPress stylesheet for the child theme. Contains theme metadata and custom styles.
3. 📸 **`screenshot.png`** - Screenshot image for the theme, displayed in the WordPress admin area.
4. ⚙️ **`inc/`** - Directory containing backend PHP code, including custom functions, REST APIs, hooks, and filters.
5. 🖼️ **`src/`** - Directory containing Vue components and all client-side code that enhances the user interface.
6. 📦 **`assets/`** - Directory for static assets like images, fonts, static site generation, and compiled CSS/JS files.
7. 🛠️ **`tools/`** - Directory for development and build tools.

## 📄 Theme Pages

| Page                                    | Template                   | Description                         | Status            |
| --------------------------------------- | -------------------------- | ----------------------------------- | ----------------- |
| 🏠 [Homepage](page-homepage.php)        | `page-homepage.php`        | Main landing page with job listings | ✅ Active         |
| 💼 [Job Detail](single-lowongan.php)    | `single-lowongan.php`      | Individual job posting page         | ✅ Active         |
| 📝 [Post Job](page-pasang-lowongan.php) | `page-pasang-lowongan.php` | Job posting submission form         | ✅ Active         |
| 📋 [Job Archive](archive-lowongan.php)  | `archive-lowongan.php`     | Job listings archive (Legacy)       | ⚠️ Pre-Vue Island |

## 🔧 Automation Tools

- 🏗️ **[SSG](tools/SSG/docs/README.md)** — Static Site Generation via GitHub Actions pipeline
  - ⚡ Automated builds on content changes
  - 🚀 Performance optimization
  - 📊 SEO improvements

## 📝 Development Notes

> 💡 **Architecture Tips**
>
> - 🔗 See [Init.php](inc/Core/Init.php) and [PHP-DI Container](inc/Core/Container.php) for WordPress event-driven hooks implementation
> - 🎨 [Assets](assets) are shared between backend and frontend - Tailwind scans both PHP and Vue source files
> - ⚡ All frontend rendering happens in `<body>` (CSR) while `<head>` contains server-side data

## 📋 Mini Kanban Table

| 📥 BACKLOG                                 | 📋 TODO | 🚧 IN PROGRESS                      | ✅ COMPLETED                        |
| ------------------------------------------ | ------- | ----------------------------------- | ----------------------------------- |
|                                            |         | 🔄 Implement SSG via GitHub Actions | ✅ Migrate to Vue for most frontend |
| 🚀 Migrate to Nuxt and deploy to Vercel    |         |                                     | ✅ Fully CSR `<body>`               |
| 🗺️ Add Job Fair Page (map & event details) |         |                                     |                                     |
