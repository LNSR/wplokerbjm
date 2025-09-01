import { chromium } from 'playwright';
import type { Browser, BrowserContext, Page } from 'playwright';
import { minify } from 'html-minifier-terser';
import { FileManager } from './file-utils.js';
import { createAdBlockManager, type AdBlockConfig } from './adblock-utils.js';

export interface BrowserConfig {
  userAgent?: string;
  ignoreHTTPSErrors?: boolean;
  dohServer?: string;
}

export interface PageGenerationOptions {
  waitUntil?: 'load' | 'domcontentloaded' | 'networkidle';
  timeout?: number;
  minifyHtml?: boolean;
  adBlock?: AdBlockConfig;
}

export class BrowserManager {
  private browser: Browser | null = null;
  private context: BrowserContext | null = null;

  async launch(config: BrowserConfig = {}): Promise<void> {
    const {
      userAgent = 'Mozilla/5.0 (compatible; SSG-Bot/1.0)',
      ignoreHTTPSErrors = true,
      dohServer = process.env['SSG_DOH_SERVER'] || 'https://dns.adguard.com/dns-query'
    } = config;

    // Prepare launch arguments
    const launchArgs: string[] = [];
    if (dohServer) {
      launchArgs.push(`--dns-over-https-server=${dohServer}`);
      console.log(`🔒 DNS over HTTPS enabled: ${dohServer}`);
    }

    this.browser = await chromium.launch({ args: launchArgs });
    this.context = await this.browser.newContext({
      ignoreHTTPSErrors,
      extraHTTPHeaders: {
        'User-Agent': userAgent
      }
    });
  }

  async createPage(): Promise<Page> {
    if (!this.context) {
      throw new Error('Browser context not initialized. Call launch() first.');
    }
    const page = await this.context.newPage();
    return page;
  }

  async close(): Promise<void> {
    if (this.context) {
      await this.context.close();
      this.context = null;
    }
    if (this.browser) {
      await this.browser.close();
      this.browser = null;
    }
  }
}

export class PageGenerator {
  private browserManager: BrowserManager;

  constructor() {
    this.browserManager = new BrowserManager();
  }

  async initialize(config: BrowserConfig = {}): Promise<void> {
    await this.browserManager.launch(config);
  }

