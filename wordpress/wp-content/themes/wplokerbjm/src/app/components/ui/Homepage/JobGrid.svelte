<script module lang="ts">
  import type { CardJob, JobGridProps, SearchState } from "@/types";
  import { SearchContext, SearchTitle, type JobDetailResponse } from "@/types";
  import {
    routeStore,
    routeStateStore,
    GlobalNavigateTo,
  } from "$lib/stores/Route.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  import { Virtualization } from "$lib/utils/Virtualization.svelte";
  import { scrollY, innerHeight } from "svelte/reactivity/window";

  const displayJobs = $derived(searchStore.jobs);
  const loading = $derived(searchStore.loading);
  const hasMore = $derived(searchStore.hasMore);
  const isDesktop = $derived.by(() => !isMobile());
  const displayTotalJobs = $derived(searchStore.totalJobs);
  const displayTitle = $derived(searchStore.title);
  const cardHeights = new SvelteMap(routeStateStore.getCardHeights("jobGrid"));

  class OverlayController {
    // overlay automatically open for desktop
    openJobDetailOverlay(slug: string): void {
      const job = displayJobs.find((j: CardJob) => j.slug === slug);
      jobOverlay.openOverlay(slug, job);
    }

    async handleJobClick(job: CardJob): Promise<void> {
      if (!job.permalink) return;

      // NOTE: Overlay feature is for desktop only (window.innerWidth >= 768)
      // Mobile navigation goes to SingleLowongan.svelte route

      // Save current search state before navigation (for both mobile and desktop)
      const currentSearchState = {
        jobs: searchStore.jobs,
        context: searchStore.context,
        title: searchStore.title,
        totalJobs: searchStore.totalJobs,
        maxNumPages: searchStore.maxNumPages,
        page: searchStore.page,
        filters: { ...searchStore.filters },
        loading: searchStore.loading,
        error: searchStore.error,
      };

      const path = () => {
        if (isDesktop) {
          const pathname = new URL(
            String(job.permalink),
            window.location.origin,
          ).pathname;
          return pathname;
        } else {
          return "/";
        }
      };

      routeStateStore.saveSearchState(path(), currentSearchState);

      // Save card heights before navigating/opening overlay
      routeStateStore.saveCardHeights(new Map(cardHeights), "jobGrid");

      async function MobileJobClick(): Promise<void> {
        // Mark as last visited before navigating
        routeStateStore.MarkVisitedJob(job.slug ?? "");
        // use SPA navigation to SingleLowongan.svelte route
        const url = new URL(
          String(job.permalink),
          routeStore.currentUrl.origin,
        );
        return void GlobalNavigateTo(
          url.pathname + url.search + url.hash,
          currentSearchState,
        );
      }

      if (isDesktop) {
        jobOverlay.openOverlay(job.slug ?? "", job);
        this.scrollToCard(job.slug ?? "");
      } else {
        await MobileJobClick();
      }
    }

    /**
     *  Check URL for overlay slug on mount and
     *  handle opening overlay if present/scroll skipping.
     *  If no slug, open overlay in placeholder mode for desktop.
     */
    checkUrlForOverlay() {
      if (typeof window !== "undefined") {
        const path = window.location.pathname;
        const match = path.match(/\/lowongan\/([^/]+)\/?$/);
        if (match && match[1] && isDesktop) {
          const slug = match[1];
          void overlayManager.openJobDetailOverlay(slug);
          routeStateStore.setSkipScrollRestore(path, false);
        }
      }
    }

    scrollToCard(slug: string): void {
      jobOverlay.scrollToCard(slug, 300, false, "grid");
    }
  }

  export const overlayManager = new OverlayController();
</script>

