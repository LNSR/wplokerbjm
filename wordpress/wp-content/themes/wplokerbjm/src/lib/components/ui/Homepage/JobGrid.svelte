<script module lang="ts">
  import type {
    CardJob,
    JobGridProps,
    LoadMoreResponse,
    SearchResponse,
    SearchState,
  } from "@/types";
  import { goto } from "$app/navigation";
  import { routeStore, routeStateStore } from "$lib/stores/Route.svelte";
  import { jobOverlayManager } from "$lib/stores/JobOverlay.svelte";
  import { searchStore } from "$lib/stores/Search.svelte";
  import { APIServiceBrowser } from "@/services/graphql/APIService";
  import { SearchUtils } from "@/utils/search";
  import { isMobile } from "$lib/utils/window.svelte";
  import { useVirtualization } from "@/lib/utils/virtualization.svelte";
  import { scrollY, innerHeight } from "svelte/reactivity/window";
  import { browser } from "$app/environment";

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
      routeStateStore.saveCardHeights(searchStore.jobGridCardHeight, "jobGrid");

      if (!isMobile()) {
        jobGridManager.saveGridStates(
          new URL(String(job.permalink), window.location.origin).pathname,
        ); // save state with the target path so it can be restored in sidepanel context

        jobOverlayManager?.openOverlay(job.slug ?? "", job, "featured", {
          gotoCB() {
            jobOverlayManager?.scrollToCard(job.slug ?? "", true, "featured");
          },
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

    public async refreshGrid(): Promise<
      JobGridProps | SearchResponse | undefined
    > {
      try {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        return await searchStore.refreshJobGrid();
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
      if (searchStore.loading || searchStore.page >= searchStore.maxNumPages!) {
        throw new Error("Cannot load more: already loading or no more pages");
      }

      searchStore.loading = true;
      searchStore.error = null;
      try {
        const paged = searchStore.page + 1;
        const context = searchStore.context;
        const filters = SearchUtils.sanitizeFilters({ ...searchStore.filters });

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
              !searchStore.jobs.some(
                (existingJob) => existingJob.permalink === newJob.permalink,
              ),
          );
          searchStore.jobs.push(...newJobs);
          searchStore.page = paged;
          searchStore.maxNumPages =
            response.maxNumPages || searchStore.maxNumPages;
        } else {
          searchStore.page = searchStore.maxNumPages!;
        }

        return response;
      } catch (err) {
        console.error("SearchStore: Load more failed:", err);
        searchStore.error =
          err instanceof Error ? err.message : "Load more failed";

        if (retries > 0) {
          console.log(`Retrying loadMore, attempts left: ${retries}`);
          return this.loadMore(retries - 1);
        }

        throw err;
      } finally {
        searchStore.loading = false;
      }
    }

    public appendCachedPage(): void {
      if (!this.nextPageLoadMoreCache) {
        return;
      }

      searchStore.jobs.push(...this.nextPageLoadMoreCache);
      searchStore.page++;
      this.nextPageLoadMoreCache = null;
    }

    public async prefetchNextPage(): Promise<void> {
      if (
        this.isPrefetchingLoadMore ||
        searchStore.page >= searchStore.maxNumPages! ||
        this.nextPageLoadMoreCache
      ) {
        return;
      }

      this.isPrefetchingLoadMore = true;
      searchStore.error = null;

      const paged = searchStore.page + 1;
      const context = searchStore.context;
      const filters = SearchUtils.sanitizeFilters({ ...searchStore.filters });

      const loadMoreFilters = {
        paged,
        context,
        ...filters,
      };

      const TIMEOUT_MS = 6000;

      try {
        const apiPromise =
          APIServiceBrowser.loadMoreJobsGraphQL(loadMoreFilters);
        const timeoutPromise = new Promise<{ __timedOut: true }>((resolve) =>
          setTimeout(() => {
            resolve({ __timedOut: true });
          }, TIMEOUT_MS),
        );

        const raceResult = (await Promise.race([
          apiPromise,
          timeoutPromise,
        ])) as LoadMoreResponse | { __timedOut: true };

        let response: LoadMoreResponse | undefined;

        if ("__timedOut" in raceResult) {
          console.warn(
            "SearchStore: Prefetch timed out, performing manual fetch",
          );
          response = await APIServiceBrowser.loadMoreJobsGraphQL(loadMoreFilters);
        } else {
          response = raceResult;
        }

        if (response?.jobs && response.jobs.length) {
          const newJobs = response.jobs.filter(
            (newJob: any) =>
              !searchStore.jobs.some(
                (existingJob) => existingJob.permalink === newJob.permalink,
              ),
          );
          this.nextPageLoadMoreCache = newJobs;
          searchStore.maxNumPages = response.maxNumPages || searchStore.maxNumPages;
        } else {
          searchStore.page = searchStore.maxNumPages!;
        }
      } catch (err) {
        console.error("SearchStore: Prefetch failed:", err);
        searchStore.error =
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
            searchStore.jobs = [...saved.jobs];
          }
          if (saved.filters) searchStore.filters = saved.filters;
          if (typeof saved.page === "number") searchStore.page = saved.page;
          if (typeof saved.maxNumPages === "number")
            searchStore.maxNumPages = saved.maxNumPages;
          if (saved.title) searchStore.title = saved.title || searchStore.title;
          if (saved.context)
            searchStore.context = saved.context || searchStore.context;
          if (typeof saved.totalJobs === "number")
            searchStore.totalJobs = saved.totalJobs;
        }

        if (routeStore.isInitialLoad) {
          tick().then(() => {
            requestAnimationFrame(() =>
              requestAnimationFrame(() =>
                routeStateStore.restoreScrollPosition(routePath),
              ),
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

    saveGridStates(path?: string): void {
      const routePath = path || "/";
      try {
        const state: SearchState = {
          jobs: searchStore.jobs || [],
          filters: searchStore.filters || {},
          page: searchStore.page || 1,
          title: searchStore.title || "Lowongan Terbaru",
          context: searchStore.context || "latest",
          totalJobs: searchStore.totalJobs || 0,
          maxNumPages: searchStore.maxNumPages || 1,
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
  import SingleOverlay from "@components/ui/Homepage/SingleOverlay.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount, tick } from "svelte";

  const props: JobGridProps = $props();

  const displayJobs = $derived(searchStore.jobs);
  const loading = $derived(searchStore.loading);
  const hasMore = $derived(searchStore.hasMore);
  const displayTotalJobs = $derived(searchStore.totalJobs);
  const displayTitle = $derived(searchStore.title);

  // virtualization parameters
  const FALLBACK_ITEM_HEIGHT = 420;
  const fallbackGap = 32;
  const buffer = 6;
  const currentHeightY = $derived(scrollY.current);
  const innerHeightValue = $derived(innerHeight.current);
  const cardHeightsJobCard = $derived(searchStore.jobGridCardHeight);

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
      cardHeights: cardHeightsJobCard,
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
    searchStore.jobs = [...jobs];
    if (typeof maxNumPages === "number" && maxNumPages > 0) {
      searchStore.maxNumPages = maxNumPages;
    }
    if (context) searchStore.context = context;
    if (title) searchStore.title = title;
    if (totalJobs !== undefined) searchStore.totalJobs = totalJobs;
    if (filters) searchStore.setFilters(filters);
  })();

  onMount(() => {
    jobGridManager.init();
  });
</script>

<section
  class="relative mt-12"
  id="job-grid"
  style={!isMobile() ? "view-transition-name: none;" : ""}
>
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
      onclick={() => void jobGridManager.refreshGrid()}
      disabled={jobGridManager.isRefreshing || loading}
      tabindex="0"
    >
      <RefreshSpinner size="h-5 w-5" spin={jobGridManager.isRefreshing} />
      <span class="sr-only"
        >{jobGridManager.isRefreshing
          ? "Sedang menyegarkan"
          : "Segarkan"}</span
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
    {#if displayJobs.length && searchStore.context !== "latest"}
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
              {#each virtualization.visibleJobs as job, index (job.permalink)}
                {@const absoluteTop =
                  virtualization.itemPositions[
                    virtualization.startIndex + index
                  ]}
                <div
                  style="position: absolute; transform: translate3d(0, {absoluteTop}px, 0); width: 100%; contain: layout;"
                  {@attach useVirtualization.createMeasureHeight(
                    cardHeightsJobCard,
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
                    onClick={() => {
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
                )}px, 0); pointer-events:none; opacity:0; contain: layout size;"
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
                  onClick={() => {
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
                : "Mempersiapkan..."}
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

      {#if !isMobile()}
        <div
          class="sticky self-start w-full"
          style:top="var(--site-header-height, 0px)"
        >
          <SingleOverlay />
        </div>
      {/if}
    </div>
  {/if}
</section>