  async generatePage(
    url: string,
    outputPath: string,
    options: PageGenerationOptions = {},
    retryCount = 0
  ): Promise<void> {
    const {
      waitUntil = 'networkidle',
      timeout = 30000,
      minifyHtml = false,
      adBlock
    } = options;

    const maxRetries = parseInt(process.env['SSG_MAX_RETRIES'] || '3');

    const page = await this.browserManager.createPage();

    try {
      // Set page timeout
      page.setDefaultTimeout(timeout);

      // Setup ad blocking if enabled
      let adBlockManager;
      if (adBlock || process.env['SSG_BLOCK_ADS'] !== 'false') {
        adBlockManager = createAdBlockManager();
        if (adBlock) {
          adBlockManager.updateConfig(adBlock);
        }
        await adBlockManager.setupPageInterception(page);
        // Dynamic message based on actual config
        const config = adBlockManager.getConfig();
        const blockingTypes = [];
        if (config.blockAds) blockingTypes.push('ads');
        if (config.blockTracking) blockingTypes.push('tracking');
        if (config.blockAnalytics) blockingTypes.push('analytics');
        console.log(`🛡️ AdBlock enabled - blocking ${blockingTypes.join(', ')}`);
      }

      await page.goto(url, { waitUntil });
      await page.waitForLoadState('domcontentloaded');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000); // Reduced from 5000ms

      const content = await page.content();

      // Show blocking statistics if ad blocking was enabled
      if (adBlockManager) {
        const stats = adBlockManager.getBlockingStats();
        if (stats.totalBlocked > 0) {
          console.log(`🚫 Blocked ${stats.totalBlocked} requests:`);
          Object.entries(stats.byReason).forEach(([reason, count]) => {
            console.log(`   - ${reason}: ${count}`);
          });
        }
      }

      // Minify HTML if enabled
      let finalContent = content;
      if (minifyHtml) {
        console.log('🔧 Starting HTML minification...');
        finalContent = await this.minifyHtml(content);
        const savings = ((content.length - finalContent.length) / content.length * 100).toFixed(1);
        console.log(`✅ Minified: ${content.length} → ${finalContent.length} bytes (${savings}% reduction)`);
      }

      // Use FileManager for safe file writing
      FileManager.writeFile(outputPath, finalContent);

      console.log(`Static page generated: ${outputPath}`);

    } catch (error) {
      console.error(`Error generating page ${url}:`, error);

      // Retry logic
      if (retryCount < maxRetries) {
        console.log(`Retrying ${url} (attempt ${retryCount + 1}/${maxRetries + 1})...`);
        await new Promise(resolve => setTimeout(resolve, 1000 * (retryCount + 1))); // Exponential backoff
        return this.generatePage(url, outputPath, options, retryCount + 1);
      } else {
        throw new Error(`Failed to generate ${url} after ${maxRetries + 1} attempts: ${error}`);
      }
    } finally {
      await page.close();
    }
  }

  private async minifyHtml(content: string): Promise<string> {
    try {
      return await minify(content, {
        collapseWhitespace: true,
        removeComments: true,
        removeRedundantAttributes: true,
        removeScriptTypeAttributes: true,
        removeStyleLinkTypeAttributes: true,
        useShortDoctype: true,
        minifyCSS: true,
        minifyJS: true,
        minifyURLs: true
      });
    } catch (minifyError) {
      console.warn(`Warning: HTML minification failed:`, minifyError);
      return content; // Return original content if minification fails
    }
  }

  async cleanup(): Promise<void> {
    await this.browserManager.close();
  }
}

// Convenience function for single page generation
// Shared generator to reuse a single browser instance across many page generations.
let sharedGenerator: PageGenerator | null = null;
let initPromise: Promise<void> | null = null;

export async function generateStaticPage(
  url: string,
  outputPath: string,
  options: PageGenerationOptions = {}
): Promise<void> {
  // Ensure initialization happens only once, even with concurrent calls
  if (!sharedGenerator && !initPromise) {
    initPromise = (async () => {
      try {
        console.log('Initializing shared browser generator...');
        sharedGenerator = new PageGenerator();
        // Initialize once and keep the browser open for the lifetime of the process.
        await sharedGenerator.initialize();
        console.log('Shared browser generator initialized successfully');

        // Ensure cleanup when the Node process exits.
        const cleanupOnce = async () => {
          try {
            if (sharedGenerator) {
              console.log('Cleaning up shared browser generator...');
              await sharedGenerator.cleanup();
              sharedGenerator = null;
              initPromise = null;
            }
          } catch {
            // swallow errors during process shutdown
          }
        };
        process.once('beforeExit', cleanupOnce);
        process.once('SIGINT', async () => {
          await cleanupOnce();
          process.exit(130);
        });
        process.once('SIGTERM', async () => {
          await cleanupOnce();
          process.exit(0);
        });
      } catch (error) {
        console.error('Failed to initialize shared browser generator:', error);
        initPromise = null;
        throw error;
      }
    })();
  }

  // Wait for initialization to complete if it's in progress
  if (initPromise) {
    await initPromise;
  }

  if (!sharedGenerator) {
    throw new Error('Failed to initialize shared page generator');
  }

  // Delegate generation to the shared generator. It handles retries internally.
  await sharedGenerator.generatePage(url, outputPath, options);
}

// Export cleanup function for explicit cleanup
export async function cleanupSharedBrowser(): Promise<void> {
  if (sharedGenerator) {
    console.log('Cleaning up shared browser generator...');
    await sharedGenerator.cleanup();
    sharedGenerator = null;
    initPromise = null;
  }
}