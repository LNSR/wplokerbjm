import { nonceStore } from '$lib/stores/Nonce.svelte';
import { WPThemeDataStore } from '$lib/stores/WPThemeData';

/**
 * Focused on Google-related functionality like sending page views and managing tracking availability.
 */
export class GoogleServices {
  private static gtmLoaded = false;
  private static adSenseLoaded = false;

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
   * Injects the Google Tag Manager script if tracking is enabled and not already loaded.
   * @returns Promise that resolves when GTM is loaded or immediately if disabled/already loaded.
   */
  public static async injectGTMScript(): Promise<void> {
    if (!this.isTrackingEnabled() || this.gtmLoaded || typeof document === 'undefined') return;

    // Avoid duplicate injection
    if (document.querySelector('script[src*="googletagmanager.com"]')) {
      this.gtmLoaded = true;
      return;
    }

    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.async = true;
      script.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-PHZNSBWX&l=dataLayer';

      script.onload = () => {
        this.gtmLoaded = true;
        resolve();
      };

      script.onerror = () => {
        console.warn('Failed to load GTM script');
        reject(new Error('GTM script load failed'));
      };

      // Initialize dataLayer if not present
      if (!window.dataLayer) {
        window.dataLayer = [];
      }
      window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

      document.head.appendChild(script);
    });
  }

  /**
   * Injects the AdSense script if tracking is enabled and not already loaded.
   * @returns Promise that resolves when AdSense script is loaded or immediately if disabled/already loaded.
   */
  public static async injectAdSenseScript(): Promise<void> {
    if (!this.isTrackingEnabled() || this.adSenseLoaded || typeof document === 'undefined') return;

    // Avoid duplicate injection
    if (document.querySelector('script[src*="pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"]')) {
      this.adSenseLoaded = true;
      return;
    }

    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.async = true;
      script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3206452872913415';
      script.crossOrigin = 'anonymous';

      script.onload = () => {
        this.adSenseLoaded = true;
        resolve();
      };

      script.onerror = () => {
        console.warn('Failed to load AdSense script');
        reject(new Error('AdSense script load failed'));
      };

      document.head.appendChild(script);
    });
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
        this.purgeGlobalAds();
      }
    } catch (e) {
      console.warn('Failed to dispatch adsense:destroy or purge global ads', e);
    }
  }

  /**
   * Purges all global AdSense ad elements from the document.
   * Useful for cleaning up external ads injected outside component containers during SPA navigation.
   */
  public static purgeGlobalAds(): void {
    try {
      if (typeof document === 'undefined') return;
      // Remove all ins.adsbygoogle elements (including their iframes) from the document
      const adElements = document.querySelectorAll('ins.adsbygoogle');
      adElements.forEach((el) => {
        if (el.parentNode) {
          el.parentNode.removeChild(el);
        }
      });
      // Remove Google iframes (AdSense and reCAPTCHA related) for cleaner DOM
      const googleIframes = document.querySelectorAll('iframe[src*="googleads.g.doubleclick.net"], iframe[src*="google.com/recaptcha"]');
      googleIframes.forEach((iframe) => {
        if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
      });
      console.log(`Purged ${adElements.length} AdSense elements and ${googleIframes.length} Google iframes`);
    } catch (e) {
      console.warn('Failed to purge global elements', e);
    }
  }

}
