declare global {
  interface Window {
    gtag?: (...args: unknown[]) => void;
    ga?: (...args: unknown[]) => void;
    _gaq?: { push?: (...args: unknown[]) => void };
    dataLayer?: Array<Record<string, unknown>> & { push?: (...args: unknown[]) => void };
    __siteKitPageviewFired?: boolean;
    wpUserLoggedIn?: boolean;
  }
}
export {};