# ⚡ SSG Quick Start Guide

## 🎯 What You'll Achieve

- ✅ Generate static HTML from your WordPress site
- ✅ Set up automated builds on content changes
- ✅ Optimize performance with minification
- ✅ Deploy static files for better SEO

## 📋 Prerequisites

Before you start, make sure you have:

- 📦 **Node.js 18+** or **Bun** installed
- 🌐 **WordPress site** with sitemap enabled (`https://yoursite.com/sitemap.xml`)
- ⚡ **Theme development environment** set up

## 🚀 Quick Setup

### Step 1: Install Dependencies

```bash
# Navigate to your theme directory
cd wordpress/wp-content/themes/astra-child

# Install dependencies
bun install
# or
npm install

# Install Playwright browsers
bunx playwright install
# or
npx playwright install
```

### Step 2: Configure Environment

Create your SSG environment file.

Important: the SSG utilities load environment variables using Vite's `loadEnv`, which reads standard `.env` files in the theme root (for example: `.env`, `.env.local`, `.env.development`, etc.). To ensure the SSG tools pick up your configuration, copy the theme root `.env` template to `.env`:

```bash
# From the theme root
cp .env.example .env

# Edit the configuration
nano .env
```

```env
# Your WordPress site URL
SSG_BASE_URL=https://yoursite.com

# Performance settings
SSG_CONCURRENCY=5
SSG_MINIFY_HTML=true
SSG_PAGE_TIMEOUT=30000

# Privacy and security settings
SSG_DOH_SERVER=https://dns.adguard.com/dns-query
SSG_BLOCK_ADS=true
SSG_BLOCK_TRACKING=true
SSG_BLOCK_ANALYTICS=false

# Output settings
SSG_OUTPUT_DIR=./assets/ssg
SSG_CONTINUE_ON_ERROR=true
```

### Step 3: Generate Your First Static Site

```bash
# Generate from sitemap (recommended)
bun run ssg:sitemap https://yoursite.com/sitemap.xml

# Or generate a single page
bun run ssg https://yoursite.com ./output/index.html
```

### Step 4: Verify Output

Check your generated files:

```bash
# List generated files
ls -la assets/ssg/

# Open a generated page in browser
open assets/ssg/index.html
```

## 🎉 Success!

Your static site has been generated! You should see:

- 📁 **`assets/ssg/`** - Directory with all static HTML files
- ⚡ **Minified HTML** (if enabled) - Optimized for performance
- � **DNS over HTTPS** (if configured) - Enhanced privacy protection
- 🛡️ **AdBlock Protection** (if enabled) - Clean HTML without ads/trackers
- �🔗 **Preserved URLs** - Matching your WordPress structure

## 🔧 Advanced Usage

### Custom Output Directory

```bash
# Generate to custom directory
bun run ssg:sitemap https://yoursite.com/sitemap.xml ./my-static-site
```

### High Performance Generation

```bash
# Maximum concurrency with minification
SSG_CONCURRENCY=10 SSG_MINIFY_HTML=true bun run ssg:sitemap https://yoursite.com/sitemap.xml
```

## 🆘 Common Issues

### Issue: "Browser not found"

```bash
# Solution: Install Playwright browsers
bunx playwright install
```

### Issue: "Permission denied"

```bash
# Solution: Fix file permissions
sudo chown -R $USER:$USER ./assets/ssg
```

### Issue: "Site unreachable"

```bash
# Solution: Check your site URL and network
curl -I https://yoursite.com/sitemap.xml
```

## 📚 Next Steps

- 🔍 **[Full Documentation](./README.md)** - Complete SSG tools guide
- ⚙️ **[WordPress Integration](./SSG-WP-INTEGRATION.md)** - Set up automatic selective builds
- 🔄 **Manual Full Builds** - Use GitHub Actions workflow dispatch for complete site regeneration

---
