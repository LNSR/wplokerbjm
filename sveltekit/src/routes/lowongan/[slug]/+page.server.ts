import type { PageServerLoad } from "./$types";
import { error } from '@sveltejs/kit';
import { APIServiceServer, APIServiceShared } from "@/services/graphql/APIService";
import { getCmsOrigin } from "@/utils/environment";
import { schemaScriptAttach, schemaScriptParser } from "$lib/server/utils/scripts.server";
import { collectPreloadLinksForJob } from "$lib/server/utils/http.server";
import type { JobDetailResponse } from "@/types/API";
export const load: PageServerLoad = async ({ params, locals, url, fetch }) =>
{
  const slug = String(params.slug ?? "");
  if (!slug) throw error(410, "Lowongan tidak ditemukan");

  const isMobile = Boolean(locals.deviceType?.isMobile);

  // Preview detection: draft saves redirect to /lowongan/{post_id} (numeric slug),
  // with ?p= kept as a fallback for previously bookmarked preview links.
  const numericSlug = /^[0-9]+$/.test(slug);
  const pParam = url.searchParams.get("p");
  const isPreview = numericSlug || (pParam !== null && /^[0-9]+$/.test(pParam));
  const previewId = numericSlug ? Number(slug) : Number(pParam);

  try
  {
    if (isPreview)
    {
      const job: JobDetailResponse = await APIServiceServer.fetchJobDetailPreviewGraphQL(previewId);
      if (!job) throw error(410, "Lowongan tidak ditemukan");
      locals.postTime = job.post_time;
      locals.isPreview = true; // lets hooks apply noindex (and formerly no-store)

      return {
        isPreview: true,
        job,
        jobSchemaScript: "",
        carousel: { jobs: [], totalJobs: 0 },
        jobGrid: { jobs: [], maxNumPages: 1, totalJobs: 0 },
      };
    }

    const jobPromise: Promise<JobDetailResponse> = APIServiceServer.fetchJobDetailGraphQL(slug);
    const schemaPromise = APIServiceServer.fetchJobSchemasGraphQL(slug, "JobPosting").catch(
      (e) =>
      {
        console.warn("Failed to fetch job schema on server load:", e);
        return null;
      },
    );

    const desktopDataPromise = !isMobile
      ? Promise.all([
        APIServiceShared.fetchCarouselGraphQL(),
        APIServiceShared.fetchJobGridGraphQL({ paged: 1 }),
      ]).catch((e) =>
      {
        console.warn("Failed to fetch carousel/jobGrid on server load:", e);
        return [ null, null ] as const;
      })
      : Promise.resolve([ null, null ] as const);

    const [ job, schemas, [ carousel, jobGrid ] ] = await Promise.all([
      jobPromise,
      schemaPromise,
      desktopDataPromise,
    ]);

    let jobSchema: string | null = null;
    schemas && (jobSchema = schemaScriptParser(schemas));
    locals.postTime = job.post_time;
    if (jobSchema && url.origin)
    {
      const cmsOrigin: string = getCmsOrigin();
      try
      {
        const hostOnly = cmsOrigin.replace(/^https?:\/\//, "").replace(/\/$/, "");
        const originRegex = new RegExp(`https?:\\/\\/${hostOnly}`, "g");
        jobSchema = JSON.stringify(jobSchema).replace(originRegex, url.origin);
      } catch (e)
      {
        console.warn("Failed to replace job schema URLs, using original", e);
      }
    }

    if (!job) throw error(410, "Lowongan tidak ditemukan");
    const linkHeader = collectPreloadLinksForJob(job);
    linkHeader && (locals.earlyHintsLink = linkHeader);
    if (!isMobile)
    {

      return {
        carousel: carousel ?? { jobs: [], totalJobs: 0 },
        jobGrid: jobGrid ?? { jobs: [], maxNumPages: 1, totalJobs: 0 },
        job,
        jobSchemaScript: jobSchema
          ? await schemaScriptAttach(jobSchema, "JobPosting", job.id)
          : "",
      };

    } else
    {

      return {
        job,
        jobSchemaScript: jobSchema
          ? await schemaScriptAttach(jobSchema, "JobPosting", job.id)
          : "",
      };

    }
  } catch (err)
  {
    console.error("+page.server load error for /lowongan/[slug]:", err);
    throw error(410, "Lowongan tidak ditemukan");
  }
};
