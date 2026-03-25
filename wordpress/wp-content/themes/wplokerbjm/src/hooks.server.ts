import type { Handle } from "@sveltejs/kit";
import type { IncomingRequestCfProperties } from "@cloudflare/workers-types";
import type { WPLokerBJMThemedData } from "@/types";
import { handleDeviceDetector } from "sveltekit-device-detector";
import { APIService } from "@/services/APIService";
import { isDevelopmentMode } from "@/utils";

const deviceHandler: Handle = handleDeviceDetector({});

class hooksHelper {
  static isAuthenticated(cookies: string | null): boolean {
    if (!cookies) return false;

    const wpAuthCookiePattern = /wordpress_logged_in|wordpress_sec|wordpress_\w+_?\d+/i;

    if (/jwt-token=([^;]+)/.test(cookies)) return true;
    if (wpAuthCookiePattern.test(cookies)) return true;

    return false;
  }

  static prependHeader(headers: Headers, name: string, value: string): void {
    const existing = headers.get(name);
    if (existing) {
      headers.set(name, `${value}, ${existing}`);
    } else {
      headers.set(name, value);
    }
  }

  static filterCookieString(raw: string): string {
    const parts = raw.split(";").map(p => p.trim());
    const allowed = parts.filter(cook => {
      // name before first =
      const name = cook.split("=")[0] || "";
      return (
        name.toLowerCase().startsWith("wordpress") ||
        name.toLowerCase().startsWith("wp") ||
        name.toLowerCase().startsWith("jwt-token")
      );
    });
    // you could add more device-specific logic here if needed
    return allowed.join("; ");
  }
}

export const handle: Handle = async ({ event, resolve }) => {

  const originalFetch = event.fetch;
  let response: Response;

  const wrappedFetch = ((info: RequestInfo | URL, init: RequestInit = {}) => {
    init.headers = new Headers(init.headers);

    const cookie = event.request.headers.get("cookie");
    if (cookie) {
      const filtered = hooksHelper.filterCookieString(cookie);
      if (filtered) {
        init.headers.set("cookie", filtered);
      }

      if (!init.headers.has("authorization")) {
        const m = filtered.match(/(?:^|;\s*)jwt-token=([^;]+)/);
        if (m && m[1]) {
          const token = decodeURIComponent(m[1]);
          init.headers.set("Authorization", `Bearer ${token}`);
        }
      }
    }

    return originalFetch(info, init);
  }) as unknown as typeof event.fetch;

  Object.assign(wrappedFetch, originalFetch);
  event.fetch = wrappedFetch;

  const cf = (event.request as Request & { cf?: IncomingRequestCfProperties }).cf;
  if (cf?.deviceType) {
    event.locals.deviceType = {
      isMobile: cf.deviceType === "mobile",
      deviceType: cf.deviceType,
    };
  }

  // fetch theme data as early as possible so load functions can see it
  try {
    const themeData: WPLokerBJMThemedData | null = await APIService.getThemeDataGraphQL(undefined, event.fetch);
    event.locals.themeData = themeData;
  } catch (e) {
    console.warn("hooks.handle: failed to fetch theme data", e);
    event.locals.themeData = null;
  }

  const path = event.url.pathname;
  const cookie = event.request.headers.get("cookie");
  const authenticated = hooksHelper.isAuthenticated(cookie);

  try {
    if (cf?.deviceType) {
      response = await resolve(event);
    } else {
      response = await deviceHandler({ event, resolve });
    }
  } catch (err) {
    console.error("hooks.handle: error in device handler", err);
    response = await deviceHandler({ event, resolve });
  }

  // vary responses based on device type so caches key separately
  if (event.locals.deviceType) {
    const existing = response.headers.get("Vary");
    const varyValue = existing ? `${existing}, Device-Type` : "Device-Type";
    const varyValueWithCookie = `${varyValue}, Cookie, Content-Encoding`; // also vary by cookie since auth status can affect content and we filter cookies in the fetch wrapper
    response.headers.set("Vary", varyValueWithCookie);
    try {
      const dt = event.locals.deviceType.deviceType || (event.locals.deviceType.isMobile ? "mobile" : "desktop");
      response.headers.set("Device-Type", dt);
    } catch (e) {
      console.warn("hooks.handle: failed to set Device-Type header", e);
    }
  }
  const contentType = response.headers.get("content-type") || "";
  const publicCache = "public, max-age=60, stale-while-revalidate=3600, s-maxage=5184000, stale-if-error=86400";
  const privateCache = "private, max-age=20, must-revalidate";
  const DevModeCache = "private, no-cache, must-revalidate";
  // Set cache control headers
  if (contentType.startsWith("text/html")) {
    if (authenticated) {
      // Don't cache authenticated requests
      response.headers.set(
        "Cache-Control",
        isDevelopmentMode() ? DevModeCache : privateCache
      );
    } else {
      response.headers.set(
        "Cache-Control",
        isDevelopmentMode() ? DevModeCache : publicCache
      );
    }

    // Prepend preload Link header for the site logo without overwriting existing Link entries
    try {
      const themeData = event.locals.themeData;
      const logoUrl = themeData?.logo?.logoUrl;
      if (logoUrl) {

        let link = `<${logoUrl}>; rel=preload; as=image`;
        link += `; nopush`;

        hooksHelper.prependHeader(response.headers, "Link", link);
      }
    } catch (e) {
      console.warn("hooks.handle: failed to append Link header", e);
    }

    response.headers.set("Service-Worker-Allowed", "/");
    response.headers.set("Cross-Origin-Opener-Policy", "same-origin");
    response.headers.set("Cross-Origin-Embedder-Policy", "credentialless");
  } else if (contentType.includes("application/json") || contentType.includes("application/xml")) {
    if (authenticated) {
      response.headers.set("Cache-Control", isDevelopmentMode() ? DevModeCache : privateCache);
    } else {
      response.headers.set(
        "Cache-Control",
        isDevelopmentMode() ? DevModeCache : publicCache
      );
    }
  }

  // some Partytown assets are served with text/javascript and don't hit the
  // html branch, so ensure the service worker header is always added when
  // any path contains the special prefix.
  if (path.startsWith("/~partytown") || path.includes("/~partytown")) {
    response.headers.set("Service-Worker-Allowed", "/");
  }

  // always expose and allow common headers for CORS responses
  response.headers.set("Access-Control-Expose-Headers", 'ETag, CF-Ray, Last-Modified');
  response.headers.set("Access-Control-Allow-Headers", "Authorization, Content-Type, If-None-Match, If-Match, If-Modified-Since, If-Unmodified-Since");
  return response;
};