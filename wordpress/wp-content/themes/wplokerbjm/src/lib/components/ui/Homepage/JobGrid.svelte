<script module lang="ts">
  import type {
    CardJob,
    JobGridProps,
    LoadMoreResponse,
    SearchResponse,
    SearchState,
    StatusPekerjaanNumber, StatusPekerjaanString
  } from "@/types";
  import { goto } from "$app/navigation";
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import { jobListingStore } from "$lib/stores/JobListingStore.svelte";
  import { APIServiceBrowser } from "@/services/graphql/APIService";
  import { withTimeout } from "es-toolkit";
  import { SearchUtils } from "@/utils/search";
  import { useVirtualization } from "@/lib/features/Virtualization.svelte";
  import { scrollY, innerHeight } from "svelte/reactivity/window";
  import { browser } from "$app/environment";
  import { useSidePanel } from "$lib/composables/SidePanel.svelte";
  import { deviceDetector } from "$lib/features/DeviceDetector.svelte";

  const isMobile = $derived(deviceDetector.isPlatformMobile);
  const displayJobs = $derived.by(() => {
    if (jobListingStore.context === "search") return jobListingStore.jobs;

    /**
     * only "Normal" one @see StatusPekerjaanString from WP backend
     */
    const status: StatusPekerjaanNumber = 0; // Normal

    return jobListingStore.jobs.filter(
      (job) => job.status_pekerjaan === status,
    );
  });
  const loading = $derived(jobListingStore.loading);
  const hasMore = $derived(jobListingStore.hasMore);
  const displayTotalJobs = $derived(jobListingStore.totalJobs);
  const displayTitle = $derived(jobListingStore.title);

  // virtualization parameters
  const FALLBACK_ITEM_HEIGHT = 420;
  const fallbackGap = 24;
  const buffer = 6;
  const currentHeightY = $derived(scrollY.current);
  const innerHeightValue = $derived(innerHeight.current);
  /**
   * Decide navigation behavior when a job card is clicked, either opening the overlay (desktop) or navigating to the job detail page (mobile). Also handles saving the current grid state before navigation to allow for proper restoration when coming back. The separation of mobile and desktop logic ensures an optimized experience for each device type while maintaining state consistency across navigations.
   */
  class NavigationController {
    /**
     * Handle job click on mobile
     */
    private static MobileJobClick(
      job: Parameters<typeof this.handleJobClick>[0],
    ): void {
      routeStateStore.MarkVisitedJob(job.slug ?? "", "featured");
      // navigate to SingleLowongan.svelte route
      const url = new URL(String(job.permalink), window.location.origin);
      jobGridManager.saveGridStates();
      void goto(url.pathname + url.search + url.hash);
    }
    public static handleJobClick(job: CardJob): void {
      if (!job.permalink) return;

      // NOTE: Overlay feature is for desktop only (window.innerWidth >= 768)
      // Mobile navigation goes to SingleLowongan.svelte route

      // Save card heights before navigating/opening overlay
      routeStateStore.saveCardHeights(routeStateStore.getCardHeights("jobGrid"), "jobGrid");

      if (!isMobile) {
        jobGridManager.saveGridStates(
          new URL(String(job.permalink), window.location.origin).pathname,
        ); // save state with the target path so it can be restored in sidepanel context

        useSidePanel.openSidePanel(job.slug ?? "", job, "featured", () => {
          useSidePanel.scrollToJobGridCard(job.slug ?? "", "featured");
        });
      } else {
        this.MobileJobClick(job);
      }
    }
  }

  class GridJobManager {
    // Load more cache for CLS-free loading, avoid dynamically append list cards to DOM which triggered unfair CLS assessment
    public nextPageLoadMoreCache = $state<CardJob[] | null>(null);
    public isPrefetchingLoadMore = $state(false);
    public isRefreshing = $state(false);
    public errorMessage = $state<string | null>(null);

    public async refreshGrid(): Promise<
      JobGridProps | SearchResponse | undefined
    > {
      try {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        return await jobListingStore.refreshJobGrid();
      } catch (err) {
        console.error("Failed to refresh job grid:", err);
      } finally {
        this.isRefreshing = false;
      }
    }

    /**
     * Refresh the job grid based on the current search context and filters
     * Refresh button initialized in the UI to allow users to manually refresh the grid when they want to see updated results without changing filters or search terms
     */
    public async loadMore(retries = 2): Promise<LoadMoreResponse> {
      if (
        jobListingStore.loading ||
        jobListingStore.page >= jobListingStore.maxNumPages!
      ) {
        throw new Error("Cannot load more: already loading or no more pages");
      }

      jobListingStore.loading = true;
      jobListingStore.error = null;
      try {
        const paged = jobListingStore.page + 1;
        const context = jobListingStore.context;
        const filters = SearchUtils.sanitizeFilters({
          ...jobListingStore.filters,
        });

        const loadMoreFilters = {
          paged,
          context,
          ...filters,
        };

        const response =
          await APIServiceBrowser.loadMoreJobsGraphQL(loadMoreFilters);

        if (Array.isArray(response.jobs) && response.jobs.length) {
          const newJobs = response.jobs.filter(
            (newJob) =>
              !jobListingStore.jobs.some(
                (existingJob) => existingJob.permalink === newJob.permalink,
              ),
          );
          jobListingStore.jobs.push(...newJobs);
          jobListingStore.page = paged;
          jobListingStore.maxNumPages =
            response.maxNumPages || jobListingStore.maxNumPages;
        } else {
          jobListingStore.page = jobListingStore.maxNumPages!;
        }

        return response;
      } catch (err) {
        console.error("SearchStore: Load more failed:", err);
        jobListingStore.error =
          err instanceof Error ? err.message : "Load more failed";

        if (retries > 0) {
          console.log(`Retrying loadMore, attempts left: ${retries}`);
          return this.loadMore(retries - 1);
        }
        this.errorMessage =
          "Terjadi kesalahan saat memuat lebih banyak lowongan";
        throw err;
      } finally {
        jobListingStore.loading = false;
      }
    }

    public appendCachedPage(): void {
      if (!this.nextPageLoadMoreCache) {
        return;
      }

      jobListingStore.jobs.push(...this.nextPageLoadMoreCache);
      jobListingStore.page++;
      this.nextPageLoadMoreCache = null;
    }

    public async prefetchNextPage(): Promise<void> {
      if (
        this.isPrefetchingLoadMore ||
        jobListingStore.page >= jobListingStore.maxNumPages! ||
        this.nextPageLoadMoreCache
      ) {
        return;
      }

      this.isPrefetchingLoadMore = true;
      jobListingStore.error = null;

      const paged = jobListingStore.page + 1;
      const context = jobListingStore.context;
      const filters = SearchUtils.sanitizeFilters({
        ...jobListingStore.filters,
      });

      const loadMoreFilters = {
        paged,
        context,
        ...filters,
      };

      const TIMEOUT_MS = 6000;

      try {
        // Use es-toolkit's withTimeout to avoid manual Promise.race and clearer semantics
        let response: LoadMoreResponse | undefined;

        try {
          response = await withTimeout(
            () => APIServiceBrowser.loadMoreJobsGraphQL(loadMoreFilters),
            TIMEOUT_MS,
          );
        } catch (err) {
          const isTimeout =
            (err as Error)?.name === "TimeoutError" ||
            String((err as Error)?.message ?? "")
              .toLowerCase()
              .includes("time");

          if (isTimeout) {
            console.warn(
              "SearchStore: Prefetch timed out, performing manual fetch",
            );
            response =
              await APIServiceBrowser.loadMoreJobsGraphQL(loadMoreFilters);
          } else {
            throw err;
          }
        }

        if (response?.jobs && response.jobs.length) {
          const newJobs = response.jobs.filter(
            (newJob: any) =>
              !jobListingStore.jobs.some(
                (existingJob) => existingJob.permalink === newJob.permalink,
              ),
          );
          this.nextPageLoadMoreCache = newJobs;
          jobListingStore.maxNumPages =
            response.maxNumPages || jobListingStore.maxNumPages;
        } else {
          jobListingStore.page = jobListingStore.maxNumPages!;
        }
      } catch (err) {
        console.error("SearchStore: Prefetch failed:", err);
        jobListingStore.error =
          err instanceof Error ? err.message : "Prefetch failed";
      } finally {
        this.isPrefetchingLoadMore = false;
      }
    }

    /**
     * Initialize restore and autosave for the current route's search state.
     */
    public init(): void {
      const routePath = window.location.pathname || "/";

      try {
        const saved = routeStateStore.getSearchState(routePath);
        if (saved) {
          if (Array.isArray(saved.jobs) && saved.jobs.length) {
            jobListingStore.jobs = [...saved.jobs];
          }
          if (saved.filters) jobListingStore.filters = saved.filters;
          if (typeof saved.page === "number") jobListingStore.page = saved.page;
          if (typeof saved.maxNumPages === "number")
            jobListingStore.maxNumPages = saved.maxNumPages;
          if (saved.title)
            jobListingStore.title = saved.title || jobListingStore.title;
          if (saved.context)
            jobListingStore.context = saved.context || jobListingStore.context;
          if (typeof saved.totalJobs === "number")
            jobListingStore.totalJobs = saved.totalJobs;
        }

        if (routeStore.isInitialLoad) {
          tick().then(() => {
            requestAnimationFrame(() =>
              useRIC(() => routeStateStore.restoreScrollPosition(routePath)),
            );

            setTimeout(() => {
              routeStateStore.clearScrollPosition(routePath);
            }, 1000);
          });
        }
      } catch (e) {
        console.warn("Failed to restore search state:", e);
      }
    }

    public saveGridStates(path?: string): void {
      const routePath = path || "/";
      try {
        const state: SearchState = {
          jobs: jobListingStore.jobs || [],
          filters: jobListingStore.filters || {},
          page: jobListingStore.page || 1,
          title: jobListingStore.title || "Lowongan Terbaru",
          context: jobListingStore.context || "latest",
          totalJobs: jobListingStore.totalJobs || 0,
          maxNumPages: jobListingStore.maxNumPages || 1,
        };
        routeStateStore.saveSearchState(routePath, state);

        // Save current scroll position
        routeStateStore.saveScrollPosition(routePath, scrollY.current || 0);
      } catch (e) {
        void e;
      }
    }
  }

  export const jobGridManager = new GridJobManager();
