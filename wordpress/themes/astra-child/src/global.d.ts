declare global {
  interface Window {
    gtag?: (...args: any[]) => void;
    dataLayer?: Array<Record<string, any>> & { push?: (...args: any[]) => void };
  }
}
export {};