import { nonceStore } from '$lib/stores/Nonce.svelte';
import { WPThemeDataStore } from '$lib/stores/WPThemeData';

/**
 * Focused on Google-related functionality like sending page views and managing tracking availability.
 */
export class GoogleServices {
  /**
   * Checks if tracking is enabled (client-side and not logged-in).
   * @private
   * @returns boolean True if tracking is enabled, false otherwise.
   */
  private static isTrackingEnabled(): boolean {
    if (typeof window === 'undefined') return false;
    const themeData = WPThemeDataStore.getThemeData();
    if (themeData?.disableTracking) return false;
    return !nonceStore.getNonce();
  }

  /**
   * Sends a page_view event to GA4 (via gtag) or GTM (via dataLayer).
   * Falls back gracefully if gtag is unavailable.
   * @param path Optional custom path; defaults to current pathname.
   * @param eventName Optional event name; defaults to 'page_view' for GA4 compatibility.
   */
  public static sendPageView(path?: string, eventName: string = 'page_view'): void {
    if (!this.isTrackingEnabled()) return;

    try {
      const pagePath = path || window.location.pathname;
      const pageLocation = window.location.href;
      const pageTitle = typeof document !== 'undefined' ? document.title : '';

      const w = window;
      if (typeof w.gtag === 'function') {
        // GA4 / gtag.js
        w.gtag('event', 'page_view', {
          page_path: pagePath,
          page_location: pageLocation,
          page_title: pageTitle,
        });
      } else if (Array.isArray(w.dataLayer)) {
        // Tag Manager dataLayer fallback
        w.dataLayer.push({
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

  public static adSenseRefresh(): void {
    try {
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('adsense:refresh'));
      }
    } catch (e) {
      console.warn('Failed to dispatch adsense:refresh', e);
    }
  }

  public static adSenseDestroy(): void {
    try {
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('adsense:destroy'));
      }
    } catch (e) {
      console.warn('Failed to dispatch adsense:destroy', e);
    }
  }

}
