import type { LayoutServerLoad } from "./$types";
import { APIServiceServer } from "@/services/graphql/APIService";
import type { WPLokerBJMThemedData } from "@/types";
import { getCmsOrigin } from "@/utils/environment";
export const load: LayoutServerLoad = async ({ locals, url, fetch }) =>
{
  try
  {
    let themeData: WPLokerBJMThemedData = locals.themeData;
    const origin = getCmsOrigin();
    const fullUrl = `${origin}${url.pathname}`;
    let rankMathHead = null;

    // Preview requests (numeric slug route or ?p= fallback) target non-public
    // drafts: Rank Math renders a 404 head for them, so skip fetching it
    // entirely (also avoids caching that 404 head in RANKMATH_HEAD_PREFIX).
    const isPreviewRequest =
      /^\/lowongan\/\d+$/.test(url.pathname) ||
      (() => {
        const p = url.searchParams.get("p");
        return p !== null && /^[0-9]+$/.test(p);
      })();

    if (!isPreviewRequest)
      try
      {
        rankMathHead = await APIServiceServer.getRankMathHeadGraphQL(
          fullUrl,
        );

        if (rankMathHead && url.origin)
        {
          try
          {
            const hostOnly = origin.replace(/^https?:\/\//, "").replace(/\/$/, "");
            const originRegex = new RegExp(`https?:\\/\\/${hostOnly}`, "g");
            rankMathHead = rankMathHead.replace(originRegex, url.origin);

          } catch (e)
          {
            console.warn("layout load: failed to replace RankMath head URLs, using original", e);
          }
        }
      } catch (e)
      {
        console.warn("layout load: failed to fetch RankMath head", e);
      }

    return {
      themeData,
      rankMathHead,
      deviceType: locals.deviceType,
    };
  } catch (err)
  {
    console.error("Error in layout load function:", err);
    return {
      themeData: null,
      rankMathHead: null,
      deviceType: locals.deviceType,
    };
  }
};
