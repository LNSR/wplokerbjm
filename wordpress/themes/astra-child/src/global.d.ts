declare global {
  interface Window {
    gtag?: (...args: any[]) => void;
    ga?: (...args: any[]) => void;
    _gaq?: { push?: (...args: any[]) => void };
    dataLayer?: Array<Record<string, any>> & { push?: (...args: any[]) => void };
    __siteKitPageviewFired?: boolean;
    wpUserLoggedIn?: boolean;
  }
}
export {};