<script lang="ts">
  import { onMount } from "svelte";
  import { SvelteMap } from "svelte/reactivity";
  import type { Attachment } from "svelte/attachments";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";
  import { getThemeData } from "@/utils";
  import { searchStore } from "$lib/stores/Search.svelte";
  import { APIService } from "@/services/APIService";
  import { utilsSEO } from "$lib/utils/SEO.svelte";
  import JobCard from "@components/ui/Homepage/JobCard.svelte";
  import SingleOverlay from "@components/ui/Homepage/SingleOverlay.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";

  let initialLoading = $state(false);
  let hasRestoredState = $state(false);
  let isRefreshing = $state(false);
  let loadMoreSentinel = $state<HTMLElement | null>(null);

  const props: JobGridProps & { job?: JobDetailResponse | null } = $props();

  const {
    jobs = [],
    maxNumPages,
    context,
    filters,
    title,
    totalJobs,
    job,
  } = props;

  class VirtualizationManager {
    static computeListVirtualization(
      displayJobs: CardJob[],
      scrollY: number,
      containerHeight: number,
      cardHeights: Map<number, number>,
      fallbackHeight: number,
      gap: number,
      buffer: number,
    ) {
      return Virtualization.computeList({
        displayJobs,
        scrollY,
        containerHeight,
        cardHeights,
        fallbackHeight,
        gap,
        buffer,
      });
    }

    static measureHeight = (jobId?: number): Attachment<HTMLElement> => {
      return Virtualization.createMeasureHeight(cardHeights, jobId);
    };
  }

  // fallback height used until measurements are available
  const FALLBACK_ITEM_HEIGHT = 420;
  const gap = 24;
  const buffer = 3;
  const virtualization = $derived.by(() =>
    VirtualizationManager.computeListVirtualization(
      displayJobs,
      scrollY.current ?? 0,
      innerHeight.current ?? 800,
      new Map(cardHeights),
      FALLBACK_ITEM_HEIGHT,
      gap,
      buffer,
    ),
  );

  class JobGridHandler {
    private async fetchJobGrid(): Promise<JobGridProps> {
      try {
        const response = await APIService.fetchJobGridGraphQL({
          paged: 1,
          context: context || SearchContext.Latest,
          title: title || searchStore.title,
          total_jobs: totalJobs || 0,
          ...filters,
        });
        return response;
      } catch (error) {
        console.error("Error fetching job grid:", error);
        throw error;
      }
    }

    /**
     * Check if saved search state is valid based on last job update timestamp
     */
    private isValidState(
      savedSearchState: SearchState | undefined,
    ): boolean | undefined {
      let lastJobUpdateMs = 0;
      const themeData = getThemeData();
      if (themeData?.lastJobUpdate) {
        const parsed = Date.parse(themeData.lastJobUpdate);
        lastJobUpdateMs = isNaN(parsed) ? 0 : parsed;
      }
      const savedTimestamp =
        typeof savedSearchState?.timestamp === "number"
          ? savedSearchState.timestamp
          : 0;

      const shouldRestore =
        savedSearchState &&
        lastJobUpdateMs > 0 &&
        savedTimestamp > 0 &&
        savedTimestamp >= lastJobUpdateMs;

      return shouldRestore;
    }

    /**
     * Restore state according context from saved search state
     * context are from (e.g., "latest", "search")
     */
    private restoreState(savedSearchState: SearchState): void {
      if (!savedSearchState) return;
      const uniqueJobs = savedSearchState.jobs.filter(
        (job, index, self) =>
          index === self.findIndex((j) => j.permalink === job.permalink),
      );
      searchStore.jobs = [...uniqueJobs];
      searchStore.context = savedSearchState.context;
      searchStore.title = savedSearchState.title;
      searchStore.totalJobs = savedSearchState.totalJobs;
      searchStore.maxNumPages = savedSearchState.maxNumPages;
      searchStore.page = savedSearchState.page;
      searchStore.setFilters(savedSearchState.filters);
      searchStore.loading = false;
      searchStore.error = savedSearchState.error;

      hasRestoredState = true;
    }

    private async initializeFreshData(): Promise<void> {
      if (!jobs || jobs.length === 0) {
        initialLoading = true;
        try {
          const gridData = await this.fetchJobGrid();
          // Update search store with fetched data
          searchStore.jobs = gridData.jobs || [];
          searchStore.maxNumPages = gridData.maxNumPages || 1;
          searchStore.context = gridData.context || SearchContext.Latest;
          searchStore.title =
            (gridData.title as SearchTitle) || SearchTitle.Latest;
          searchStore.totalJobs = gridData.totalJobs || 0;
          // Update filters if provided
          if (gridData.filters) {
            searchStore.setFilters(gridData.filters);
          }
        } catch (error) {
          console.error("Failed to fetch job grid:", error);
          searchStore.jobs = [];
          searchStore.maxNumPages = 1;
          searchStore.context = SearchContext.Latest;
          searchStore.title = SearchTitle.Latest;
          searchStore.totalJobs = 0;
        } finally {
          initialLoading = false;
        }
      } else {
        // Use server-provided jobs to initialize search store
        searchStore.jobs = [...jobs];
        // Use provided `maxNumPages` (SSR or GraphQL)
        if (typeof maxNumPages === "number" && maxNumPages > 0) {
          searchStore.maxNumPages = maxNumPages;
        }
        if (context) searchStore.context = context;
        if (title) searchStore.title = title as SearchTitle;
        if (totalJobs !== undefined) searchStore.totalJobs = totalJobs;
        if (filters) searchStore.setFilters(filters);
      }
    }

    public async initializeJobs(): Promise<void> {
      // If we've already restored state for this component instance, don't do it again
      if (hasRestoredState) {
        console.log(
          "JobGrid: Already restored state for this component instance, skipping",
        );
        return;
      }

      // Check if there's a saved search state for this path
      const savedSearchState = routeStateStore.getSearchState(
        window.location.pathname,
      ) as SearchState | undefined;

      if (this.isValidState(savedSearchState)) {
        await this.restoreState(savedSearchState!);
      } else {
        // Clear stale saved state to keep sessionStorage clean
        if (savedSearchState) {
          routeStateStore.clearSearchState(window.location.pathname);
        }
        await this.initializeFreshData();
      }
    }
    public async refreshJobGrid() {
      if (isRefreshing) return;
      isRefreshing = true;
      try {
        if (searchStore.context !== SearchContext.Search) {
          const response = await APIService.fetchJobGridGraphQL({
            paged: 1,
            context: searchStore.context,
            title: searchStore.title,
            total_jobs: 0,
            ...searchStore.filters,
          });
          searchStore.jobs = response.jobs || [];
          // API returns `maxNumPages` for pagination metadata
          searchStore.maxNumPages = response.maxNumPages || 1;
          searchStore.context = response.context || SearchContext.Latest;
          searchStore.title =
            (response.title as SearchTitle) || SearchTitle.Latest;
          searchStore.totalJobs = response.totalJobs || 0;
          if (response.filters) {
            searchStore.setFilters(response.filters);
          }
          searchStore.page = 1; // Reset page
          searchStore.error = null;
        } else {
          // In search mode, re-run search
          await searchStore.searchJobs();
        }
      } catch (err) {
        console.error("Failed to refresh job grid:", err);
        searchStore.error = "Failed to refresh job grid";
      } finally {
        isRefreshing = false;
      }
    }
  }

  const jobGridHandler = new JobGridHandler();

  let _prevJobIds: number[] = [];

  onMount(() => {
    overlayManager.checkUrlForOverlay();
    void jobGridHandler.initializeJobs();
    if (job) {
      jobOverlay.overlayData = job; // no need to drill prop to JobDetail component
    }
  });

  $effect(() => {
    // Fetch first 54 immediately once jobs are available and then append subsequent batches
    const currJobIds = (searchStore.jobs || []).map(j => Number(j.id)).filter(id => !isNaN(id)) as number[];

    if (_prevJobIds.length === 0 && currJobIds.length > 0) {
      // initial load: take exactly the first 54
      const firstBatch = currJobIds.slice(0, 54);
      if (firstBatch.length > 0) {
        // Only fetch ItemList schema once on initial homepage load. Subsequent
        // appends should NOT re-fetch or replace the ItemList.
        try {
          const comp = routeStore.getComponentNamePath(routeStore.currentUrl.pathname);
          if (comp === 'Homepage') {
            void utilsSEO.addJobSchemas(firstBatch);
          }
        } catch {
          // fallback: if routeStore unavailable, do not fetch to be safe
        }
        // Track prev ids regardless so appended logic can still detect new items
        _prevJobIds = [...firstBatch];
      }
      return;
    }

    if (currJobIds.length > _prevJobIds.length) {
      // appended jobs (load more): only append up to the next 54
      const appended = currJobIds.slice(_prevJobIds.length, _prevJobIds.length + 54);
      if (appended.length > 0) {
        // Do NOT call addJobSchemas on append; only update our local tracking
        _prevJobIds = _prevJobIds.concat(appended);
      }
    }
  });

  $effect(() => {

    // Auto prefetch using IntersectionObserver sentinel to prepare next page
    if (!loadMoreSentinel) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (
            entry.isIntersecting &&
            hasMore &&
            !searchStore.nextPageLoadMoreCache &&
            !searchStore.isPrefetchingLoadMore
          ) {
            void searchStore.prefetchNextPage();
          }
        }
      },
      { root: null, rootMargin: "5000px" }, // large rootMargin to prefetch early
    );

    observer.observe(loadMoreSentinel);

    return () => {
      observer.disconnect();
    };
  });
