import type { LayoutServerLoad } from "./$types";
import { APIService } from "@/services/APIService";
import type { RankMathHeadData, WPLokerBJMThemedData } from "@/types";
import { getCmsOrigin } from "@/utils/environment";
export const load: LayoutServerLoad = async ({ locals, url, fetch }) => {
  try {
    let themeData: WPLokerBJMThemedData = locals.themeData;
    // fetch RankMath head from CMS domain using the request URL path
    const origin = getCmsOrigin();
    const fullUrl = `${origin}${url.pathname}`;
    let rankMathHead: RankMathHeadData | null = null;
    try {
      rankMathHead = await APIService.getRankMathHeadGraphQL(
        fullUrl,
        undefined,
        fetch,
      );

      if (rankMathHead && url.origin) {
        try {
          const hostOnly = origin.replace(/^https?:\/\//, "").replace(/\/$/, "");
          const originRegex = new RegExp(`https?:\\/\\/${hostOnly}`, "g");
          rankMathHead = JSON.parse(JSON.stringify(rankMathHead).replace(originRegex, url.origin));
        } catch (e) {
          rankMathHead = JSON.parse(JSON.stringify(rankMathHead).split(origin).join(url.origin));
        }
      }
    } catch (e) {
      console.warn("layout load: failed to fetch RankMath head", e);
    }

    return {
      themeData,
      rankMathHead,
      deviceType: locals.deviceType
    };
  } catch (err) {
    return {
      themeData: null,
      rankMathHead: null,
      deviceType: locals.deviceType
    };
  }
};
