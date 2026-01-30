# 💼 WPLokerBJM Source Code & Configuration

![WordPress](https://img.shields.io/badge/WordPress-21759B?style=flat-square&logo=wordpress&logoColor=white)
![Svelte](https://img.shields.io/badge/Svelte-4A4A55?style=flat-square&logo=svelte&logoColor=FF3E00)
![TypeScript](https://img.shields.io/badge/TypeScript-007ACC?style=flat-square&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=flat-square&logo=vite&logoColor=white)

> 🚀 A simple WordPress job board built with Svelte frontend and WordPress backend.

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
├── .env
├── .env.example
├── .gitignore
├── caddy.Dockerfile                   # Dockerfile for Caddy web server
├── Caddyfile                          # Reverse Proxy. See https://caddyserver.com/docs/
├── compose.yml                        # Docker Compose configuration
├── Dockerfile.development             # Development Dockerfile
├── Dockerfile.production              # Production Dockerfile
├── README.md                          # This documentation file
├── wpcli.sh                           # Provide alias for "wpcli"(change to your own container)
├── wplokerbjm.code-workspace          # VS Code workspace configuration
├── .github/                           # GitHub Actions CI/CD configurations
│   ├── actions/
│   │   ├── setup-dependencies/
│   │   │   └── action.yml
│   │   └── setup-deployment/
│   │       └── action.yml
│   ├── instructions/
│   │   └── wplokerbjm.instructions.md # AI instructions
│   ├── scripts/
│   │   ├── prepare-ssh-key.sh
│   │   ├── ssh-auth-check.sh
│   │   ├── verify-deploy-key.sh
│   │   └── deploy/
│   │       └── rclone-sync.sh
│   └── workflows/
│       ├── clean-workflow.yml
│       └── deploy.yml
├── .vscode/
│   └── mcp.json
├── certs/                             # SSL certificates directory
├── configs/                           # Configuration files for docker compose setups
│   ├── caddy-early-hints.conf
│   ├── cloudflared-config.yml
│   └── wp-config-extra.php
├── docker.conf.d/                     # Images Docker configurations (dev/prod)
│   ├── dev/
│   │   ├── opcache.ini                # PHP OPcache settings (dev)
│   │   ├── php.ini                    # PHP configuration (dev)
│   │   ├── www.conf                   # PHP-FPM worker settings (dev)
│   │   └── xdebug.ini                 # Xdebug configuration for debugging
│   └── prod/
│       ├── opcache.ini                # PHP OPcache settings (prod)
│       ├── php.ini                    # PHP configuration (prod)
│       └── www.conf                   # PHP-FPM worker settings (prod)
├── scripts/                           # Setup scripts
│   └── entrypoint.sh                  # WordPress entrypoint script
├── tools/
└── wordpress/                         # WordPress installation
    └── wp-content/                    # WordPress content directory
```

## 🔌 WordPress Core Plugins

| Plugin                 | Description                              | Status      |
| ---------------------- | ---------------------------------------- | ----------- |
| 🧩 **MetaBox**         | Dynamic data framework for custom fields | ✅ Required |
| 🔗 **WPGraphQL**       | GraphQL API with smart caching for WP    | ✅ Required |
| 🖥️ **Query Monitor**   | Debugging and performance monitoring     | 🔧 Optional |
| 🔍 **Rank Math SEO**   | Custom Job Posting schema integration    | 🔧 Optional |
| 💾 **UpdraftPlus**     | Backup and restore functionality         | 🔧 Optional |
| ⚡ **LiteSpeed Cache** | High-performance caching solution        | 🔧 Optional |

> 💡 **Note**: See `server/Models/Schema` for MetaBox implementation details

### 🏗️ Backend Structure

The backend code is organized as follows:

```sh
server/
├── Configs/                   # Configuration files
│   └── CredentialConfig.php
├── Controllers/               # Controllers
│   ├── GraphQL/
│   │   ├── Resolvers/         # GraphQL resolvers
│   │   │   ├── JobsDataResolver.php
│   │   │   ├── TaxonomyResolver.php
│   │   │   └── ThemeDataResolver.php
│   │   └── Types/             # GraphQL types
│   └── Utilities/             # Utility for controllers
│       └── ControllerUtils.php
├── Core/                      # Core framework and dependency injection
│   ├── Container/             # Container setup and definitions
│   │   ├── Attributes/        # Hook attributes
│   │   │   └── WPHooksAttributes.php
│   │   ├── Container.php      # Main DI container
│   │   ├── Definitions/       # Container definitions
│   │   │   ├── AutoScanned.php
│   │   │   └── Core.php
│   │   ├── Init.php           # Container initialization
│   │   └── Support/           # Container support utilities
│   │       └── AutowireScanner.php
│   ├── Cron/                  # Cron job management
│   │   └── WPCron.php
│   ├── GlobalHooks.php        # Global WordPress hooks
│   ├── Plugins/               # Plugin integrations
│   │   ├── Litespeed.php
│   │   └── Rankmath.php
│   ├── Posts/                 # Post management
│   │   └── PostsManagement.php
│   ├── Taxonomy/              # Taxonomy management
│   │   └── TaxonomyManagement.php
│   └── Theme/                 # Theme-specific functionality
│       ├── Enqueue.php        # Asset enqueuing
│       └── ThemeHooks.php     # Theme hooks
├── Factories/                 # Factory classes
│   └── JobDataFactory.php
├── Models/                    # Data models and schema definitions
│   └── Schema/                # MetaBox fields, post types, taxonomies
│       ├── CustomFields.php
│       ├── PostTypes.php
│       └── Taxonomies.php
├── Presenters/                # Page presenters (provide initial data for CSR)
│   ├── Components/            # PHP UI components
│   │   ├── JobCarousel.php
│   │   └── JobGrid.php
│   ├── DocumentHTML.php       # HTML document presenter
│   ├── Pages/                 # Page-specific presenters
│   │   ├── HomepagePresenter.php
│   │   ├── PasangIklanLokerPresenter.php
│   │   └── SinglePresenter.php
│   └── SEO/                   # SEO-related presenters
│       ├── Schema/            # Schema.org presenters
│       │   └── JobPostingSchema.php
│       └── SkeletonHTML/      # Skeleton HTML for SEO
│           └── SkeletonForSEO.php
├── QueryBuilders/             # Query builder classes
│   ├── JobQuery.php
│   └── TaxonomyQuery.php
├── Repositories/              # Data repositories
│   ├── CustomFieldRepository.php
│   ├── JobRepository.php
│   └── TaxonomyRepository.php
├── Services/                  # Business logic/services
│   ├── GraphQL/               # GraphQL services
│   │   ├── GraphQLData.php
│   │   └── GraphQLRegistration.php
│   └── Schema/                # Schema services
│       └── JobSchemaOrg.php
├── Shared/                    # Shared utilities and services
│   ├── Cache/                 # Caching utilities
│   │   └── Cache.php
│   ├── Log/                   # Logging utilities
│   │   └── Logger.php
│   └── Utilities/             # General utilities
│       └── SharedUtils.php
└── Views/                     # PHP view templates (provide initial data for CSR)
    └── Page/
        ├── HomepageView.php
        ├── PasangIklanLokerView.php
        └── SingleLowonganView.php
```

---

## 🎨 Frontend Structure

```sh
src/
├── app/                       # App-level components and logic
│   ├── components/            # Reusable UI components
│   │   ├── layouts/           # Layout components
│   │   │   ├── Footer.svelte
│   │   │   └── Header.svelte
│   │   └── ui/                # UI component groups
│   │       ├── Header/
│   │       │   └── BookmarkModal.svelte
│   │       ├── Homepage/
│   │       │   ├── CustomDropdown.svelte
│   │       │   ├── JobCard.svelte
│   │       │   ├── JobCarousel.svelte
│   │       │   ├── JobGrid.svelte
│   │       │   ├── SearchForm.svelte
│   │       │   └── SingleOverlay.svelte
│   │       ├── Shared/
│   │       │   ├── BookmarkButton.svelte
│   │       │   ├── FloatingActionButton.svelte
│   │       │   ├── JobDetail.svelte
│   │       │   ├── LoadingSpinner.svelte
│   │       │   └── RefreshSpinner.svelte
│   │       └── Skeletons/
│   │           └── SkeletonSingleLowongan.svelte
│   ├── lib/                   # App libraries and utilities
│   │   ├── stores/            # Svelte stores for state management
│   │   │   ├── Bookmark.svelte.ts
│   │   │   ├── DynamicComponent.svelte.ts
│   │   │   ├── General.svelte.ts
│   │   │   ├── HeaderStore.svelte.ts
│   │   │   ├── JobOverlay.svelte.ts
│   │   │   ├── Route.svelte.ts
│   │   │   ├── Search.svelte.ts
│   │   │   └── Taxonomy.svelte.ts
│   │   └── utils/             # Library utilities
│   │       ├── elements.svelte.ts
│   │       ├── SEO.svelte.ts
│   │       └── Virtualization.svelte.ts
│   └── routes/                # Route components
│       ├── Homepage.svelte
│       ├── PasangIklanLoker.svelte
│       └── SingleLowongan.svelte
├── app.svelte                 # Main Svelte app boot component
├── assets/                    # Static assets
│   └── css/                   # Stylesheets
│       ├── app.css
│       └── theme.css
├── global.d.ts                # Global TypeScript declarations
├── main.ts                    # Svelte app entry point
├── services/                  # Service classes (API, Auth, etc.)
│   ├── APIService.ts
│   ├── Formatting.ts
│   ├── Google.ts
│   ├── Mounter.ts
│   └── api/                   # API-related services
│       └── graphql/
│           └── query/
│               ├── index.ts
│               ├── job.ts
│               ├── Taxonomy.ts
│               └── theme.ts
├── types/                     # TypeScript type definitions
│   ├── API.ts
│   ├── Component.ts
│   ├── index.ts
│   ├── MetaBox.ts
│   ├── SavedState.ts
│   ├── Theme.ts
│   └── Wordpress.ts
└── utils/                     # Agnostic utility functions
    ├── elements.ts
    ├── environment.ts
    ├── index.ts
    ├── indexedDB.ts
    ├── lodash.ts
    ├── Nonce.ts
    ├── partytown.ts
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

---
