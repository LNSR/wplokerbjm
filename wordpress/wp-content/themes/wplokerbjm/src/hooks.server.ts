import type { Handle } from "@sveltejs/kit";
import { handleDeviceDetector } from "sveltekit-device-detector";
import { APIService } from "@/services/APIService";

const deviceHandler: Handle = handleDeviceDetector({});

// Check if request is authenticated
function isAuthenticated(cookies: string | null): boolean {
  if (!cookies) return false;

  // Check for JWT token
  if (/jwt-token=([^;]+)/.test(cookies)) return true;

  // Check for WordPress authentication cookies
  const wpAuthCookiePattern = /wordpress_logged_in|wordpress_sec|wordpress_\w+_?\d+/i;
  if (wpAuthCookiePattern.test(cookies)) return true;

  return false;
}

export const handle: Handle = async ({ event, resolve }) => {
  // helper that filters cookies based on whitelisted prefixes
  function filterCookieString(raw: string, isMobile: boolean | null): string {
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

  const original = event.fetch;
  event.fetch = (info, init: RequestInit = {}) => {
    init.headers = new Headers(init.headers);

    const cookie = event.request.headers.get("cookie");
    if (cookie) {
      const filtered = filterCookieString(
        cookie,
        event.locals.deviceType?.isMobile ?? null
      );
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

    return original(info, init);
  };

  let response: Response;
  try {
    const themeData = await APIService.getThemeDataGraphQL(undefined, undefined, event.fetch);
    event.locals.themeData = themeData;
  } catch (e) {
    console.warn("hooks.handle: failed to fetch theme data", e);
    event.locals.themeData = null;
  }
  try {
    const cf = (event.request as Request & { cf?: any }).cf;
    if (cf?.deviceType) {
      event.locals.deviceType = {
        isMobile: cf.deviceType === "mobile",
        deviceType: cf.deviceType,
      } as any;
      response = await resolve(event);
    } else {
      response = await deviceHandler({ event, resolve });
    }
  } catch (err) {
    // On any error during device detection, safely fall back to middleware.
    response = await deviceHandler({ event, resolve });
  }

  const path = event.url.pathname;
  const contentType = response.headers.get("content-type") || "";
  const cookie = event.request.headers.get("cookie");
  const authenticated = isAuthenticated(cookie);

  // vary responses based on device type so caches key separately
  if (event.locals.deviceType) {
    // append to existing Vary header if present
    const existing = response.headers.get("Vary");
    const varyValue = existing ? `${existing}, Device-Type` : "Device-Type";
    response.headers.set("Vary", varyValue);
  }

  // Set cache control headers
  if (contentType.startsWith("text/html")) {
    if (authenticated) {
      // Don't cache authenticated requests
      response.headers.set(
        "Cache-Control",
        "private, no-cache, no-store, must-revalidate"
      );
    } else {
      // Serve stale content while revalidating for public requests
      // max-age: 2 hours, stale-while-revalidate: 7 days
      response.headers.set(
        "Cache-Control",
        "public, max-age=7200, s-maxage=86400, stale-while-revalidate=604800, must-revalidate"
      );
    }

    response.headers.set("Service-Worker-Allowed", "/");
    response.headers.set("Cross-Origin-Opener-Policy", "same-origin");
    response.headers.set("Cross-Origin-Embedder-Policy", "credentialless");
  } else if (
    contentType.startsWith("text/javascript") ||
    contentType.startsWith("application/javascript")
  ) {
  } else if (contentType.includes("application/json")) {
    if (authenticated) {
      response.headers.set("Cache-Control", "private, no-cache, no-store, must-revalidate");
    } else {
      response.headers.set(
        "Cache-Control",
        "public, max-age=7200, s-maxage=86400, stale-while-revalidate=604800, must-revalidate"
      );
    }
  }

  // some Partytown assets are served with text/javascript and don't hit the
  // html branch, so ensure the service worker header is always added when
  // any path contains the special prefix.
  if (path.startsWith("/~partytown") || path.includes("/~partytown")) {
    response.headers.set("Service-Worker-Allowed", "/");
  }

  function prependHeader(headers: Headers, name: string, value: string) {
    const existing = headers.get(name);
    if (existing) {
      headers.set(name, `${value}, ${existing}`);
    } else {
      headers.set(name, value);
    }
  }

  // Prepend preload Link header for the site logo without overwriting existing Link entries
  try {
    const themeData = event.locals.themeData;
    const logoUrl = themeData?.logo?.logoUrl;
    if (logoUrl) {

      let link = `<${logoUrl}>; rel=preload; as=image`;
      link += `; nopush`;

      prependHeader(response.headers, "Link", link);
    }
  } catch (e) {
    console.warn("hooks.handle: failed to append Link header", e);
  }

  return response;
};
