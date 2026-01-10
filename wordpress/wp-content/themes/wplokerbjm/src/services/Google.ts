import { nonceStore } from '@/utils';
import { getThemeData, Partytown } from '@/utils';

/**
 * Focused on Google-related functionality like sending page views and managing tracking availability.
 */
export class GoogleServices {
  private static gtmLoaded = false;

  /**
   * Checks if tracking is enabled (client-side and not logged-in).
   * @private
   * @returns boolean True if tracking is enabled, false otherwise.
   */
  private static isTrackingEnabled(): boolean {
    if (typeof window === 'undefined') return false;
    const themeData = getThemeData();
    if (themeData?.disableTracking) return false;
    return !nonceStore.getNonce;
  }

  /**
   * Ensure Partytown is initialized and ready for use after human interaction.
   * Delegates to shared utilities in src/utils/partytown.ts.
   * @private
   */
  private static async waitForPartytown(): Promise<boolean> {
    if (typeof window === 'undefined') return false;

    return await Partytown.ensureBootOnInteraction();
  }

  /**
   * Injects the Google Tag Manager script if tracking is enabled and not already loaded.
   * @returns Promise that resolves when GTM is loaded or immediately if disabled/already loaded.
   */
  public static async injectGTMScript(): Promise<void> {
    if (!this.isTrackingEnabled() || this.gtmLoaded || typeof document === 'undefined') return;

    await this.waitForPartytown();

    // Push the GTM start event before injecting the script
    window.dataLayer!.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

    const script = document.createElement('script');
    script.type = 'text/partytown';
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-PHZNSBWX&l=dataLayer';

    document.head.appendChild(script);

    // Tell Partytown to scan for the new GTM script
    window.dispatchEvent(new CustomEvent("ptupdate"));

    this.gtmLoaded = true;
  }

  /**
   * Sends a page_view event to GTM via dataLayer.
   * @param path Optional custom path; defaults to current pathname.
   * @param eventName Optional event name; defaults to 'page_view'.
   */
  public static sendPageView(path?: string, eventName: string = 'page_view'): void {
    if (!this.isTrackingEnabled()) return;

    try {
      const pagePath = path || window.location.pathname;
      const pageLocation = window.location.href;
      const pageTitle = typeof document !== 'undefined' ? document.title : '';

      if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({
          event: eventName,
          page_path: pagePath,
          page_location: pageLocation,
          page_title: pageTitle,
        });
      }
    } catch (e) {
      console.warn(`Failed to push ${eventName}`, e);
    }
  }
}
