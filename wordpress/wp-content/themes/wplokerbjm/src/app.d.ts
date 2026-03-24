import { type PartytownConfig } from "@qwik.dev/partytown/integration";
import type { DevicePayload } from "sveltekit-device-detector/dist/types";
import type {
  Env,
  ExecutionContext,
  CacheStorage,
  IncomingRequestCfProperties,
  DurableObjectNamespace,
  KVNamespace,
} from "@cloudflare/workers-types";

// See https://svelte.dev/docs/kit/types#app.d.ts
// for information about these interfaces
declare global {
  namespace App {
    // interface Error {}
    // interface Locals {}
    // interface PageData {}
    // interface PageState {}
    interface Locals {
      deviceType: DevicePayload;
      jwtToken: string | null;

      /** Theme data fetched from the CMS and stored on locals for downstream usage. */
      themeData: WPLokerBJMThemedData | null
    }

    interface PageData { deviceType: DevicePayload }

    interface Platform {
      env?: {
        wplokerbjm: KVNamespace;
      };
      env: Env;
      ctx: ExecutionContext;
      caches: CacheStorage;
      cf?: IncomingRequestCfProperties;
    }

    interface PrivateEnv {}
    interface PublicEnv {}
  }

  type DataLayerItem = Record<string, unknown> | unknown[];

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
    partytown?: PartytownConfig
  }
}

export {};
