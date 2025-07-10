import type { Job } from "@/types/job";

export function getJobSlugFromRoute(routePath: string): string | null {
  const match = routePath.match(/^\/lowongan\/([^/?#]+)/);
  return match ? match[1] : null;
}

/**
 * Get the slug for a job by its ID from a jobs array.
 * @param jobs Array of job objects (must have id and permalink)
 * @param id The job ID to search for
 * @returns The slug string or null if not found
 */
export function getJobSlugFromId(
  jobs: Job[],
  id: number
): string | null {
  const job = jobs.find((j) => j.id === id && j.permalink);
  if (!job) return null;
  const match = job.permalink!.match(/\/lowongan\/([^/?#]+)/);
  return match ? match[1] : null;
}