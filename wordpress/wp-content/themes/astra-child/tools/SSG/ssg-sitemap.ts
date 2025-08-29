/**
 * Static Site Generator - Sitemap-based
 * Generates static HTML pages from sitemap URLs with advanced features
 */

import { generateStaticPage } from './utilities/browser-utils.js';
import { processWithConcurrency } from './utilities/concurrency-utils.js';
import { SitemapParser } from './utilities/xml-utils.js';
import { urlToFilePath } from './utilities/file-utils.js';
import { loadSSGConfig } from './utilities/env-loader.js';

interface SitemapUrl {
  loc: string;
  lastmod?: string;
  changefreq?: string;
  priority?: string;
}

/**
 * Parse sitemap from URL or local file
 */
async function parseSitemap(sitemapPath: string): Promise<SitemapUrl[]> {
  try {
    if (sitemapPath.startsWith('http')) {
      const parser = new SitemapParser();
      return await parser.parseSitemapUrl(sitemapPath);
    }

    // For local files, we'd need to implement file reading
    throw new Error('Local file parsing not implemented in this version');
  } catch (error) {
    console.error(`Error parsing sitemap: ${error}`);
    return [];
  }
}

/**
 * Generate static pages with controlled concurrency
 */
async function generatePagesWithConcurrency(
  urls: SitemapUrl[],
  outputDir: string
): Promise<void> {
  // Load configuration from environment (respects Vite's .env loading)
  const config = loadSSGConfig();

  console.log(`Starting generation of ${urls.length} pages with concurrency ${config.concurrency}...`);
  if (config.continueOnError) {
    console.log('Continuing on errors enabled');
  }
  if (config.minifyHtml) {
    console.log('HTML minification enabled');
  }

  // Progress callback
  const onProgress = (completed: number, total: number, url: string) => {
    const outputPath = urlToFilePath(url, outputDir);
    console.log(`[${completed}/${total}] Generating: ${url} -> ${outputPath}`);
  };

  // Error callback
  const onError = (url: string, error: string) => {
    console.error(`Failed to generate ${url}: ${error}`);
  };

  // Processor function
  const processor = async (url: string) => {
    const outputPath = urlToFilePath(url, outputDir);
    await generateStaticPage(url, outputPath, {
      minifyHtml: config.minifyHtml,
      timeout: config.pageTimeout
    });
  };

  // Process with concurrency control
  const result = await processWithConcurrency(urls.map(u => u.loc), processor, {
    concurrency: config.concurrency,
    continueOnError: config.continueOnError,
    onProgress,
    onError
  });

  // Final summary
  console.log(`\n🎉 Static site generation complete!`);
  console.log(`📁 Generated ${result.successful} pages in ${outputDir}`);
  console.log(`⚡ Used concurrency: ${config.concurrency}`);
  console.log(`🔄 Max retries: ${config.maxRetries}`);
  console.log(`⏰ Page timeout: ${config.pageTimeout}ms`);

  if (config.continueOnError) {
    console.log(`✅ Continued on errors: enabled`);
  }

  if (config.minifyHtml) {
    console.log(`🗜️  HTML minification: enabled`);
  }

  if (result.failed > 0) {
    console.log(`⚠️  ${result.failed} pages failed to generate`);
  }

  // Explicit cleanup of shared browser
  console.log('🧹 Cleaning up browser resources...');
  try {
    // Import the cleanup function from browser-utils
    const { cleanupSharedBrowser } = await import('./utilities/browser-utils.js');
    if (typeof cleanupSharedBrowser === 'function') {
      await cleanupSharedBrowser();
    }
  } catch (error) {
    console.warn('Warning: Could not cleanup browser resources:', error);
  }
}

/**
 * CLI functionality
 */
async function main(): Promise<void> {
  const args = process.argv.slice(2);

  if (args.includes('--help') || args.includes('-h')) {
    console.log('Usage: bun tools/SSG/ssg-sitemap.ts <sitemapPathOrUrl> [outputDir]');
    console.log('  sitemapPathOrUrl: Path to local sitemap XML file or URL to remote sitemap');
    console.log('  outputDir: Output directory for static files (default: ./assets/ssg)');
    console.log('');
    console.log('Environment Variables (loaded from .env files):');
    console.log('  SSG_CONCURRENCY: Number of concurrent page generations (default: 5)');
    console.log('  SSG_MAX_RETRIES: Maximum retry attempts for failed pages (default: 3)');
    console.log('  SSG_PAGE_TIMEOUT: Timeout for individual page generation in ms (default: 30000)');
    console.log('  SSG_CONTINUE_ON_ERROR: Continue processing even if some pages fail (default: false)');
    console.log('  SSG_MINIFY_HTML: Minify HTML output to reduce file size (default: false)');
    console.log('');
    console.log('Examples:');
    console.log('  bun tools/SSG/ssg-sitemap.ts ./sitemap.xml ./output');
    console.log('  bun tools/SSG/ssg-sitemap.ts https://example.com/sitemap.xml');
    console.log('  # With .env file containing SSG_CONCURRENCY=3 SSG_MINIFY_HTML=true');
    console.log('  bun tools/SSG/ssg-sitemap.ts ./sitemap.xml');
    return;
  }

  const sitemapPath = args[0];
  const outputDir = args[1] || './assets/ssg';

  if (!sitemapPath) {
    console.error('Error: Sitemap path or URL is required');
    console.log('Use --help for usage information');
    process.exit(1);
  }

  console.log(`Parsing sitemap: ${sitemapPath}`);
  const urls = await parseSitemap(sitemapPath);

  if (urls.length === 0) {
    console.error('No URLs found in sitemap');
    process.exit(1);
  }

  console.log(`Found ${urls.length} URLs in sitemap`);

  // Generate static pages with controlled concurrency
  await generatePagesWithConcurrency(urls, outputDir);
}

// Run main if this file is executed directly
main().catch((err) => {
  console.error('Error:', err);
  process.exit(1);
});

export { parseSitemap, generateStaticPage, urlToFilePath };