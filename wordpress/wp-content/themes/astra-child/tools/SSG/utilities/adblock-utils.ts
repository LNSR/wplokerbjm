/**
 * AdBlock Utilities for SSG
 * Provides utilities to block ads, tracking scripts, and analytics during static site generation
 * This prevents AdSense policy violations and ensures clean static HTML output
 */

import type { Page, Request, Route } from 'playwright';

export interface AdBlockConfig {
  blockAds?: boolean;
  blockTracking?: boolean;
  blockAnalytics?: boolean;
  allowedDomains?: string[];
  customBlockList?: string[];
  logBlocked?: boolean;
}

export interface BlockedRequest {
  url: string;
  type: string;
  reason: string;
  timestamp: number;
}

/**
 * Default configuration for ad blocking
 */
export const DEFAULT_ADBLOCK_CONFIG: AdBlockConfig = {
  blockAds: true,
  blockTracking: true,
  blockAnalytics: false, // Keep analytics for better site understanding
  allowedDomains: [],
  customBlockList: [],
  logBlocked: true
};

/**
 * AdBlock utility class for managing ad and tracking blocking
 */
export class AdBlockManager {
  private config: AdBlockConfig;
  private blockedRequests: BlockedRequest[] = [];
  private readonly adDomains: Set<string>;
  private readonly trackingDomains: Set<string>;
  private readonly analyticsDomains: Set<string>;

  constructor(config: AdBlockConfig = DEFAULT_ADBLOCK_CONFIG) {
    this.config = { ...DEFAULT_ADBLOCK_CONFIG, ...config };

    // AdSense and ad-related domains
    this.adDomains = new Set([
      'googleads.g.doubleclick.net',
      'googlesyndication.com',
      'googletagservices.com',
      'google-analytics.com',
      'googleadservices.com',
      'doubleclick.net',
      'pagead2.googlesyndication.com',
      'tpc.googlesyndication.com',
      'partner.googleadservices.com',
      'www.googleadservices.com',
      'ads.google.com',
      'pubads.g.doubleclick.net',
      'securepubads.g.doubleclick.net',
      'g.doubleclick.net',
      'ad.doubleclick.net',
      'cm.g.doubleclick.net',
      'dcm.g.doubleclick.net',
      'fls.doubleclick.net',
      'www.googletagmanager.com',
      'tagmanager.google.com',
      'marketingplatform.google.com',
      'adservice.google.com',
      'www.google-analytics.com',
      'ssl.google-analytics.com',
      'adsystem.com',
      'amazon-adsystem.com',
      'media.net',
      'criteo.com',
      'outbrain.com',
      'taboola.com',
      'adsafe.org',
      'facebook.com/tr',
      'connect.facebook.net',
      'ads.twitter.com',
      'analytics.twitter.com',
      'ads.linkedin.com',
      'snap.licdn.com',
      'ads.pinterest.com',
      'analytics.pinterest.com'
    ]);

    // Tracking and privacy-related domains
    this.trackingDomains = new Set([
      'facebook.com/tr',
      'connect.facebook.net',
      'hotjar.com',
      'fullstory.com',
      'mouseflow.com',
      'crazyegg.com',
      'clarity.ms',
      'smartlook.com',
      'logrocket.com',
      'bugsnag.com',
      'sentry.io',
      'rollbar.com',
      'trackjs.com'
    ]);

    // Analytics domains (optional blocking)
    this.analyticsDomains = new Set([
      'google-analytics.com',
      'googletagmanager.com',
      'gtag',
      'analytics.google.com',
      'stats.g.doubleclick.net',
      'www.google-analytics.com',
      'ssl.google-analytics.com',
      'www.googletagmanager.com',
      'tagmanager.google.com',
      'marketingplatform.google.com'
    ]);
  }

  /**
   * Setup request interception for a Playwright page
   */
  async setupPageInterception(page: Page): Promise<void> {
    await page.route('**/*', async (route: Route, request: Request) => {
      const url = request.url();
      const resourceType = request.resourceType();

      if (this.shouldBlockRequest(url)) {
        const reason = this.getBlockReason(url);

        if (this.config.logBlocked) {
          this.logBlockedRequest(url, resourceType, reason);
        }

        // Fail the request to prevent loading
        await route.abort('blockedbyclient');
        return;
      }

      // Allow the request to continue
      await route.continue();
    });
  }

  /**
   * Determine if a request should be blocked
   */
  private shouldBlockRequest(url: string): boolean {
    const domain = this.extractDomain(url);

    // Check allowlist first
    if (this.config.allowedDomains?.some(allowed => domain.includes(allowed))) {
      return false;
    }

    // Check custom block list
    if (this.config.customBlockList?.some(blocked => url.includes(blocked))) {
      return true;
    }

    // Block ads
    if (this.config.blockAds && this.isAdRequest(url, domain)) {
      return true;
    }

    // Block tracking
    if (this.config.blockTracking && this.isTrackingRequest(url, domain)) {
      return true;
    }

    // Block analytics (optional)
    if (this.config.blockAnalytics && this.isAnalyticsRequest(url, domain)) {
      return true;
    }

    return false;
  }

