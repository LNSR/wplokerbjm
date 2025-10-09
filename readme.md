# 💼 WPLokerBJM Source Code & Configuration

<div align="center">
  
![WordPress](https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![Svelte](https://img.shields.io/badge/Svelte-4A4A55?style=for-the-badge&logo=svelte&logoColor=FF3E00)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=for-the-badge&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white)

</div>

> 🚀 A simple WordPress job portal built with Svelte frontend and PHP backend.

This repository contains the source code and configuration for **WPLokerBJM**.

## 📁 Project Structure

- 🎨 **`wordpress/wp-content/themes/wplokerbjm`**  
  The main directory for theme customizations.

- 🔌 **`wordpress/wp-content/mu-plugins/wplokerbjm-bootstrap.php`**  
  Must-use plugin that loads the Composer autoloader and initializes the PHP-DI container early in the WordPress lifecycle, ensuring hooks and services are registered before regular plugins and themes.

- ⚙️ **`wordpress/wp-content/themes/wplokerbjm/server`**  
  Contains backend PHP code, including custom functions, REST APIs, hooks, and filters. This may include custom post types, meta fields, and integration logic.

- 🖼️ **`wordpress/wp-content/themes/wplokerbjm/src`**  
  Contains Svelte components and all client-side code that enhances the user interface.

---

## 🛠️ Setup Configuration

```bash
├── bun.lock                           # Bun lockfile for package management
├── caddy.Dockerfile                   # Dockerfile for Caddy web server
├── Caddyfile                          # Reverse Proxy. See https://caddyserver.com/docs/
├── compose.yml                        # Docker Compose configuration
├── docker.conf.d                      # Images Docker configurations
│   ├── opcache.ini                    # PHP OPcache settings
│   ├── php.ini                        # PHP configuration
│   ├── www.conf                       # PHP-FPM worker settings
│   └── xdebug.ini                     # Xdebug configuration for debugging
├── Dockerfile                         # Main application Dockerfile
├── localhost-key.pem                  # Generated via mkcert, used for HTTPS local dev
├── localhost.pem                      # Same(but for public cert)
├── mkcert                             # Mkcert certificates directory
│   ├── rootCA-key.pem                 # Root CA private key
│   └── rootCA.pem                     # Root CA certificate
├── readme.md                          # This documentation file
├── scripts                            # Utility scripts
│   └── entrypoint.sh                  # Docker entrypoint script
├── wordpress                          # WordPress installation
│   └── wp-content                     # WordPress content directory
├── wpcli.sh                           # Provide alias for "wpcli"(change to your own container)
└── wplokerbjm.code-workspace          # VS Code workspace configuration
```

## 🔌 WordPress Core Plugins

| Plugin                 | Description                              | Status      |
| ---------------------- | ---------------------------------------- | ----------- |
| 🧩 **MetaBox**         | Dynamic data framework for custom fields | ✅ Required |
| 🔍 **Rank Math SEO**   | Custom Job Posting schema integration    | 🔧 Optional |
| 💾 **UpdraftPlus**     | Backup and restore functionality         | 🔧 Optional |
| ⚡ **LiteSpeed Cache** | High-performance caching solution        | 🔧 Optional |

> 💡 **Note**: See `server/Models/Schema` for MetaBox implementation details

### 🏗️ Backend Structure

The backend code is organized as follows:

```sh
server/
├── Contracts/                 # Interfaces for data providers and hooks
│   ├── DataProviderInterface.php
│   └── HooksInterface.php
├── Controllers/               # Controllers
│   └── REST/                  # REST API controllers
│       ├── AutoSuggestionSearch.php
│       ├── Carousel.php
│       ├── DispatchSSGBuild.php
│       ├── DynamicSearch.php
│       ├── JobBookmark.php
│       ├── JobGridController.php
│       ├── LoadMore.php
│       ├── SingleOverlay.php
│       └── TaxonomyDepth.php
├── Core/                      # Core framework and dependency injection
│   ├── Cache.php              # Centralized cache management for transients
│   ├── Container/             # Container setup and definitions
│   │   ├── AutowireScanner.php    # Autowire scanner
│   │   ├── Definitions/           # Container definitions
│   │   │   ├── AutoScanned.php    # Auto-scanned definitions
│   │   │   ├── Core.php           # Core definitions
│   │   │   ├── Factories.php      # Factory definitions
│   │   │   └── Repositories.php   # Repository definitions
│   │   └── Init.php               # Container initialization
│   ├── Container.php          # Main DI container
│   ├── Enqueue/               # Enqueue management
│   │   └── Vite.php           # Vite integration for asset management
│   ├── Enqueue.php            # Registers/enqueues scripts and styles
│   ├── Hooks/                 # Sub-Hooks
│   │   ├── Google.php
│   │   ├── Litespeed.php
│   │   ├── Nonce.php
│   │   └── Theme.php
│   ├── Hooks.php              # Registers custom WP actions and filters
│   └── ObjectCache.php        # Direct object cache management
├── Factories/                 # Factory classes
│   └── JobDataFactory.php
├── Models/                    # Data models and schema definitions
│   └── Schema/                # MetaBox fields, post types, taxonomies (reference only)
│       ├── CustomFields.php
│       ├── PostTypes.php
│       └── Taxonomies.php
├── Presenters/                # Page presenters (migrated to CSR frontend, provide only initial data)
│   ├── Components/            # PHP UI components (migrated to CSR frontend, provide only initial data)
│   │   ├── Hero.php
│   │   ├── JobCarousel.php
│   │   └── JobGrid.php
├── QueryBuilders/             # Query builder classes
│   ├── AttachmentQuery.php
│   ├── DBQuery/
│   │   └── CacheQuery.php
│   ├── JobQuery.php
│   └── TaxonomyQuery.php
├── Repositories/              # Data repositories
│   ├── CustomFieldRepository.php
│   ├── JobRepository.php
│   └── TaxonomyRepository.php
├── Services/                  # Business logic/services
│   ├── Cron/
│   │   └── CronService.php
│   ├── CustomField/
│   │   └── CustomFieldsService.php
│   ├── Job/
│   │   ├── ArchiveServices.php
│   │   ├── FormatterServices.php
│   │   └── JobServices.php
│   ├── PostsManagement/
│   │   ├── PostsManagement.php
│   │   └── SSG/                        # SSG: Post management for static generation
│   │       ├── PostsCRUDListener.php   # Listens to post CRUD for SSG triggers
│   │       ├── RedirectToSSG.php       # Handles redirects for SSG pages
│   │       └── TriggerBuild.php        # Triggers SSG builds on post changes
│   ├── REST/
│   │   ├── RESTData.php
│   │   └── RESTRoute.php
│   ├── Taxonomy/
│   │   ├── TaxonomyManagement.php
│   │   └── TaxonomyService.php
│   └── Utilities/
│       ├── SSG/
│       │   ├── BotDetectionHelper/
│       │   │   ├── BotRangeFetcher.php
│       │   │   └── DnsResolver.php
│       │   ├── BotDetection.php
│       │   ├── Integrations/
│       │   │   ├── LiteSpeedIntegration.php
│       │   │   ├── RankMathIntegration.php
│       │   │   └── SSGIntegration.php
│       │   ├── SSGUtilities.php
│       │   └── URLFilterService.php
│       └── Utilities.php
└── Views/                     # PHP view templates (migrated to CSR frontend, provide only initial data)
    └── Page/
        ├── ArchiveView.php
        ├── HomepageView.php
        └── SingleView.php
```

---

## 🎨 Frontend Structure

```sh
src/
├── app/                       # App-level components and logic
│   ├── components/            # Reusable UI components
│   │   ├── layouts/           # Layout components
│   │   │   └── Header.svelte
│   │   └── ui/                # UI component groups
│   │       ├── Header/        # Header-specific components
│   │       │   └── BookmarkModal.svelte
│   │       ├── Homepage/      # Homepage-specific components
│   │       │   ├── CustomDropdown.svelte
│   │       │   ├── JobCard.svelte
│   │       │   ├── JobCarousel.svelte
│   │       │   ├── JobGrid.svelte
│   │       │   ├── SearchForm.svelte
│   │       │   └── SingleOverlay.svelte
│   │       ├── Shared/        # Shared inter-components
│   │       │   ├── BookmarkButton.svelte
│   │       │   ├── FloatingActionButton.svelte
│   │       │   ├── JobDetail.svelte
│   │       │   ├── LoadingSpinner.svelte
│   │       │   └── RefreshSpinner.svelte
│   │       └── Skeletons/     # Loading skeleton components
│   │           ├── SkeletonHomepage.svelte
│   │           ├── SkeletonPasangIklanLoker.svelte
│   │           └── SkeletonSingleLowongan.svelte
│   └── lib/                   # App libraries and utilities
│       ├── localizations/     # Localization files
│       │   └── svelte-lightbox.ts
│       ├── stores/            # Svelte stores for state management
│       │   ├── Bookmark.svelte.ts
│       │   ├── General.svelte.ts
│       │   ├── HeaderStore.svelte.ts
│       │   ├── JobOverlay.svelte.ts
│       │   ├── Nonce.svelte.ts
│       │   ├── route.svelte.ts
│       │   ├── Search.svelte.ts
│       │   └── Taxonomy.svelte.ts
│       └── utils/             # Utility functions
│           ├── elements.svelte.ts
│           └── SEO.svelte.ts
├── app.svelte                 # Main Svelte app component
├── assets/                    # Static assets
│   ├── css/                   # Stylesheets
│   │   ├── app.css
│   │   ├── svelte-lightbox.css
│   │   └── theme.css
│   └── svelte.svg             # Svelte logo
├── global.d.ts                # Global TypeScript declarations
├── main.ts                    # Svelte app entry point
├── routes/                    # Route components
│   ├── Homepage.svelte
│   ├── PasangIklanLoker.svelte
│   └── SingleLowongan.svelte
├── services/                  # Service classes (API, Auth, etc.)
│   ├── api/                   # API-related services
│   │   ├── Client.ts          # API client setup/utilities
│   │   ├── endpoints/         # API endpoint logic
│   │   │   ├── Jobs.ts        # Jobs-related API calls
│   │   │   ├── RankMath.ts
│   │   │   └── Taxonomy.ts    # Taxonomy-related API calls
│   │   ├── Error.ts           # API error handling
│   │   └── index.ts           # API module entry
│   ├── APIService.ts
│   ├── AuthService.ts
│   ├── Formatting.ts
│   ├── Mounter.ts
│   └── workers/               # Web workers
├── types/                     # TypeScript type definitions
│   ├── API.ts
│   ├── Component.ts
│   ├── index.ts
│   └── Job.ts
└── utils/                     # Utility functions
    ├── debounce.ts
    ├── elements.ts
    ├── indexedDB.ts
    ├── index.ts
    └── validation.ts
```

---

## 🚀 Development Setup Instructions

### Prerequisites

- 🐳 Docker & Docker Compose
- 📦 Node.js 18+ or Bun
- 🔒 mkcert (for SSL certificates)

### Step-by-Step Setup

#### 1. 📥 **Clone the repository**

```bash
git clone <repository-url>
cd wplokerbjm
```

#### 2. ⚙️ **Configure environment and Docker Compose**

- 📝 Copy and edit your own `.env` file
- 🔧 Adjust `compose.yaml` as needed

#### 3. 🔐 **Generate SSL certificates** (for HTTPS)

Using **Caddy** or **mkcert** (your choice)

```sh
# 🛠️ mkcert setup
mkcert -install
mkcert localhost 127.0.0.1 ::1  # Use your own IP or domain if needed
```

#### 4. 🐳 **Spin up Docker containers**

- 🔑 **Set correct permissions** (before containers are running)  
  The container needs UID/GID `1000` for read/write access:

  ```sh
  # 🏠 HOST
  sudo chown -R $USER:$USER ./wordpress
  ```

- 🚀 **Start the containers**

  ```sh
  docker compose up -d
  ```

  - Use [wpcli.sh](wpcli.sh) for WP commands

    ```bash
    source wpcli.sh
    #example:
    wpcli plugin list
    ```

#### 5. ⚡ **Start development server**

```bash
# Option 1: Direct command
bun run dev

# Option 2: VSCode Task
# VSCode → Terminal → Run Task → dev
```

### 🎉 You're all set

Your development environment should now be running at `https://localhost`

---

## 📚 Additional Resources

- 📋 [**Project Notes & Architecture**](wordpress/wp-content/themes/wplokerbjm/README.md)
- 🛠️ [**SSG Tools Documentation**](wordpress/wp-content/themes/wplokerbjm/tools/SSG/docs/README.md)

---
