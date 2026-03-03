<script module lang="ts">
  import type { CardJob, JobGridProps, SearchState } from "@/types";
  import { SearchContext, SearchTitle } from "@/types";
  import {
    routeStore,
    routeStateStore,
    GlobalNavigateTo,
  } from "$lib/stores/Route.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  import { Virtualization } from "$lib/utils/Virtualization.svelte";
  import { scrollY, innerHeight } from "svelte/reactivity/window";
  import { browser } from "$app/environment";

  const displayJobs = $derived(searchStore.jobs);
  const loading = $derived(searchStore.loading);
  const hasMore = $derived(searchStore.hasMore);
  const isDesktop = $derived.by(() => !isMobile());
  const displayTotalJobs = $derived(searchStore.totalJobs);
  const displayTitle = $derived(searchStore.title);
  const cardHeights = new SvelteMap(routeStateStore.getCardHeights("jobGrid"));

  let isRefreshing = $state(false);
  let loadMoreSentinel = $state<HTMLElement | null>(null);
  let prevFilters = $state(""); // to track filter changes so card heights can be cleared

  class OverlayController {
    async handleJobClick(job: CardJob): Promise<void> {
      if (!job.permalink) return;

      // NOTE: Overlay feature is for desktop only (window.innerWidth >= 768)
      // Mobile navigation goes to SingleLowongan.svelte route

      // Save card heights before navigating/opening overlay
      routeStateStore.saveCardHeights(cardHeights, "jobGrid");

      async function MobileJobClick(): Promise<void> {
        // Mark as last visited before navigating
        routeStateStore.MarkVisitedJob(job.slug ?? "", "grid");
        // use SPA navigation to SingleLowongan.svelte route
        const url = new URL(String(job.permalink), window.location.origin);
        jobGridManager.saveGridStates();
        return void GlobalNavigateTo(url.pathname + url.search + url.hash);
      }

      if (isDesktop) {
        jobGridManager.saveGridStates(
          new URL(String(job.permalink), window.location.origin).pathname,
        ); // save state with the target path so it can be restored in sidepanel context

        jobOverlay.openOverlay(job.slug ?? "", job, "grid");
        this.scrollToCard(job.slug ?? "");
      } else {
        await MobileJobClick();
      }
    }

    scrollToCard(slug: string): void {
      jobOverlay.scrollToCard(slug, 300, false, "grid");
    }
  }

  class GridJobManager {
    #disposeSave: (() => void) | undefined;

    /**
     * Refresh the job grid based on the current search context and filters
     * Refresh button initialized in the UI to allow users to manually refresh the grid when they want to see updated results without changing filters or search terms
     */
    async refreshJobGrid() {
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
          searchStore.maxNumPages = response.maxNumPages || 1;
          searchStore.context = response.context || SearchContext.Latest;
          searchStore.title =
            (response.title as SearchTitle) || SearchTitle.Latest;
          searchStore.totalJobs = response.totalJobs || 0;
          if (response.filters) {
            searchStore.setFilters(response.filters);
          }
          searchStore.page = 1;
          searchStore.error = null;
        } else {
          await searchStore.searchJobs();
        }
      } catch (err) {
        console.error("Failed to refresh job grid:", err);
        searchStore.error = "Failed to refresh job grid";
      } finally {
        isRefreshing = false;
      }
    }
    /**
     * Initialize restore and autosave for the current route's search state.
     */
    init(): void {
      const routePath = window.location.pathname || "/";

      try {
        const saved = routeStateStore.getSearchState(routePath) as
          | SearchState
          | undefined;
        if (saved) {
          if (Array.isArray(saved.jobs) && saved.jobs.length) {
            searchStore.jobs = [...saved.jobs];
          }
          if (saved.filters) searchStore.filters = saved.filters as any;
          if (typeof saved.page === "number") searchStore.page = saved.page;
          if (typeof saved.maxNumPages === "number")
            searchStore.maxNumPages = saved.maxNumPages;
          if (saved.title)
            searchStore.title =
              (saved.title as SearchTitle) || searchStore.title;
          if (saved.context)
            searchStore.context =
              (saved.context as SearchContext) || searchStore.context;
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
        const state: Partial<SearchState> = {
          jobs: searchStore.jobs || [],
          filters: searchStore.filters || {},
          page: searchStore.page || 1,
          title: searchStore.title || SearchTitle.Latest,
          context: searchStore.context || SearchContext.Latest,
          totalJobs: searchStore.totalJobs || 0,
        };
        routeStateStore.saveSearchState(routePath, state as SearchState);

        // Save current scroll position
        routeStateStore.saveScrollPosition(routePath, window.scrollY);
      } catch (e) {
        void e;
      }
    }
  }

  export const overlayManager = new OverlayController();
  export const jobGridManager = new GridJobManager();
