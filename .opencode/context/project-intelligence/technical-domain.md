<!-- Context: project-intelligence/technical | Priority: critical | Version: 1.0 | Updated: 2026-06-21 -->

# Technical Domain — Lowker Site (Root)

> Monorepo infrastructure: Docker, Caddy, Cloudflare Tunnels, CI/CD, and microservice deployment.

## Quick Reference

- **Purpose**: Understand infrastructure architecture, shared services, deployment pipelines
- **Update When**: New services, infrastructure changes, deployment workflow changes
- **Audience**: DevOps engineers, developers working across components

## Primary Stack

| Layer           | Technology                   | Version | Rationale                                              |
| --------------- | ---------------------------- | ------- | ------------------------------------------------------ |
| Container       | Docker Compose               | latest  | Multi-service orchestration (wordpress + caddy + cloudflared) |
| Reverse Proxy   | Caddy                        | latest  | Auto-SSL (mkcert), reverse proxy to WordPress          |
| Tunnel          | Cloudflare Tunnels (cloudflared) | latest  | Expose local services securely without port forwarding |
| CI/CD           | GitHub Actions               | —       | Automated builds, deployments, testing                 |
| Microservice    | Python (Telegram Bot)        | 3.x     | Deployed on Render; git submodule in `wplokerbjm-post-automation/` |
| Config          | `.env` (shared)              | —       | Environment variables shared across all components     |
| SSL (Dev)       | mkcert                       | latest  | Local HTTPS certificates for development               |

## Architecture Pattern

```
Type: Monorepo — WordPress backend + SvelteKit frontend + Python microservice
Pattern: Docker Compose orchestrates all local services; Caddy reverse-proxies with SSL; Cloudflared exposes via tunnel
```

```
                        ┌──────────────┐
                        │   Internet   │
                        └──────┬───────┘
                               │
                        ┌──────▼───────┐
                        │  Cloudflare  │
                        │   Tunnel     │
                        └──────┬───────┘
                               │
                    ┌──────────▼──────────┐
                    │    cloudflared      │
                    │   (container)       │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │       Caddy         │
                    │   (reverse proxy)   │
                    │   SSL via mkcert    │
                    └─────┬─────────┬─────┘
                          │         │
               ┌──────────▼──┐ ┌───▼──────────────┐
               │  WordPress  │ │  SvelteKit (dev) │
               │  (container)│ │  (local dev)     │
               └──────┬──────┘ └──────────────────┘
                      │
               ┌──────▼──────┐
               │   MySQL DB  │
               │  (container)│
               └─────────────┘
```

**Why this architecture?** Docker Compose provides reproducible local development. Caddy handles SSL termination with mkcert certs (trusted by browsers). Cloudflare Tunnels eliminate the need for port forwarding, DNS configuration, or static IPs — services are securely exposed via Cloudflare's edge network. The Python bot runs independently on Render for production-grade reliability.

## Project Structure (Root)

```
Lowker-site/
├── .env                          # Shared environment variables
├── docker-compose.yml            # WordPress + Caddy + Cloudflared
├── Caddyfile                     # Reverse proxy config (Caddy)
├── sveltekit/                    # SvelteKit frontend (headless)
│   └── src/
├── wordpress/                    # WordPress backend
│   └── wp-content/themes/wplokerbjm/
├── wplokerbjm-post-automation/   # Python Telegram bot (git submodule)
│   ├── main.py
│   └── requirements.txt
├── .github/workflows/            # GitHub Actions CI/CD
└── scripts/                      # Utility scripts
```

## Docker Compose Configuration

**Concept**: Three containers orchestrated via `docker-compose.yml` — WordPress (PHP + MySQL), Caddy (reverse proxy), cloudflared (tunnel).

**Key services**:

| Service       | Image                     | Purpose                          | Ports          |
| ------------- | ------------------------- | -------------------------------- | -------------- |
| `wordpress`   | official WP image         | WordPress CMS + MySQL            | 8080 (internal)|
| `caddy`       | caddy:latest              | Reverse proxy + auto-SSL (mkcert)| 80, 443        |
| `cloudflared` | cloudflare/cloudflared    | Cloudflare Tunnel client         | —              |

**Key points**:
- WordPress connects to MySQL via internal Docker network
- Caddy proxies `wplokerbjm.localhost` → WordPress container
- Cloudflared configured with Cloudflare Tunnel token (from `.env`)
- mkcert generates locally-trusted SSL certificates for `*.localhost`
- Shared volumes for WordPress content persistence (uploads, plugins, themes)

## Environment Variables (.env)

**Concept**: A single `.env` file at the project root feeds all components — Docker Compose, SvelteKit, and the Python bot.

**Key variables** (shape only — values never shown):

| Variable               | Used By         | Purpose                              |
| ---------------------- | --------------- | ------------------------------------ |
| `CLOUDFLARE_TUNNEL_TOKEN` | Docker/cloudflared | Cloudflare Tunnel authentication   |
| `WORDPRESS_DB_*`       | Docker/WP       | MySQL connection details             |
| `WP_GRAPHQL_ENDPOINT`  | SvelteKit       | WordPress GraphQL endpoint URL       |
| `TELEGRAM_BOT_TOKEN`   | Python bot      | Telegram Bot API token               |
| `TELEGRAM_CHAT_ID`     | Python bot      | Target Telegram chat ID              |
| `RENDER_API_KEY`       | GitHub Actions  | Deploy to Render                     |

**Usage**: Docker Compose reads `.env` natively. SvelteKit and Python bot read via their respective config systems. **Never commit `.env`** — `.env.example` provided for reference.

## GitHub Actions CI/CD

**Concept**: Automated workflows for building, testing, and deploying all components.

**Workflows** (in `.github/workflows/`):

| Workflow               | Trigger            | Purpose                              |
| ---------------------- | ------------------ | ------------------------------------ |
| `deploy-python-bot.yml` | Push to main       | Deploy Python bot to Render          |
| `build-sveltekit.yml`  | Push to main / PR  | Build & lint SvelteKit frontend      |
| `test-wordpress.yml`   | Push to main / PR  | Run PHPUnit tests for WP theme       |

**Key patterns**:
- Render deployment: uses `render.com` API with `RENDER_API_KEY` secret
- SvelteKit build: `bun install` + `bun run build` + Cloudflare Workers deploy
- WordPress tests: PHPUnit via Docker container, MySQL service for integration tests

## Python Telegram Bot (Render Microservice)

**Concept**: A Python-based Telegram bot that automates job post management. Runs as an independent microservice on Render for production reliability.

**Location**: `wplokerbjm-post-automation/` (git submodule)

**Key points**:
- Python 3.x with `python-telegram-bot` library
- Communicates with WordPress via REST API (authenticated)
- Handles Telegram commands for CRUD operations on job posts
- Deployed to Render via GitHub Actions (`deploy-python-bot.yml`)
- Render provides auto-scaling, HTTPS, and health checks
- Submodule pattern: bot lives in separate repo, linked into monorepo

## Related Files

- `./sveltekit/.opencode/context/project-intelligence/technical-domain.md` — Frontend architecture
- `./wordpress/wp-content/themes/wplokerbjm/.opencode/context/project-intelligence/technical-domain.md` — Backend architecture
