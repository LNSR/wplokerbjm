import type { PageServerLoad } from "./$types";

import { APIService } from "@/services/APIService";
import type { JobSchemaResponse } from "@/types";
import { getCmsOrigin } from "@/utils/environment";
export const load: PageServerLoad = async ({ url, fetch }) => {
  try {
    const [carousel, jobGrid] = await Promise.all([
      APIService.fetchCarouselGraphQL(fetch),
      APIService.fetchJobGridGraphQL({ paged: 1 }, fetch),
    ]);

    // compute initial ItemList schema for homepage using jobGrid IDs
    let itemListSchema = null;
    const ids = (jobGrid?.jobs || [])
      .map((j: any) => Number(j.id))
      .filter((n: number) => !isNaN(n));

    if (ids.length > 0) {
      try {
        const schemas = await APIService.fetchJobSchemasGraphQL(
          ids,
          undefined,
          "ItemList",
          fetch,
        );
        itemListSchema = schemas?.[0] || null;

        // normalize any URL origins inside the schema to current request origin
        if (itemListSchema && url.origin) {
          const cmsOrigin = getCmsOrigin();
          try {
            const hostOnly = cmsOrigin.replace(/^https?:\/\//, "").replace(/\/$/, "");
            const originRegex = new RegExp(`https?:\\/\\/${hostOnly}`, "g");
            const str = JSON.stringify(itemListSchema);
            itemListSchema = str.replace(originRegex, url.origin);
          } catch (e) {
            console.warn("Failed to replace itemListSchema URLs, using original", e);
          }
        }
      } catch (e) {
        console.warn("Failed to fetch ItemList schema on server load:", e);
      }
    }

    return {
      carousel: carousel ?? { jobs: [], totalJobs: 0 },
      jobGrid: jobGrid ?? { jobs: [], maxNumPages: 1, totalJobs: 0 },
      itemListSchema: itemListSchema as JobSchemaResponse["schemas"],
    };
  } catch (err) {
    console.error("+page.server load error (homepage):", err);
    return {
      carousel: { jobs: [], totalJobs: 0 },
      jobGrid: { jobs: [], maxNumPages: 1, totalJobs: 0 },
      itemListSchema: null,
    };
  }
};