  /**
   * Check if request is ad-related
   */
  private isAdRequest(url: string, domain: string): boolean {
    // Check known ad domains
    if (this.adDomains.has(domain)) {
      return true;
    }

    // Check URL patterns
    const adPatterns = [
      /\/ads\//,
      /\/adsense\//,
      /\/doubleclick\//,
      /\/googleads\//,
      /\/pagead\//,
      /\/adnxs\./,
      /\/amazon-adsystem\./,
      /\/googlesyndication\./,
      /\/googletagservices\./,
      /\/googleadservices\./,
      /\/facebook\.com\/tr/,
      /\/connect\.facebook\.net/
    ];

    return adPatterns.some(pattern => pattern.test(url));
  }

  /**
   * Check if request is tracking-related
   */
  private isTrackingRequest(url: string, domain: string): boolean {
    // Check known tracking domains
    if (this.trackingDomains.has(domain)) {
      return true;
    }

    // Check URL patterns for tracking
    const trackingPatterns = [
      /\/track/,
      /\/pixel/,
      /\/beacon/,
      /\/hotjar/,
      /\/fullstory/,
      /\/mouseflow/,
      /\/crazyegg/,
      /\/clarity/,
      /\/smartlook/,
      /\/logrocket/
    ];

    return trackingPatterns.some(pattern => pattern.test(url));
  }

  /**
   * Check if request is analytics-related
   */
  private isAnalyticsRequest(url: string, domain: string): boolean {
    // Check known analytics domains
    if (this.analyticsDomains.has(domain)) {
      return true;
    }

    // Check URL patterns for analytics
    const analyticsPatterns = [
      /google-analytics\.com/,
      /googletagmanager\.com/,
      /gtag/,
      /analytics\.google\.com/,
      /stats\.g\.doubleclick\.net/
    ];

    return analyticsPatterns.some(pattern => pattern.test(url));
  }

  /**
   * Get the reason why a request was blocked
   */
  private getBlockReason(url: string): string {
    const domain = this.extractDomain(url);

    if (this.config.customBlockList?.some(blocked => url.includes(blocked))) {
      return 'custom_blocklist';
    }

    if (this.config.blockAds && this.isAdRequest(url, domain)) {
      return 'ad_blocking';
    }

    if (this.config.blockTracking && this.isTrackingRequest(url, domain)) {
      return 'tracking_blocking';
    }

    if (this.config.blockAnalytics && this.isAnalyticsRequest(url, domain)) {
      return 'analytics_blocking';
    }

    return 'unknown';
  }

  /**
   * Log blocked request
   */
  private logBlockedRequest(url: string, resourceType: string, reason: string): void {
    const blockedRequest: BlockedRequest = {
      url,
      type: resourceType,
      reason,
      timestamp: Date.now()
    };

    this.blockedRequests.push(blockedRequest);

    if (this.config.logBlocked) {
      console.log(`🚫 Blocked ${resourceType}: ${url} (${reason})`);
    }
  }

  /**
   * Extract domain from URL
   */
  private extractDomain(url: string): string {
    try {
      return new URL(url).hostname;
    } catch {
      return url;
    }
  }

  /**
   * Get statistics about blocked requests
   */
  getBlockingStats(): {
    totalBlocked: number;
    byReason: Record<string, number>;
    byType: Record<string, number>;
    blockedRequests: BlockedRequest[];
  } {
    const byReason: Record<string, number> = {};
    const byType: Record<string, number> = {};

    for (const request of this.blockedRequests) {
      byReason[request.reason] = (byReason[request.reason] || 0) + 1;
      byType[request.type] = (byType[request.type] || 0) + 1;
    }

    return {
      totalBlocked: this.blockedRequests.length,
      byReason,
      byType,
      blockedRequests: [...this.blockedRequests]
    };
  }

  /**
   * Clear blocking statistics
   */
  clearStats(): void {
    this.blockedRequests = [];
  }

  /**
   * Update configuration
   */
  updateConfig(newConfig: Partial<AdBlockConfig>): void {
    this.config = { ...this.config, ...newConfig };
  }

  /**
   * Get current configuration
   */
  getConfig(): AdBlockConfig {
    return { ...this.config };
  }
}

/**
 * Convenience function to create AdBlock manager with environment-based config
 */
export function createAdBlockManager(): AdBlockManager {
  const config: AdBlockConfig = {
    blockAds: process.env['SSG_BLOCK_ADS'] !== 'false', // Default: true
    blockTracking: process.env['SSG_BLOCK_TRACKING'] !== 'false', // Default: true
    blockAnalytics: process.env['SSG_BLOCK_ANALYTICS'] === 'true', // Default: false
    allowedDomains: process.env['SSG_ALLOWED_DOMAINS']?.split(',') || [],
    customBlockList: process.env['SSG_CUSTOM_BLOCKLIST']?.split(',') || [],
    logBlocked: process.env['SSG_LOG_BLOCKED'] !== 'false' // Default: true
  };

  return new AdBlockManager(config);
}

/**
 * Helper function to setup basic ad blocking on a page
 */
export async function setupBasicAdBlocking(page: Page, config?: Partial<AdBlockConfig>): Promise<AdBlockManager> {
  const manager = new AdBlockManager(config);
  await manager.setupPageInterception(page);
  return manager;
}

export default AdBlockManager;
