import type { PageServerLoad } from "./$types";
import { APIServiceServer, APIServiceShared } from "@/services/graphql/APIService";
import type { JobSchemaResponse } from "@/types";
import { getCmsOrigin } from "@/utils/environment";
import { schemaScriptAttach, schemaScriptParser } from "$lib/server/utils/scripts.server";
export const load: PageServerLoad = async ({ url, fetch }) =>
{
  try
  {
    const [ carousel, jobGrid ] = await Promise.all([
      APIServiceShared.fetchCarouselGraphQL(),
      APIServiceShared.fetchJobGridGraphQL({ paged: 1 }),
    ]);

    // compute initial ItemList schema for homepage using jobGrid IDs
    let itemListSchema: string | null = null;
    const ids = (jobGrid?.jobs || [])
      .map((j) => Number(j.id))
      .filter((n) => !isNaN(n));

    if (ids.length > 0)
    {
      try
      {
        const schemas: JobSchemaResponse[ 'schemas' ] = await APIServiceServer.fetchJobSchemasGraphQL(
          ids,
          "ItemList",
        );
        schemas && (itemListSchema = schemaScriptParser(schemas));

        // normalize any URL origins inside the schema to current request origin
        if (itemListSchema && url.origin)
        {
          const cmsOrigin = getCmsOrigin();
          try
          {
            const hostOnly = cmsOrigin.replace(/^https?:\/\//, "").replace(/\/$/, "");
            const originRegex = new RegExp(`https?:\\/\\/${hostOnly}`, "g");
            itemListSchema = JSON.stringify(itemListSchema).replace(originRegex, url.origin);
          } catch (e)
          {
            console.warn("Failed to replace itemListSchema URLs, using original", e);
          }
        }
      } catch (e)
      {
        console.warn("Failed to fetch ItemList schema on server load:", e);
      }
    }

    return {
      carousel: carousel ?? { jobs: [], totalJobs: 0 },
      jobGrid: jobGrid ?? { jobs: [], maxNumPages: 1, totalJobs: 0 },
      itemListSchemaScript: itemListSchema
        ? await schemaScriptAttach(itemListSchema, "ItemList")
        : "",
    };
  } catch (err)
  {
    console.error("+page.server load error (homepage):", err);
    return {
      carousel: { jobs: [], totalJobs: 0 },
      jobGrid: { jobs: [], maxNumPages: 1, totalJobs: 0 },
      itemListSchemaScript: "",
    };
  }
};
