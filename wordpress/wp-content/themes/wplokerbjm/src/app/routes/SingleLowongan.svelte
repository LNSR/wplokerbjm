<script lang="ts">
  import { onMount } from "svelte";
  import JobDetail from "@components/ui/Shared/JobDetail.svelte";
  import SkeletonSingleLowongan from "@components/ui/Skeletons/SkeletonSingleLowongan.svelte";
  import { APIService } from "@/services/APIService";
  import { type JobDetailResponse as SingleJob } from "@/types";
  import { utilsSEO } from "$lib/utils/SEO.svelte";
  import { routeStore } from "$lib/stores/Route.svelte";
  const { job: initialJob } = $props<{
    job: SingleJob;
  }>();

  let job = $state<SingleJob | null>(null);
  let isLoading = $state(true);
  let currentRequestId = $state(0);
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;
  let abortController: AbortController | null = null;

  function getSlugFromUrl(): string | null {
    if (typeof window === "undefined") return null;
    return window.location.pathname.split("/").filter(Boolean).pop() || null;
  }

  function debouncedFetch(jobSlug: string): void {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchJob(jobSlug);
    }, 300);
  }

  async function fetchJob(slug: string): Promise<void> {
    // Cancel any previous request
    if (abortController) {
      abortController.abort();
    }

    const requestId = ++currentRequestId;
    abortController = new AbortController();
    isLoading = true;
    job = null; // Reset job state for new fetch

    try {
      const fetchedJob = await APIService.fetchJobDetailGraphQL(slug, abortController.signal);
      // Only update if this is still the latest request and not aborted
      if (requestId === currentRequestId && !abortController.signal.aborted) {
        job = fetchedJob;
      }
    } catch (error) {
      if (
        error &&
        typeof error === "object" &&
        "name" in error &&
        error.name !== "AbortError"
      ) {
        console.error("Failed to fetch job:", error);
      }
      // Don't update state if aborted
    } finally {
      // Only set loading to false if this is the latest request and not aborted
      if (requestId === currentRequestId && !abortController.signal.aborted) {
        isLoading = false;
      }
    }
  }

  // server props take priority on initial load, else fetch from API
  onMount(() => {
    if (initialJob) {
      job = initialJob;
      isLoading = false;
    } else {
      // Always parse slug from URL
      const slug = getSlugFromUrl();
      if (slug) {
        debouncedFetch(slug);
      } else {
        isLoading = false;
      }
    }

    return () => {
      if (debounceTimer) clearTimeout(debounceTimer);
      if (abortController) abortController.abort();
    };
  });

  // Watch for slug changes (in case component is reused or URL changes)
  $effect(() => {
    const slug = getSlugFromUrl();
    if (slug && !job) {
      debouncedFetch(slug);
    }
    if (job && job.id && !routeStore.isInitialLoad) {
      void utilsSEO.RemoveAllSchemas();
      void utilsSEO.addJobPostingJsonLd([Number(job.id)]);
    }
  });
</script>

{#if isLoading}
  <SkeletonSingleLowongan />
{:else if job}
  <main class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12">
    <JobDetail {job} />
  </main>
{/if}