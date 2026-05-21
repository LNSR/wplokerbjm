import { PUBLIC_CMS_ORIGIN, PUBLIC_CMS_ORIGIN_DEV } from "$env/static/public";
import { dev } from "$app/environment";

/**
 * Return the canonical origin that should be used when constructing
 * absolute URLs for API requests (including rankmath head fetches).
 *
 * WordPress may live on a different domain than the frontend bundle, and the
 * head data endpoint requires the CMS host so it can produce valid canonicals.
 */
export function getCmsOrigin(): string
{
  return dev ? PUBLIC_CMS_ORIGIN_DEV : PUBLIC_CMS_ORIGIN;
}
