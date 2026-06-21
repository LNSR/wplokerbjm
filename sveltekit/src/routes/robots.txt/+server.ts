import type { RequestHandler } from "./$types";

export const GET: RequestHandler = ({ url }) => {
  const origin = url.origin;
  const body = `User-agent: *
Disallow:

Sitemap: ${origin}/sitemap_index.xml
`;
  return new Response(body, {
    headers: {
      "content-type": "text/plain; charset=utf-8",
      "cache-control": "public, max-age=0, s-maxage=3600",
    },
  });
};
