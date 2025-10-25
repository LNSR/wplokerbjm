<script lang="ts">
  import { onMount } from "svelte";
  import JobDetail from "@components/ui/Shared/JobDetail.svelte";
  import SkeletonSingleLowongan from "@components/ui/Skeletons/SkeletonSingleLowongan.svelte";
  import { APIService } from "@/services/APIService";
  import { type SingleOverlayResponse as SingleJob } from "@/types";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";

  let { job: initialJob, slug: passedSlug } = $props<{
    job?: SingleJob;
    slug?: string;
  }>();

  let job = $state<SingleJob | null>(initialJob || null);
  let isLoading = $state(!initialJob); // Show skeleton if no initial job data
  let currentRequestId = $state(0);
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;
  let abortController: AbortController | null = null;

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
      const fetchedJob = await APIService.fetchSingleOverlay(slug, {
        signal: abortController.signal,
      });
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

  onMount(() => {
    if (!job) {
      // Fallback to API
      const slug =
        passedSlug || window.location.pathname.split("/").filter(Boolean).pop();

      if (slug) {
        debouncedFetch(slug);
      } else {
        isLoading = false;
      }
    } else {
      isLoading = false;
    }

    return () => {
      if (debounceTimer) clearTimeout(debounceTimer);
      if (abortController) abortController.abort();
    };
  });

  // Watch for slug changes (in case component is reused)
  $effect(() => {
    const slug =
      passedSlug ||
      (typeof window !== "undefined"
        ? window.location.pathname.split("/").filter(Boolean).pop()
        : null);
    if (slug && !job) {
      debouncedFetch(slug);
    }
  });
</script>

{#if isLoading}
  <main
    class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8"
    style:padding-top={headerStore.totalOffset + "px"}
  >
    <SkeletonSingleLowongan />
  </main>
{:else if job}
  <main
    class="container mx-auto max-w-[90vw] lg:max-w-[60vw] space-y-8 mt-12"
    style:padding-top={headerStore.totalOffset + "px"}
  >
    <JobDetail {job} />
  </main>
{/if}
