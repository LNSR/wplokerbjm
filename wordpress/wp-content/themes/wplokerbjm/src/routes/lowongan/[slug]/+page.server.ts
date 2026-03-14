import type { PageServerLoad } from "./$types";
import { error } from '@sveltejs/kit';
import { APIService } from "@/services/APIService";
import { getCmsOrigin } from "@/utils/environment";
export const ssr = true;
export const csr = true;
export const load: PageServerLoad = async ({ params, locals, url, fetch }) => {
  const slug = String(params.slug ?? "");
  if (!slug) throw error(410, "Lowongan tidak ditemukan");

  const isMobile = Boolean(locals.deviceType?.isMobile);

  try {
    const jobPromise = APIService.fetchJobDetailGraphQL(slug, undefined, fetch);
    const schemaPromise = APIService.fetchJobSchemasGraphQL(slug, undefined, "JobPosting", fetch).catch(
      (e) => {
        console.warn("Failed to fetch job schema on server load:", e);
        return null;
      },
    );

    const desktopDataPromise = !isMobile
      ? Promise.all([
        APIService.fetchCarouselGraphQL(fetch),
        APIService.fetchJobGridGraphQL({ paged: 1 }, fetch),
      ]).catch((e) => {
        console.warn("Failed to fetch carousel/jobGrid on server load:", e);
        return [null, null] as const;
      })
      : Promise.resolve([null, null] as const);

    const [job, schemas, [carousel, jobGrid]] = await Promise.all([
      jobPromise,
      schemaPromise,
      desktopDataPromise,
    ]);

    let jobSchema: any = schemas?.[0] || null;
    if (jobSchema && url.origin) {
      const cmsOrigin = getCmsOrigin();
      try {
        const hostOnly = cmsOrigin.replace(/^https?:\/\//, "").replace(/\/$/, "");
        const originRegex = new RegExp(`https?:\\/\\/${hostOnly}`, "g");
        const str = JSON.stringify(jobSchema);
        jobSchema = JSON.parse(str.replace(originRegex, url.origin));
      } catch (e) {
        const str = JSON.stringify(jobSchema);
        jobSchema = JSON.parse(str.split(cmsOrigin).join(url.origin));
      }
    }

    if (!isMobile) {
      if (!job) throw error(410, "Lowongan tidak ditemukan");

      return {
        carousel: carousel ?? { jobs: [], totalJobs: 0 },
        jobGrid: jobGrid ?? { jobs: [], maxNumPages: 1, totalJobs: 0 },
        job: job ?? null,
        jobSchema,
      };
    } else {
      if (!job) throw error(410, "Lowongan tidak ditemukan");
      return { job: job ?? null, jobSchema };
    }
  } catch (err) {
    console.error("+page.server load error for /lowongan/[slug]:", err);
    throw error(410, "Lowongan tidak ditemukan");
  }
};
