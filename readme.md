# 💼 WPLokerBJM Source Code & Configuration

<div align="center">
  
![WordPress](https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-35495E?style=for-the-badge&logo=vue.js&logoColor=4FC08D)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=for-the-badge&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white)

</div>

> 🚀 A simple WordPress job portal built with Vue.js frontend and PHP backend.

This repository contains the source code and configuration for **WPLokerBJM**, which uses Astra as a child theme with extensive customizations.

## 📁 Project Structure

- 🎨 **`wordpress/wp-content/themes/astra-child`**  
  The main directory for theme customizations. Extends the Astra parent theme with custom features, styles, and templates for the WPLokerBJM.

- 🔌 **`wordpress/wp-content/mu-plugins/astra-child-bootstrap.php`**  
  Must-use plugin that loads the Composer autoloader and initializes the PHP-DI container early in the WordPress lifecycle, ensuring hooks and services are registered before regular plugins and themes.

- ⚙️ **`wordpress/wp-content/themes/astra-child/inc`**  
  Contains backend PHP code, including custom functions, REST APIs, hooks, and filters. This may include custom post types, meta fields, and integration logic.

- 🖼️ **`wordpress/wp-content/themes/astra-child/src`**  
  Contains Vue components and all client-side code that enhances the user interface.

---

## 🛠️ Setup Configuration

```bash
├── Caddyfile                          # Reverse Proxy. See https://caddyserver.com/docs/
├── compose.yaml
├── docker.conf.d                      # Images Docker configurations
├── Dockerfile
├── localhost-key.pem                  # Generated via mkcert, used for HTTPS local dev
├── localhost.pem                      # Same(but for public cert)
├── readme.md
├── wordpress                          # Wordpress installation
├── wpcli.sh                           # Provide alias for "wpcli"(change to your own container)
└── wplokerbjm.code-workspace
```

## 🔌 WordPress Core Plugins

| Plugin                 | Description                              | Status      |
| ---------------------- | ---------------------------------------- | ----------- |
| 🧩 **MetaBox**         | Dynamic data framework for custom fields | ✅ Required |
| 🔍 **Rank Math SEO**   | Custom Job Posting schema integration    | 🔧 Optional |
| 💾 **UpdraftPlus**     | Backup and restore functionality         | 🔧 Optional |
| ⚡ **LiteSpeed Cache** | High-performance caching solution        | 🔧 Optional |

> 💡 **Note**: See `inc/Models/Schema` for MetaBox implementation details

### 🏗️ Backend Structure

The backend code is organized as follows:

```
inc/
├── Components/                # PHP UI components (migrated to CSR frontend, provide only initial data)
├── Contracts/                 # Interfaces for data providers and hooks
│   ├── DataProviderInterface.php
│   └── HooksInterface.php
├── Controllers/               # Controllers
│   └── REST/                  # REST API controllers
├── Core/                      # Core framework and dependency injection
│   ├── AutowireScanner.php    # Scans for PHP files for autowiring
│   ├── Cache.php              # Centralized cache management for transients
│   ├── ObjectCache.php        # Direct object cache management
│   ├── Container.php          # Dependency Injection container (PHP-DI)
│   ├── Definitions/           # Container definitions
│   ├── Enqueue/               # Enqueue management
│   │   └── Vite.php           # Vite integration for asset management
│   ├── Enqueue.php            # Registers/enqueues scripts and styles
│   ├── Hooks/                 # Sub-Hooks
│   ├── Hooks.php              # Registers custom WP actions and filters
│   ├── Init.php               # Initializes services and hooks
├── Factories/                 # Factory classes
├── Layouts/                   # Reusable page/section layouts
│   └── Layouts.php            # (migrated to CSR frontend, provide only initial data)
├── Models/                    # Data models and schema definitions
│   ├── CustomFieldEntity.php
│   ├── Schema/                # MetaBox fields, post types, taxonomies (reference only)
│   │   ├── CustomFields.php
│   │   ├── PostTypes.php
│   │   └── Taxonomies.php
│   └── TaxonomyEntity.php
├── QueryBuilders/             # Query builder classes
├── Repositories/              # Data repositories
├── Services/                  # Business logic/services
│   ├── CustomField/
│   ├── Job/
│   ├── PostsManagement/
│   │   ├── PostsManagement.php
│   │   └── SSG/                        # SSG: Post management for static generation
│   │       ├── PostsCRUDListener.php   # Listens to post CRUD for SSG triggers
│   │       ├── RedirectToSSG.php       # Handles redirects for SSG pages
│   │       └── TriggerBuild.php        # Triggers SSG builds on post changes
│   ├── REST/
│   ├── Taxonomy/
│   ├── Utilities/
├── ViewModels/                # Page view models (migrated to CSR frontend, provide only initial data)
└── Views/                     # PHP view templates (migrated to CSR frontend, provide only initial data)
```

---

## 🎨 Frontend Structure

```
src/
├── api                        # REST API logic
│   ├── endpoints              # API endpoint logic
│   │   ├── Jobs.ts            # Jobs-related API calls
│   │   └── Taxonomy.ts        # Taxonomy-related API calls
│   ├── Client.ts              # API client setup/utilities
│   ├── Error.ts               # API error handling
│   └── index.ts               # API module entry
├── app                        # App bootstrap, mounting, routing
│   ├── entry/                 # entry components setup
│   ├── Factory.ts             # Vue app creation factory
│   ├── Mounter.ts             # Vue app mounting logic
│   └── Router.ts              # Vue app routing logic
├── components                 # Vue UI components
│   ├── Homepage               # Homepage-specific components
│   ├── Shared                 # Shared inter-components
├── composables                # Vue composables (reusable logic)
├── layouts                    # App layout components
├── pages                      # Page-level Vue components
├── services                   # Service classes (API, Auth, etc.)
├── stores                     # Pinia/Vuex state management
├── types                      # TypeScript type definitions
├── utils                      # Utility functions
├── global.d.ts                # Global TypeScript declarations
├── main.ts                    # Vue app entry point
├── inversify.config.ts        # Inversify Container
├── shims-vue.d.ts             # Vue shims for TypeScript
└── vite-env.d.ts              # Vite env type declarations
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

- 🚀 **Start the containers**:
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

- 📋 [**Project Notes & Architecture**](wordpress/wp-content/themes/astra-child/README.md)
- 🛠️ [**SSG Tools Documentation**](wordpress/wp-content/themes/astra-child/tools/SSG/docs/README.md)

---
