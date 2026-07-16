import type { RequestHandler } from "./$types";

import { getCmsOrigin } from "@/utils/environment";

// we'll still normalise the result so it never ends with a slash
function normaliseOrigin(o: string) {
  return o ? o.replace(/\/+$/, "") : "";
}

function escapeRegExp(s: string) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export const GET: RequestHandler = async ({ url }) => {
  // determine backend origin using the shared helper
  let cmsBase = getCmsOrigin();
  cmsBase = normaliseOrigin(cmsBase);
  if (!cmsBase) {
    return new Response("CMS origin not configured", { status: 500 });
  }

  const target = new URL(url.pathname, cmsBase).href;

  const res = await fetch(target);
  if (!res.ok) {
    return new Response(res.statusText, { status: res.status });
  }

  let xml = await res.text();
  const frontendOrigin = url.origin.replaceAll("http://", "https://");

  xml = xml.replace(new RegExp(escapeRegExp(cmsBase), "g"), frontendOrigin);
  xml = xml.replace(
    /href="\/\/(?:[^"]+)"/g,
    `href="${frontendOrigin}/main-sitemap.xsl"`,
  );

  return new Response(xml, { headers: { "content-type": "application/xml" } });
};