</script>

<script lang="ts">
  import type { Attachment } from "svelte/attachments";
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import SingleSidePanel from "@components/ui/Homepage/JobGrid/SingleSidePanel.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount, tick } from "svelte";
  import { useRIC } from "@/utils/window";

  const props: JobGridProps = $props();

  const {
    jobs = [],
    maxNumPages,
    context,
    filters,
    title,
    total: totalJobs,
  } = $derived<JobGridProps>(props);

  const virtualization = $derived(
    useVirtualization.computeList({
      displayJobs,
      scrollY: currentHeightY ?? 0,
      containerHeight: innerHeightValue ?? 800,
      cardHeights: routeStateStore.getCardHeights("jobGrid"),
      fallbackHeight: FALLBACK_ITEM_HEIGHT,
      gap: fallbackGap,
      buffer,
    }),
  );

  const observeLoadMoreSentinel: Attachment<HTMLElement> | undefined = (() => {
    if (!browser) return;
    let observerSentinel: IntersectionObserver;

    return (node: Element) => {
      observerSentinel ??= new IntersectionObserver(
        async (entries) => {
          for (const entry of entries) {
            if (
              entry.isIntersecting &&
              hasMore &&
              !jobGridManager.nextPageLoadMoreCache &&
              !jobGridManager.isPrefetchingLoadMore
            ) {
              await jobGridManager.prefetchNextPage();
            }
          }
        },
        { root: null, rootMargin: "5000px" },
      );

      if (observerSentinel instanceof IntersectionObserver)
        observerSentinel.observe(node);

      return () => {
        if (observerSentinel instanceof IntersectionObserver)
          observerSentinel.disconnect();
      };
    };
  })();

  /**
   * SSR initialization to populate searchStore with server-provided data on initial load. This ensures that the job grid is populated immediately with the correct data without waiting for client-side JS to fetch it, improving perceived performance and SEO. The check for routeStore.isInitialLoad ensures this only runs on the first load and not on client-side navigations where the state should be preserved/restored instead.
   */
  (() => {
    if (!jobs || jobs.length === 0 || !routeStore.isInitialLoad) return;
    jobListingStore.jobs = [...jobs];
    if (typeof maxNumPages === "number" && maxNumPages > 0) {
      jobListingStore.maxNumPages = maxNumPages;
    }
    if (context) jobListingStore.context = context;
    if (title) jobListingStore.title = title;
    if (totalJobs !== undefined) jobListingStore.totalJobs = totalJobs;
    if (filters) jobListingStore.setFilters(filters);
  })();

  onMount(() => {
    jobGridManager.init();
  });
