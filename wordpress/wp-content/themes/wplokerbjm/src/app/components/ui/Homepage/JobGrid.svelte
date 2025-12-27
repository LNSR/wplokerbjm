<script module lang="ts">
  import type { CardJob, JobGridProps, SearchState } from "@/types";
  import { SearchContext, SearchTitle } from "@/types";
  import type { SearchManager } from "$lib/stores/Search.svelte";
  import { routeStateStore, navigateTo } from "$lib/stores/Route.svelte";
  import { GoogleServices } from "$lib/utils/Google.svelte";
  import {
    type JobOverlayManager,
    jobOverlay,
  } from "$lib/stores/JobOverlay.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";

  let skipScrollRestore = $state(false);

  const displayJobs = $derived(searchStore.jobs);
  const loading = $derived(searchStore.loading);
  const hasMore = $derived(searchStore.hasMore);
  const overlayOpen = $derived(jobOverlay.overlayOpen);
  const selectedSlug = $derived(jobOverlay.selectedSlug);
  const displayTotalJobs = $derived(searchStore.totalJobs);
  const displayTitle = $derived(searchStore.title);
  const isDesktop = $derived.by(() => !isMobile());

  class OverlayController {
    public searchStore: SearchManager;
    public jobOverlay: JobOverlayManager;

    constructor(searchStore: SearchManager, jobOverlay: JobOverlayManager) {
      this.searchStore = searchStore;
      this.jobOverlay = jobOverlay;
    }

    async openOverlay(slug: string): Promise<void> {
      const job = this.searchStore.jobs.find((j: any) => j.slug === slug);
      await this.jobOverlay.openOverlay(slug, job);
    }

    async handleOverlayClose(): Promise<void> {
      this.jobOverlay.closeOverlay();
    }

    async handleJobClick(job: CardJob): Promise<void> {
      if (!job.permalink) return;

      // NOTE: Overlay feature is for desktop only (window.innerWidth >= 768)
      // Mobile navigation goes to SingleLowongan.svelte route

      // Save current search state before navigation (for both mobile and desktop)
      const currentSearchState = {
        jobs: this.searchStore.jobs,
        context: this.searchStore.context,
        title: this.searchStore.title,
        totalJobs: this.searchStore.totalJobs,
        maxNumPages: this.searchStore.maxNumPages,
        page: this.searchStore.page,
        filters: { ...this.searchStore.filters },
        loading: this.searchStore.loading,
        error: this.searchStore.error,
      };

      // Save current search state before navigation (implicitly always save for homepage('/') path)
      routeStateStore.saveSearchState(
        window.location.pathname,
        currentSearchState
      );

      async function MobileJobClick(): Promise<void> {
        // Mark as last visited before navigating
        routeStateStore.MarkVisitedJob(job.slug ?? "");
        // use SPA navigation to SingleLowongan.svelte route
        const url = new URL(String(job.permalink), window.location.origin);
        return navigateTo(
          url.pathname + url.search + url.hash,
          currentSearchState
        );
      }

      if (isDesktop) {
        await this.openOverlay(job.slug ?? "");
        // After opening overlay on desktop, ensure the clicked card is scrolled into view
        this.scrollToCard(job.slug ?? "");
      } else {
        await MobileJobClick();
      }
    }

    /**
     *  Check URL for overlay slug on mount and
     *  handle opening overlay if present/scroll skipping.
     */
    checkUrlForOverlay() {
      if (typeof window !== "undefined") {
        const path = window.location.pathname;
        const match = path.match(/\/lowongan\/([^/]+)\/?$/);
        if (match && match[1] && isDesktop) {
          const slug = match[1];
          overlayManager.openOverlay(slug);
          skipScrollRestore = true;
        }
      }
    }

    scrollToCard(slug: string, delay = 350, buffer = 12): void {
      try {
        this.jobOverlay.scrollToCard(slug, delay, buffer);
      } catch (err) {
        // Fallback: attempt a best-effort local scroll to keep behavior
        setTimeout(() => {
          try {
            const selector = `div[data-job-slug="${String(slug)}"]`;
            const cardElement = document.querySelector(
              selector
            ) as HTMLElement | null;
            if (cardElement) {
              cardElement.scrollIntoView({
                behavior: "smooth",
                block: "start",
                inline: "nearest",
              });
            }
          } catch (e) {
            // swallow
          }
        }, delay);
      }
    }
  }

  export const overlayManager = new OverlayController(searchStore, jobOverlay);
