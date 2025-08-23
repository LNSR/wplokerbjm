# Important theme files
## Pages
1. [page-homepage.php](page-homepage.php) # Homepage
2. [single-lowongan.php](single-lowongan.php) # Job detail page
3. [page-pasang-lowongan.php](page-pasang-lowongan.php) # Pasang iklan loker page
4. [archive-lowongan.php](archive-lowongan.php) # Basically same like Homepage but without "Job Carousel". **Legacy page prior Vue migration**

## **Note**
- See [init.php](inc/Core/Init.php) and [PHP-DI](inc/Core/Container.php) for how to register Wordpress event driven hooks
- [Assets](assets) are shared between backend and frontline because Tailwind need to scan source files from PHP too(especially inline `<head>` style/script)


# Mini Kanban Table

| BACKLOG                                   | TODO | In Progress | DONE                          |
|-------------------------------------------|------|-------------|-------------------------------|
| Implement SSG to fix SEO                  |      |             | [x] Migrate to Vue for most frontend stuff |
| Migrate to Nuxt and deploy to Vercel      |      |             | [x] Fully CSR `<body>`           |
| Add Job Fair Page (map & event details)   |      |             |                               |
