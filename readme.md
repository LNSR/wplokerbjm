# WPLokerBJM Source Code & Configuration

This repository contains the source code and configuration for WPLokerBJM, which uses Astra as a child theme.

## Project Structure

- `wordpress/themes/astra-child`:  
  The main directory for theme customizations. Extends the Astra parent theme with custom features, styles, and templates for the Lowker-site project.

- `wordpress/themes/astra-child/inc`:  
  Contains backend PHP code, including custom functions, REST APIs, hooks, and filters. This may include custom post types, meta fields, and integration logic.

- `wordpress/themes/astra-child/src`:  
  Contains Vue components and all client-side code that enhances the user interface.

---

## Backend Structure

### Core Plugins

1. **MetaBox**  
   See `wordpress/themes/astra-child/inc/Models/Schema` for the dynamic data framework.

2. **Rank Math SEO**  
   Uses a custom Job Posting schema. See the `JobService::class` in PHP.

3. **Updraft Plus**  
   Provides backup and restore functionality.

### Backend Architecture

The backend code is organized as follows:

```
inc/
├── Components/                # PHP UI components
├── Contracts/                 # Interfaces for data providers and hooks
│   └── HooksInterface.php     # Interface for WP Hooks
├── Controllers/               # Controllers
│   └── REST/                  # REST API controllers
├── Core/                      # Core framework and dependency injection
│   ├── AutowireScanner.php    # Scans for PHP files for autowiring
│   ├── Container.php          # Dependency Injection container (PHP-DI)
│   ├── Definitions/           # Container definitions
│   ├── Enqueue.php            # Registers/enqueues scripts and styles
│   ├── Hooks.php              # Registers custom WP actions and filters
│   ├── Init.php               # Initializes services and hooks
├── Factories/                 # Factory classes
├── Layouts/                   # Reusable page/section layouts
│   └── Layouts.php
├── Models/                    # Data models and schema definitions
│   ├── CustomFieldEntity.php
│   ├── Schema/                # MetaBox fields, post types, taxonomies (reference only)
│   │   ├── CustomFields.php
│   │   ├── PostTypes.php
│   │   └── Taxonomies.php
│   └── TaxonomyEntity.php
├── QueryBuilders/             # Query builder classes
│   └── JobQuery.php
├── Repositories/              # Data repositories
├── Services/                  # Business logic/services
├── ViewModels/                # Page view models
└── Views/                     # PHP view templates
```

---

## Frontend Structure

```
src/
├── api                        # REST API logic
│   ├── endpoints              # API endpoint logic
│   │   ├── Jobs.ts
│   │   └── Taxonomy.ts
│   ├── Client.ts              # API client setup/utilities
│   ├── Error.ts               # API error handling
│   └── index.ts               # API module entry
├── app                        # App bootstrap, mounting, routing
│   ├── Factory.ts
│   ├── index.ts
│   ├── Mounter.ts
│   └── Router.ts
├── components                 # Vue UI components
│   ├── Homepage               # Homepage-specific components
├── composables                # Vue composables (reusable logic)
├── container                  # Dependency injection setup
│   └── inversify
│       └── inversify.config.ts
├── layouts                    # App layout components
├── pages                      # Page-level Vue components
├── services                   # Service classes (API, Auth, etc.)
├── stores                     # Pinia/Vuex state management
├── types                      # TypeScript type definitions
├── utils                      # Utility functions
├── global.d.ts                # Global TypeScript declarations
├── main.ts                    # Vue app entry point
├── shims-vue.d.ts             # Vue shims for TypeScript
└── vite-env.d.ts              # Vite env type declarations
```

---

## Setup Instructions

1. **Clone the repository**

2. **Configure environment and Docker Compose**

   - Copy and edit your own `.env` file.
   - Adjust `compose.yaml` as needed.

3. **Generate SSL certificates** (for HTTPS)

   Using **Caddy** or **mkcert**:

   ```sh
   mkcert -install
   mkcert localhost 127.0.0.1 ::1  # Use your own IP or domain if needed
   ```

4. **Start Docker containers**

   - **Set correct permissions (before containers are running)**  
     The container needs UID/GID `1000` for read/write access, instead of the container's `www-data` user (`33`):

     ```sh
     sudo chown -R 1000:1000 ./wordpress
     ```

   - Start the containers:

     ```sh
      docker compose up -d
      docker exec -it -u root <your_wordpress_container> bash
      chown -R wordpress:wordpress /var/www/html/*
     ```