import { sequence } from "@sveltejs/kit/hooks";
import type { Handle } from "@sveltejs/kit";
import type { WPLokerBJMThemedData } from "@/types";
import { cookieJwtName } from "$lib/server/constants/constants";
import { handleDeviceDetector } from "sveltekit-device-detector";
import { APIServiceServer } from "@/services/graphql/APIService";
import { dev } from "$app/environment";
import {
  buildPreloadLink,
  isAuthenticated,
} from "$lib/server/utils/http.server";

class HttpUtils {
  public static prependHeader(
    headers: Headers,
    name: string,
    value: string[],
  ): void {
    value.forEach((v) => {
      const existing = headers.get(name);
      headers.set(name, existing ? `${v}, ${existing}` : v);
    });
  }
  public static filterCookieString(raw: string): string {
    return raw
      .split(";")
      .map((p) => p.trim())
      .filter((cook) => {
        const name = cook.split("=")[0] || "";
        const lowerName = name.toLowerCase();
        return (
          lowerName.startsWith("wordpress") ||
          lowerName.startsWith("wp") ||
          lowerName.startsWith(cookieJwtName)
        );
      })
      .join("; ");
  }
  public static setCrossOriginIsolationHeaders(headers: Headers): void {
    headers.set("Cross-Origin-Opener-Policy", "same-origin");
    headers.set("Cross-Origin-Embedder-Policy", "credentialless");
  }
}
// --- Middleware Split ---

/**
 * 1. Fast-Path Bypass Middleware
 */
const handleBypass: Handle = async ({ event, resolve }) => {
  if (event.url.pathname.startsWith("/.well-known/acme-challenge/")) {
    return resolve(event);
  }
  return resolve(event);
};

/**
 * 2. Real IP Resolution Middleware
 *
 * Uses SvelteKit's built-in getClientAddress()
 *
 * NOTE: We only store in event.locals — we cannot .set() headers on the
 * incoming Request (it's immutable in Cloudflare Workers). Downstream code
 * should read from event.locals.clientIp instead.
 */
const handleClientIp: Handle = async ({ event, resolve }) => {
  event.locals.clientIp = event.getClientAddress();
  return resolve(event);
};

/**
 * 3. Auth Context & Fetch Wrapper Middleware
 *
 * Wraps event.fetch to:
 * - Forward the real visitor IP (from handleClientIp) as X-Forwarded-For
 * - Filter cookies to only pass WordPress/wp/JWT cookies upstream
 * - Inject Authorization header from JWT cookie
 */
const handleSecurityContext: Handle = async ({ event, resolve }) => {
  const originalFetch = event.fetch;

  event.fetch = (...args: Parameters<typeof originalFetch>) => {
    const [input, init = {}] = args;
    init.headers = new Headers(init.headers);

    // Forward real visitor IP to upstream services
    if (event.locals.clientIp)
      init.headers.set("X-Forwarded-For", event.locals.clientIp);

    const cookie = event.request.headers.get("Cookie");

    if (cookie) {
      const filtered = HttpUtils.filterCookieString(cookie);
      if (filtered) {
        init.headers.set("Cookie", filtered);
      }

      if (!init.headers.has("Authorization")) {
        const m = filtered.match(
          new RegExp(`(?:^|;\\s*)${cookieJwtName}=([^;]+)`),
        );
        if (m && m[1]) {
          init.headers.set(
            "Authorization",
            `Bearer ${decodeURIComponent(m[1])}`,
          );
        }
      }
    }
    return originalFetch(input, init);
  };

  return resolve(event);
};

/**
 * 4. Layout Discovery Middleware
 */
const handleThemeContext: Handle = async ({ event, resolve }) => {
  try {
    const result: WPLokerBJMThemedData =
      await APIServiceServer.getThemeDataGraphQL(undefined, event.fetch);
    event.locals.themeData = result;
  } catch (e) {
    console.warn("hooks.handleThemeContext: failed to fetch theme data", e);
  }
  return resolve(event);
};

/**
 * 5. Device Identification Middleware
 */
const handleDevice: Handle = async ({ event, resolve }) => {
  const deviceHandler = handleDeviceDetector({});
  let response: Response;

  try {
    response = await deviceHandler({ event, resolve });
  } catch (err) {
    console.error("hooks.handleDevice: error in device handler", err);
    response = await deviceHandler({ event, resolve });
  }

  if (event.locals.deviceType) {
    const existing = response.headers.get("Vary");
    const baseVary = existing
      ? `${existing}, CF-Device-Type`
      : "CF-Device-Type";
    response.headers.set("Vary", `${baseVary}, Cookie, Content-Encoding`);

    try {
      const dt = event.locals.deviceType.isMobile ? "mobile" : "desktop";
      response.headers.set("CF-Device-Type", dt);
    } catch (e) {
      console.warn("hooks.handleDevice: failed to set Device-Type header", e);
    }
  }

  return response;
};

/**
 * 6. Caching & Transformation (Edge Optimization) Middleware
 */
const handleCacheAndTransform: Handle = async ({ event, resolve }) => {
  let response = await resolve(event);

  const path = event.url.pathname;
  const search = event.url.search;
  const contentType = response.headers.get("Content-Type") || "";
  const cookie = event.request.headers.get("Cookie");
  const authenticated = isAuthenticated(cookie);

  const publicCache = "public, max-age=180, s-maxage=2592000, stale-while-revalidate=86400";
  const privateCache = "private, max-age=300, must-revalidate";
  const devModeCache = "no-cache, must-revalidate";
  const cachePolicy = dev
    ? devModeCache
    : authenticated
      ? privateCache
      : publicCache;

  const isHtml = contentType.startsWith("text/html");
  const isJsonOrXml =
    contentType.includes("application/json") ||
    contentType.includes("application/xml");

  if (isHtml || isJsonOrXml) response.headers.set("Cache-Control", cachePolicy);

  if (isHtml) {
    // Inject links for early hints
    try {
      const links = new Set<string>();
      const logoUrl = event.locals.themeData.logo.logoUrl;
      if (logoUrl) {
        const link = buildPreloadLink(logoUrl, "image", { nopush: true });
        links.add(link);
      }
      if (event.locals.earlyHintsLink) links.add(event.locals.earlyHintsLink);
      const validLinks = Array.from(links).filter((l): l is string =>
        Boolean(l),
      );
      if (validLinks.length > 0)
        HttpUtils.prependHeader(response.headers, "Link", validLinks);
    } catch (e) {
      console.warn("hooks.handleCacheAndTransform: preload parsing failed", e);
    }

    response.headers.set("Service-Worker-Allowed", "/");
    HttpUtils.setCrossOriginIsolationHeaders(response.headers);
  }

  if (path.startsWith("/~partytown") || path.includes("/~partytown")) {
    response.headers.set("Service-Worker-Allowed", "/");
    HttpUtils.setCrossOriginIsolationHeaders(response.headers);
  }

  if (path.includes(".worker") || search.includes("worker_file")) {
    response.headers.set("Service-Worker-Allowed", "/");
    HttpUtils.setCrossOriginIsolationHeaders(response.headers);
  }

  response.headers.set(
    "Access-Control-Expose-Headers",
    "ETag, CF-Ray, Last-Modified",
  );
  response.headers.set(
    "Access-Control-Allow-Headers",
    "Authorization, Content-Type, If-None-Match, If-Match, If-Modified-Since, If-Unmodified-Since",
  );

  return response;
};

// --- Execution Pipeline ---
export const handle = sequence(
  handleBypass,
  handleClientIp,
  handleSecurityContext,
  handleThemeContext,
  handleDevice,
  handleCacheAndTransform,
);