</script>

<script lang="ts">
  import { onMount, untrack, tick } from "svelte";
  import { headerStore } from "$lib/stores/HeaderStore.svelte";
  import { WPThemeDataStore } from "$lib/stores/WPThemeData";
  import { searchStore } from "$lib/stores/Search.svelte";
  import { APIService } from "@/services/APIService";
  import JobCard from "@components/ui/Homepage/JobCard.svelte";
  import SingleOverlay from "@components/ui/Homepage/SingleOverlay.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import Adsense from "@components/ui/Shared/Adsense.svelte";

  let sentinel = $state<HTMLDivElement | null>(null);
  let observer: IntersectionObserver | null = null;
  let initialLoading = $state(false);
  let hasRestoredState = $state(false);
  let isRefreshing = $state(false);

  let {
    jobs = [],
    maxNumPages,
    context,
    filters,
    title,
    totalJobs,
  }: JobGridProps = $props();

  // Observe for infinite scrolling
  function createObserver(): void {
    if (observer) observer.disconnect();
    observer = new IntersectionObserver(
      (entries) => {
        const isIntersecting = entries[0]?.isIntersecting;
        const shouldLoadMore = isIntersecting && hasMore && !loading;

        if (shouldLoadMore) {
          untrack(() => searchStore.loadMore());
        }
      },
      { root: null, rootMargin: "100px", threshold: 0 }
    );
    if (sentinel) observer.observe(sentinel);
  }

  class JobGridHandler {
    private async fetchJobGrid(): Promise<JobGridProps> {
      try {
        const response = await APIService.fetchJobGrid({
          paged: 1,
          context: context || SearchContext.Latest,
          title: title || "",
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
      savedSearchState: SearchState | undefined
    ): boolean | undefined {
      let lastJobUpdateMs = 0;
      const themeData = WPThemeDataStore.getThemeData();
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
    private async restoreState(savedSearchState: SearchState): Promise<void> {
      if (!savedSearchState) return;
      const uniqueJobs = savedSearchState.jobs.filter(
        (job, index, self) =>
          index === self.findIndex((j) => j.permalink === job.permalink)
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

      // Scroll restoration requires DOM to be fully settled. tick() ensures Svelte reactivity updates,
      // but layout may still be settling, hence the double RAF and timeout.
      // Avoid triggering job refreshes during this period to prevent jeopardizing saved scroll positions.
      await tick();
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          setTimeout(() => {
            if (!skipScrollRestore) {
              const savedScroll = routeStateStore.getScrollPosition(
                window.location.pathname
              );
              if (savedScroll !== undefined) {
                window.scrollTo({ top: savedScroll, behavior: "smooth" });
              }
            }
          }, 500);
        });
      });
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
          searchStore.title = (gridData.title as SearchTitle) || SearchTitle.Latest;
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
        searchStore.jobs = [...jobs];
        if (maxNumPages) searchStore.maxNumPages = maxNumPages;
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
          "JobGrid: Already restored state for this component instance, skipping"
        );
        return;
      }

      // Check if there's a saved search state for this path
      const savedSearchState = routeStateStore.getSearchState(
        window.location.pathname
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
          const response = await APIService.fetchJobGrid({
            paged: 1,
            context: searchStore.context,
            title: searchStore.title,
            total_jobs: 0,
            ...searchStore.filters,
          });
          searchStore.jobs = response.jobs || [];
          searchStore.maxNumPages = response.maxNumPages || 1;
          searchStore.context = response.context || SearchContext.Latest;
          searchStore.title = (response.title as SearchTitle) || SearchTitle.Latest;
          searchStore.totalJobs = response.totalJobs || 0;
          if (response.filters) {
            searchStore.setFilters(response.filters);
          }
          searchStore.page = 1; // Reset page
          searchStore.error = null;
          // Reset observer after DOM update to ensure load more works
          setTimeout(() => createObserver(), 0);
        } else {
          // In search mode, re-run search
          await searchStore.searchJobs();
          // Reset observer after DOM update
          setTimeout(() => createObserver(), 0);
        }
        // If overlay was open, close it properly
        if (overlayOpen) {
          jobOverlay.closeIfOpen();
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

  onMount(() => {
    overlayManager.checkUrlForOverlay();
    jobGridHandler.initializeJobs();
    createObserver();
    const cleanupPopstate = jobOverlay.setupPopstateListener();

    return () => {
      if (observer) observer.disconnect();
      cleanupPopstate();
    };
  });

  // Watch for jobs changes and close overlay
  $effect(() => {
    searchStore.jobs;

    // Close overlay when jobs change (untrack to avoid circular dependencies)
    untrack(() => {
      jobOverlay.closeIfOpen();
    });
  });

  // Manage AdSense ads on overlay toggle
  $effect(() => {
    overlayOpen
      ? GoogleServices.adSenseDestroy()
      : GoogleServices.adSenseRefresh();
  });

  // When an overlay opens (including from URL) ensure the selected card scrolls into view
  $effect(() => {
    if (overlayOpen && selectedSlug) {
      setTimeout(() => {
        overlayManager.scrollToCard(selectedSlug);
      }, 300);
    }
  });
</script>

<section class="relative mt-8" id="job-grid">
  <Adsense adSlot="8930604465" />
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
      onclick={jobGridHandler.refreshJobGrid}
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
      <div
        class={[
          "transition-all duration-600 ease-in-out",
          overlayOpen
            ? "w-full lg:w-[calc(100%-420px)] will-change-[width]"
            : "w-full",
        ].join(" ")}
      >
        {#if displayJobs.length}
          <div
            class={[
              "grid gap-6",
              overlayOpen
                ? "grid-cols-1 md:grid-cols-1 lg:grid-cols-1"
                : "grid-cols-1 md:grid-cols-2 lg:grid-cols-3",
            ].join(" ")}
          >
            {#each displayJobs as job (job.permalink)}
              {@const isSelected = jobOverlay.selectedSlug === job.slug}
              <div
                class={`transition-opacity duration-600 ease-in-out ${isSelected ? "will-change-[opacity]" : ""}`}
                onkeydown={(event) => {
                  if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    overlayManager.handleJobClick(job);
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
                  onClick={() => overlayManager.handleJobClick(job)}
                />
              </div>
            {/each}
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
          <div class="flex justify-center mt-8">
            <button
              type="button"
              class="btn rounded-lg font-semibold bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] border border-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] hover:text-[var(--wpl-global-color-5)]"
              onclick={() => searchStore.loadMore()}
              disabled={loading}
            >
              {loading ? "Memuat..." : "Muat Lebih Banyak"}
            </button>
          </div>
        {/if}

        {#if loading}
          <div class="flex justify-center mt-8">
            <LoadingSpinner srLabel="Memuat lowongan..." size="md" />
          </div>
        {/if}

        <div bind:this={sentinel} style="height: 20px"></div>
      </div>

      {#if overlayOpen && selectedSlug}
        <div
          class={[
            "hidden md:block w-full",
            overlayOpen ? "sticky self-start" : "relative",
          ].join(" ")}
          style:top={overlayOpen ? headerStore.totalOffset + "px" : undefined}
        >
          <SingleOverlay
            visible={overlayOpen}
            close={() => overlayManager.handleOverlayClose()}
          />
        </div>
      {/if}
    </div>
  {/if}
</section>
