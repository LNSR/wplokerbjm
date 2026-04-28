import type { LayoutServerLoad } from "./$types";
import { APIServiceServer } from "@/services/graphql/APIService";
import type { WPLokerBJMThemedData } from "@/types";
import { getCmsOrigin } from "@/utils/environment";
import { inlineScript } from "$lib/server/utils/scripts.server";
export const load: LayoutServerLoad = async ({ locals, url, fetch }) => {
  try {
    let themeData: WPLokerBJMThemedData = locals.themeData;
    const origin = getCmsOrigin();
    const fullUrl = `${origin}${url.pathname}`;
    let rankMathHead = null;
    try {
      rankMathHead = await APIServiceServer.getRankMathHeadGraphQL(
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
          console.warn("layout load: failed to replace RankMath head URLs, using original", e);
        }
      }
    } catch (e) {
      console.warn("layout load: failed to fetch RankMath head", e);
    }

    return {
      themeData,
      rankMathHead,
      deviceType: locals.deviceType,
      inlineScript,
    };
  } catch (err) {
    console.error("Error in layout load function:", err);
    return {
      themeData: null,
      rankMathHead: null,
      deviceType: locals.deviceType,
      inlineScript,
    };
  }
};
