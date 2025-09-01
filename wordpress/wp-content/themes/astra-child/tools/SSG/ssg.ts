/**
 * Static Site Generator - Single Page
 * Generates static HTML page from single URL
 */

import { generateStaticPage, cleanupSharedBrowser } from './utilities/browser-utils.js';
import { loadSSGConfig } from './utilities/env-loader.js';

async function generateStaticPageWithConfig(url: string, outputPath: string): Promise<void> {
  // Load configuration from environment (respects Vite's .env loading)
  const config = loadSSGConfig();

  await generateStaticPage(url, outputPath, {
    minifyHtml: config.minifyHtml,
    timeout: config.pageTimeout
  });
  
  // Force cleanup after each generation to prevent hanging
  console.log('Forcing cleanup after generation...');
  await cleanupSharedBrowser();
}

// CLI functionality
async function main(): Promise<void> {
  const args = process.argv.slice(2);

  if (args.includes('--help') || args.includes('-h')) {
    console.log('Usage: bun tools/SSG/ssg.ts [url] [outputPath]');
    console.log('  url: URL to generate static page from');
    console.log('  outputPath: Output file path (default: ./assets/ssg/index.html)');
    console.log('');
    console.log('Environment Variables (loaded from .env files):');
    console.log('  SSG_MINIFY_HTML      Minify HTML output (default: false)');
    console.log('  SSG_PAGE_TIMEOUT     Page generation timeout in ms (default: 30000)');
    console.log('  SSG_BLOCK_ADS        Block ads during generation (default: true)');
    console.log('  SSG_BLOCK_TRACKING   Block tracking scripts (default: true)');
    console.log('  SSG_BLOCK_ANALYTICS  Block analytics scripts (default: false)');
    console.log('  SSG_LOG_BLOCKED      Log blocked requests (default: true)');
    console.log('');
    console.log('AdBlock Protection:');
    console.log('  🛡️ Automatically blocks AdSense, tracking, and analytics');
    console.log('  🚫 Prevents AdSense policy violations during generation');
    console.log('  📊 Configurable blocking for different service types');
    console.log('Examples:');
    console.log('  bun tools/SSG/ssg.ts https://example.com ./output/page.html');
    console.log('  # With .env file containing SSG_MINIFY_HTML=true');
    console.log('  bun tools/SSG/ssg.ts https://example.com ./page.html');
    return;
  }

  const url = args[0];
  const outputPath = args[1] || './assets/ssg/index.html';

  if (!url) {
    console.error('Error: URL is required');
    console.log('Use --help for usage information');
    process.exit(1);
  }

  console.log(`Generating static page from: ${url}`);
  console.log(`Output path: ${outputPath}`);

  try {
    await generateStaticPageWithConfig(url, outputPath);
    console.log('✅ Static page generation complete!');
    process.exit(0);
  } catch (error) {
    console.error('❌ Failed to generate static page:', error);
    // Ensure cleanup even on error
    await cleanupSharedBrowser();
    process.exit(1);
  }
}

// Run main if this file is executed directly
main().catch(async (err) => {
  console.error('Error:', err);
  // Ensure cleanup on error
  await cleanupSharedBrowser();
  process.exit(1);
});

export { generateStaticPage };