</script>

<script lang="ts">
  import { SvelteMap } from "svelte/reactivity";
  import type { Attachment } from "svelte/attachments";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";
  import { searchStore } from "$lib/stores/Search.svelte";
  import { APIService } from "@/services/APIService";
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import SingleOverlay from "@components/ui/Homepage/SingleOverlay.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount, tick } from "svelte";

  const props: JobGridProps = $props();

  const {
    jobs = [],
    maxNumPages,
    context,
    filters,
    title,
    totalJobs,
  } = (() => props)() as JobGridProps;
  // Prime from SSR
  if (jobs && jobs.length && routeStore.isInitialLoad) {
    searchStore.jobs = [...jobs];
    if (typeof maxNumPages === "number" && maxNumPages > 0) {
      searchStore.maxNumPages = maxNumPages;
    }
    if (context) searchStore.context = context;
    if (title) searchStore.title = title as SearchTitle;
    if (totalJobs !== undefined) searchStore.totalJobs = totalJobs;
    if (filters) searchStore.setFilters(filters);
  }

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

  // Clear virtualization measurements when filters change to avoid layout glitches
  $effect(() => {
    const current = JSON.stringify(searchStore.filters || {});
    if (!prevFilters) {
      prevFilters = current;
      return;
    }
    if (current !== prevFilters) {
      prevFilters = current;
      try {
        // clear in-memory map used by virtualization
        if (cardHeights && typeof cardHeights.clear === "function") {
          cardHeights.clear();
        }
        // persist empty heights so other components/tabs use fresh measurements
        routeStateStore.saveCardHeights(new SvelteMap(), "jobGrid");
      } catch (e) {
        void e;
      }
    }
  });

  onMount(() => {
    jobGridManager.init();
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
      onclick={() => void jobGridManager.refreshJobGrid()}
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
                    isVisited={routeStateStore.hasVisitedJob(
                      job.slug ?? "",
                      "grid",
                    )}
                    onClick={() => {
                      routeStore.setIsInitialLoad(false);
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
          {:else}
            <!-- SSR rendering: show up to first 27 jobs -->
            {#each displayJobs.slice(0, 27) as job}
              <div class="relative w-full pt-4">
                <JobCard
                  jobdata={job}
                  variant="featured"
                  permalink={job.permalink ?? ""}
                  isVisited={routeStateStore.hasVisitedJob(
                    job.slug ?? "",
                    "grid",
                  )}
                  onClick={() => {
                    routeStore.setIsInitialLoad(false);
                    void overlayManager.handleJobClick(job);
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
            <p
              class="bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] rounded-lg"
            >
              Tidak ada sisa muatan lagi
            </p>
          </div>
        {/if}
      </div>

      {#if isDesktop}
        <div
          class="sticky self-start w-full"
          style:top={headerStore.headerHeight + "px"}
        >
          <SingleOverlay visible={true} />
        </div>
      {/if}
    </div>
  {/if}
</section>