</script>

<section class="relative mt-8" id="job-grid">
  <div class="flex items-center justify-between mb-6">
    {#if displayJobs.length}
      <h2 class="text-xl md:text-2xl font-semibold">{displayTitle}</h2>
    {:else}
      <h2 class="text-xl md:text-2xl font-semibold">Lowongan Kerja</h2>
    {/if}
    <button
      type="button"
      class="job-grid-refresh btn btn-lg rounded-full h-10 w-10 p-0 flex items-center justify-center text-current bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)] overflow-visible focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--wpl-global-color-1)]"
      aria-label="Segarkan lowongan"
      title="Segarkan"
      onclick={() => void jobGridHandler.refreshJobGrid()}
      disabled={isRefreshing || loading}
      tabindex="0"
    >
      <RefreshSpinner size="h-5 w-5" spin={isRefreshing} />
      <span class="sr-only"
        >{isRefreshing ? "Sedang menyegarkan" : "Segarkan"}</span
      >
    </button>
  </div>

  {#if isRefreshing}
    <div
      class="flex justify-center items-center min-h-[200px]"
      aria-live="polite"
    >
      <LoadingSpinner srLabel="Memuat grid..." size="md" />
    </div>
  {:else}
    {#if displayJobs.length && searchStore.context !== SearchContext.Latest}
      <div class="text-base font-medium mb-4">
        {displayTotalJobs} lowongan ditemukan
      </div>
    {/if}

    <div class="relative flex">
      <div class="w-full lg:w-[calc(100%-420px)]">
        {#if displayJobs.length}
          <div
            style="height: {virtualization.totalHeight}px; position: relative;"
          >
            {#each virtualization.visibleJobs as job, index (job.permalink)}
              {@const absoluteTop =
                virtualization.itemPositions[virtualization.startIndex + index]}
              <div
                style="position: absolute; transform: translate3d(0, {absoluteTop}px, 0); width: 100%;"
                {@attach VirtualizationManager.measureHeight(job.id)}
                class="transition-opacity duration-600 ease-in-out"
                onkeydown={(event) => {
                  if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    void overlayManager.handleJobClick(job);
                  }
                }}
                role="button"
                tabindex="0"
                aria-label={`View job details for ${job.title}`}
              >
                <JobCard
                  jobdata={job}
                  variant="featured"
                  permalink={job.permalink ?? ""}
                  isVisited={routeStateStore.hasVisitedJob(job.slug ?? "")}
                  onClick={() => {
                    void overlayManager.handleJobClick(job);
                  }}
                />
              </div>
            {/each}
            <!-- load-more sentinel placed at the end of the virtualization spacer -->
            <div
              bind:this={loadMoreSentinel}
              style="position: absolute; left:0; width:1px; height:1px; transform: translate3d(0, {Math.max(
                0,
                (virtualization.totalHeight || 0) - 1,
              )}px, 0); pointer-events:none; opacity:0; contain: layout size;"
              aria-hidden="true"
            ></div>
          </div>
        {:else if initialLoading}
          <div class="flex justify-center py-12">
            <LoadingSpinner srLabel="Memuat lowongan..." size="md" />
          </div>
        {:else}
          <div class="text-center py-12">
            <h2 class="text-2xl font-semibold mb-6">
              Tidak ada lowongan ditemukan.
            </h2>
            <p>Coba gunakan kata kunci atau filter lain.</p>
          </div>
        {/if}

        {#if hasMore}
          <div class="flex justify-center mt-8 z-100">
            <button
              type="button"
              class="btn rounded-lg font-semibold bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] border border-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] hover:text-[var(--wpl-global-color-5)] disabled:opacity-50 disabled:cursor-not-allowed"
              onclick={() => searchStore.appendCachedPage()}
              disabled={!searchStore.nextPageLoadMoreCache}
            >
              {searchStore.nextPageLoadMoreCache
                ? "Muat Lebih Banyak"
                : "Mempersiapkan..."}
            </button>
          </div>
        {/if}

        {#if !hasMore && displayJobs.length > 0}
          <div class="flex justify-center mt-8 z-100">
            <p class="bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] rounded-lg">Tidak ada sisa muatan lagi</p>
          </div>
        {/if}
      </div>

      {#if isDesktop}
        <div
          class="sticky self-start w-full"
          style:top={headerStore.totalOffset + "px"}
        >
          <SingleOverlay visible={true} />
        </div>
      {/if}
    </div>
  {/if}
</section>
