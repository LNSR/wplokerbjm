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
    // always fetch the job detail first; we need its id for schema
    const job = await APIService.fetchJobDetailGraphQL(
      slug,
      undefined,
      fetch,
    );

    // compute jobSchema even if mobile; schema is useful for SEO on both
    let jobSchema: any = null;
    if (job && job.id) {
      try {
        const schemas = await APIService.fetchJobSchemasGraphQL(
          [Number(job.id)],
          undefined,
          "JobPosting",
          fetch,
        );
        jobSchema = schemas?.[0] || null;

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
      } catch (e) {
        console.warn("Failed to fetch job schema on server load:", e);
      }
    }

    if (!isMobile) {
      if (!job) throw error(410, "Lowongan tidak ditemukan");

      const [carousel, jobGrid] = await Promise.all([
        APIService.fetchCarouselGraphQL(fetch),
        APIService.fetchJobGridGraphQL({ paged: 1 }, fetch),
      ]);

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
