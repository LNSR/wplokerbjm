import { type PartytownConfig } from "@qwik.dev/partytown/integration";
import type { DevicePayload } from "sveltekit-device-detector/dist/types";
import type {
  Env,
  ExecutionContext,
  CacheStorage,
  Cache,
  IncomingRequestCfProperties,
  KVNamespace,
} from "@cloudflare/workers-types";
import type { WPLokerBJMThemedData } from "@/types";

// See https://svelte.dev/docs/kit/types#app.d.ts
// for information about these interfaces
declare global
{
  interface CacheStorage {
    default: Cache;
  }

  interface DeviceType
  {
    isMobile: boolean;
    isBrowser: boolean;
    isAndroid: boolean;
    isIOS: boolean;
    isSmartTV: boolean;
    isConsole: boolean;
    isWearable: boolean;
    isEmbedded: boolean;
    isMobileSafari: boolean;
    isChromium: boolean;
    isTablet: boolean;
    isDesktop: boolean;
    isWinPhone: boolean;
    isChrome: boolean;
    isFirefox: boolean;
    isSafari: boolean;
    isOpera: boolean;
    isIE: boolean;
    osVersion: string;
    fullBrowserVersion: string;
    browserVersion: string;
    mobileVendor: string;
    mobileModel: string;
    getUA: string;
    isEdge: boolean;
    isYandex: boolean;
    isIOS13: boolean;
    isIPad13: boolean;
    isIPhone13: boolean;
    isIPod13: boolean;
    isElectron: boolean;
    isEdgeChromium: boolean;
    isLegacyEdge: boolean;
    isWindows: boolean;
    isMacOs: boolean;
    isMIUI: boolean;
    isSamsungBrowser: boolean;
    isWebView: boolean;
    isCrawler: boolean;
  }

  interface DevicePayload extends DeviceType
  {
    browserMajorVersion?: string;
    browserFullVersion?: string;
    browserName?: string;
    engineName?: string;
    engineVersion?: string;
    osName?: string;
    osVersion: string;
    userAgent?: string;
    vendor?: string;
    model?: string;
    os?: string;
    ua?: string;
  }

  namespace App
  {
    // interface Error {}
    // interface Locals {}
    // interface PageData {}
    // interface PageState {}
    interface Locals
    {
      deviceType: DevicePayload;
      authToken?: string;
      earlyHintsLink?: string;
      /** Theme data fetched from the CMS and stored on locals for downstream usage. */
      themeData: WPLokerBJMThemedData;
      /** Real visitor IP address resolved from CF-Connecting-IP or X-Forwarded-For headers. */
      clientIp?: string;
      /** wordpress its post last modified time */
      postTime?: string;
    }

    interface PageData
    {
      deviceType: DevicePayload,
      jobSchemaScript?: string // This is a string containing a <script type="application/ld+json"> 
    }

    interface Platform
    {
      env: Env; 
      ctx: ExecutionContext;
      caches: CacheStorage;
      cf?: IncomingRequestCfProperties;
    }

    interface PrivateEnv { }
    interface PublicEnv { }
  }

  type DataLayerItem = Record<string, unknown> | unknown[];

  interface Window
  {
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
  
  declare module '*?inline-script' {
    const src: string
    export default src
  }
}

export { };