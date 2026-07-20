import type { RequestHandler } from './$types';
import { getCmsOrigin } from '@/utils/environment';

// mirror whatever the CMS serves for the XSL file so that the
// browser doesn't have to reach back to the backend origin.  We don't
// perform any host rewriting here because the XSL itself does not contain
// absolute URLs – it's just a stylesheet that Rank Math generates once.

export const GET: RequestHandler = async () => {
  const cmsBase = getCmsOrigin().replace(/\/+$/, '');
  if (!cmsBase) {
    return new Response('CMS origin not configured', { status: 500 });
  }

  const target = new URL('/main-sitemap.xsl', cmsBase).href.replaceAll("http://", "https://");
  const res = await fetch(target);
  if (!res.ok) {
    return new Response(res.statusText, { status: res.status });
  }

  const xsl = await res.text();
  return new Response(xsl, { headers: { 'content-type': 'application/xml' } });
};
