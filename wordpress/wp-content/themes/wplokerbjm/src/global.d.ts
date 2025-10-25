declare global {
  interface Window {
    // Check ThemeHooks.php for properties injected here
    adsbygoogle?: unknown[];
    // Google Analytics / GTM helpers used by the frontend
    // `gtag` may be injected by a GA4 snippet. Keep it optional as it may not
    // be present in some environments (dev, tests, or when GTM manages analytics).
    gtag?: (...args: unknown[]) => void;
    // `dataLayer` is the standard global pushed-to array used by Google Tag
    // Manager. We type it as a loose object array to allow arbitrary event
    // payloads (common for GTM usage).
    dataLayer?: Array<Record<string, any>>;
  }
}

export { };