</script>

<section
  class="relative mt-12"
  id="job-grid"
  style={!isMobile ? "view-transition-name: none;" : ""}
>
  <div class="flex items-center justify-between mb-6" style="content-visibility: auto;">
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
      onclick={() => void jobGridManager.refreshGrid()}
      disabled={jobGridManager.isRefreshing || loading}
      tabindex="0"
    >
      <RefreshSpinner size="h-5 w-5" spin={jobGridManager.isRefreshing} />
      <span class="sr-only"
        >{jobGridManager.isRefreshing ? "Sedang menyegarkan" : "Segarkan"}</span
      >
    </button>
  </div>

  {#if jobGridManager.isRefreshing}
    <div
      class="flex justify-center items-center min-h-[200px]"
      aria-live="polite"
    >
      <LoadingSpinner srLabel="Memuat grid..." size="md" />
    </div>
  {:else}
    {#if displayJobs.length && jobListingStore.context !== "latest"}
      <div class="text-base font-medium mb-4">
        {displayTotalJobs} lowongan ditemukan
      </div>
    {/if}

    <div class="relative flex items-stretch">
      <div class="w-full lg:w-[calc(100%-420px)]">
        {#if displayJobs.length}
          {#if browser}
            <div
              style="height: {virtualization.totalHeight}px; position: relative;"
            >
              {#each virtualization.visibleJobs as job, index}
                {@const absoluteTop =
                  virtualization.itemPositions[
                    virtualization.startIndex + index
                  ]}
                <div
                  style="position: absolute; transform: translate3d(0, {absoluteTop}px, 0); width: 100%; contain: layout;"
                  {@attach useVirtualization.createMeasureHeight(
                    routeStateStore.getCardHeights("jobGrid"),
                    job.id,
                  )}
                  class="transition-opacity duration-600 ease-in-out"
                  onkeydown={(event) => {
                    if (event.key === "Enter" || event.key === " ") {
                      event.preventDefault();
                      void NavigationController.handleJobClick(job);
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
                    onclick={() => {
                      void NavigationController.handleJobClick(job);
                    }}
                  />
                </div>
              {/each}
              <!-- load-more sentinel placed at the end of the virtualization spacer -->
              <div
                {@attach observeLoadMoreSentinel}
                style="position: absolute; left:0; width:1px; height:1px; transform: translate3d(0, {Math.max(
                  0,
                  (virtualization.totalHeight || 0) - 1,
                )}px, 0); pointer-events:none; opacity:0;"
                aria-hidden="true"
              ></div>
            </div>
          {:else}
            <!-- SSR rendering -->
            {#each displayJobs as job}
              <div class="relative w-full pt-4">
                <JobCard
                  jobdata={job}
                  variant="featured"
                  permalink={job.permalink ?? ""}
                  onclick={() => {
                    void NavigationController.handleJobClick(job);
                  }}
                />
              </div>
            {/each}
          {/if}
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
              onclick={() => jobGridManager.appendCachedPage()}
              disabled={!jobGridManager.nextPageLoadMoreCache}
            >
              {jobGridManager.nextPageLoadMoreCache
                ? "Muat Lebih Banyak"
                : (jobGridManager.errorMessage ?? "Mempersiapkan...")}
            </button>
          </div>
        {/if}

        {#if !hasMore && displayJobs.length > 0}
          <div class="flex justify-center mt-8 z-100">
            <p
              class="bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] rounded-lg"
            >
              Tidak ada sisa muatan lagi
            </p>
          </div>
        {/if}
      </div>

      {#if !isMobile}
        <div
          class="sticky self-start w-full"
          style:top="var(--site-header-height, 0px)"
        >
          <SingleSidePanel />
        </div>
      {/if}
    </div>
  {/if}
</section>
