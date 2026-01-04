import { type PartytownConfig } from "@qwik.dev/partytown/integration";

type DataLayerItem = Record<string, unknown> | unknown[];

declare global {
  interface Window {
    adsbygoogle?: unknown[];
    // Google Analytics / GTM helpers used by the frontend
    // `gtag` may be injected by a GA4 snippet. Keep it optional as it may not
    // be present in some environments (dev, tests, or when GTM manages analytics).
    gtag?: (...args: unknown[]) => void;
    // `dataLayer` is the standard global pushed-to array used by Google Tag
    // Manager. We type it as an array of objects with event and arbitrary properties.
    dataLayer?: DataLayerItem[];

    // Partytown configuration object used by the Partytown loader. The
    // `forward` array lists function calls that should be proxied to the
    // main thread (e.g., 'dataLayer.push').
    partytown?: PartytownConfig;
  }
}

export { };
