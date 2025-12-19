<script lang="ts">
  import JobCard from "@components/ui/Homepage/JobCard.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { navigateTo, routeStateStore } from "$lib/stores/Route.svelte";
  import type { CardJob } from "@/types";
  import { APIService } from "@/services/APIService";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount, onDestroy, tick } from "svelte";
  import SwiperCore, { type Swiper } from "swiper";
  import { Navigation, Pagination, Autoplay } from "swiper/modules";
  import {
    ChevronCircleLeftSolid,
    ChevronCircleRightSolid,
  } from "svelte-awesome-icons";
  import "swiper/css";
  import "swiper/css/navigation";
  import "swiper/css/pagination";

  let { jobs: propJobs = [], title = "Lowongan Darurat" } = $props<{
    jobs?: CardJob[];
    title?: string;
  }>();

  let carouselData = $state<{ jobs: CardJob[]; totalJobs?: number } | null>(
    null
  );
  let isLoading = $state(false);
  let error = $state<string | null>(null);
  let isRefreshing = $state(false);

  let jobs = $derived(
    propJobs.length > 0 ? propJobs : (carouselData?.jobs ?? [])
  );

  let swiperInstance: Swiper | null = null;
  let swiperContainerEl = $state<HTMLElement | null>(null);
  let nextButtonEl = $state<HTMLElement | null>(null);
  let prevButtonEl = $state<HTMLElement | null>(null);

  let isInitializing = $state(false);
  let swiperFailed = $state(false);

  class SwiperManager {
    private static createSwiperConfig(
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null
    ) {
      return {
        loop: false,
        slidesPerView: 1.1,
        spaceBetween: 16,
        autoplay: {
          delay: 5000,
          disableOnInteraction: false,
        },
        pagination: paginationEl
          ? { el: paginationEl, clickable: true }
          : { clickable: true },
        navigation: {
          nextEl: nextEl ?? undefined,
          prevEl: prevEl ?? undefined,
        },
        breakpoints: {
          640: {
            slidesPerView: 2,
            spaceBetween: 24,
          },
          1024: {
            slidesPerView: 4,
            spaceBetween: 32,
          },
        },
      };
    }

    private static async waitForSlidesAndWidth(
      el: HTMLElement | null
    ): Promise<boolean> {
      if (!el) return false;
      const MAX_INIT_ATTEMPTS = 12;
      let attempts = 0;
      while (true) {
        await tick();
        const slidesCount = el.querySelectorAll(".swiper-slide").length;
        const width = el.getBoundingClientRect().width;
        if (slidesCount > 0 && width > 0) return true;
        attempts += 1;
        if (attempts >= MAX_INIT_ATTEMPTS) {
          try {
            el.classList.remove("invisible");
          } catch (e) {
            // ignore
          }
          swiperFailed = !!(
            el &&
            el.querySelectorAll &&
            el.querySelectorAll(".swiper-slide").length > 0
          );
          return false;
        }

        await new Promise((res) => setTimeout(res, 80));
      }
    }

    private static async initializeSwiperInstance(
      el: HTMLElement,
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null
    ): Promise<void> {
      try {
        try {
          if (SwiperCore && (SwiperCore as typeof SwiperCore).use) {
            SwiperCore.use([Navigation, Pagination, Autoplay]);
          }
        } catch (e) {}

        const cfg = SwiperManager.createSwiperConfig(
          paginationEl,
          nextEl,
          prevEl
        );
        const finalCfg = {
          ...(cfg || {}),
          modules: [Navigation, Pagination, Autoplay],
        };

        swiperInstance = new SwiperCore(el, finalCfg);

        // Restore saved slide index
        const savedState = routeStateStore.getCarouselState();
        // Clear saved state after restoring
        routeStateStore.clearCarouselState();
        if (savedState) {
          swiperInstance.slideTo(savedState.slideIndex);
        }

        return;
      } catch (err) {
        console.error("Failed to initialize Swiper:", err);
        throw err;
      }
    }

    static async createSwiper(): Promise<void> {
      const el = swiperContainerEl as HTMLElement | null;
      if (!el) return;

      if (isInitializing) return;
      isInitializing = true;
      swiperFailed = false;

      await tick();

      try {
        el.classList.remove("invisible");
      } catch (e) {}

      const paginationEl = el.querySelector(
        ".swiper-pagination"
      ) as HTMLElement | null;

      const nextEl = (nextButtonEl ??
        el.parentElement?.querySelector(
          ".job-carousel-next"
        )) as HTMLElement | null;
      const prevEl = (prevButtonEl ??
        el.parentElement?.querySelector(
          ".job-carousel-prev"
        )) as HTMLElement | null;

      // Wait until there are slides and the container has width
      const ready = await SwiperManager.waitForSlidesAndWidth(el);
      if (!ready) {
        isInitializing = false;
        return;
      }

      if (swiperInstance) {
        try {
          swiperInstance.destroy(true, true);
        } catch (e) {
          // ignore
        }
        swiperInstance = null;
      }

      try {
        await SwiperManager.initializeSwiperInstance(
          el,
          paginationEl,
          nextEl,
          prevEl
        );

        swiperFailed = false;
        try {
          el.classList.remove("no-swiper");
        } catch (e) {}
      } catch (err) {
        console.error("Failed to initialize Swiper:", err);
        console.warn(
          "Carousel will display as static list due to Swiper failure"
        );
        swiperFailed = true;
        try {
          el.classList.add("no-swiper");
        } catch (e) {}
      } finally {
        try {
          el.classList.remove("invisible");
        } catch (e) {}
        isInitializing = false;
      }
    }

    // Destroy and recreate the swiper instance (used by refresh button)
    static async reinitializeSwiper({ forceDestroy = false } = {}) {
      // If we already have an instance, optionally destroy it first to ensure a
      // full re-measure on re-init.
      if (swiperInstance) {
        try {
          swiperInstance.destroy(forceDestroy, true);
        } catch (err) {
          // ignore
        }
        swiperInstance = null;
      }

      // Wait a tick to ensure DOM has a chance to render the carousel element.
      // During a "refresh" the carousel DOM may be hidden (isRefreshing=true),
      // so we need to wait until it is present and has layout before creating
      // the Swiper instance.
      const MAX_DOM_ATTEMPTS = 12;
      let attempts = 0;
      while (
        (!swiperContainerEl ||
          !(swiperContainerEl as HTMLElement).isConnected ||
          (swiperContainerEl as HTMLElement).getBoundingClientRect().width ===
            0) &&
        attempts < MAX_DOM_ATTEMPTS
      ) {
        // Give the renderer some time to mount/unmount nodes.
        await tick();
        await new Promise((res) => setTimeout(res, 80));
        attempts += 1;
      }

      const el = swiperContainerEl as HTMLElement | null;
      if (!el) {
        try {
          const possibleNode = document.querySelector(
            ".job-carousel"
          ) as HTMLElement | null;
          if (possibleNode) possibleNode.classList.remove("invisible");
        } catch (e) {
          // ignore
        }
        return;
      }

      // Wait a final tick to ensure slides/content have rendered before init.
      await tick();

      // Recreate the instance (this function lazy-loads Swiper JS)
      await SwiperManager.createSwiper();

      // After attempting to initialize, ensure the carousel is visible even if
      // initialization failed. createSwiper removes "invisible" on success/failure
      // but in case it returned early elsewhere ensure we remove it here.
      try {
        el.classList.remove("invisible");
      } catch (e) {
        // ignore
      }
    }

    // Refresh handler: fetch fresh data and reinitialize the carousel
    static async refreshCarousel() {
      if (isRefreshing) return;
      isRefreshing = true;
      try {
        // Stop and remove any existing instance so we re-create from scratch
        if (swiperInstance) {
          try {
            swiperInstance.destroy(true, true);
          } catch (err) {
            // ignore
          }
          swiperInstance = null;
        }
        // Fetch fresh carousel data
        const data = await APIService.fetchCarousel();
        carouselData = data ?? null;
        error = null;
      } catch (err) {
        console.error("Failed to refresh carousel:", err);
        error = "Failed to refresh carousel";
      } finally {
        // Ensure the loading state is cleared before attempting to reinitialize
        // the Swiper instance so the DOM element for the carousel is mounted.
        isRefreshing = false;
      }

      // Give Svelte one tick to update the DOM now that `isRefreshing` is false,
      // then reinitialize the Swiper. This ensures the carousel element exists
      // and that `.invisible` will be removed by the initializer.
      await tick();
      await SwiperManager.reinitializeSwiper({ forceDestroy: true });
    }

    // Fetch carousel data if no jobs prop from initial load page
    static async fetchCarouselData(): Promise<void> {
      if (propJobs.length === 0 && !carouselData && !isLoading) {
        isLoading = true;
        APIService.fetchCarousel()
          .then((data) => {
            carouselData = data;
            error = null;
          })
          .catch((err) => {
            console.error("Failed to fetch carousel:", err);
            error = "Failed to load carousel data";
          })
          .finally(() => {
            isLoading = false;
          });
      }
    }

    // Keep Swiper in sync when job list updates
    static async updateSwiperOnJobsChange(): Promise<void> {
      // When job list changes, ensure Swiper exists and is in sync.
      const count = jobs?.length ?? 0;

      if (count === 0) {
        // No jobs: destroy any existing instance
        if (swiperInstance) {
          swiperInstance.destroy(true, true);
          swiperInstance = null;
        }
        return;
      }

      // If no instance yet, create one (ensures initialization after async data)
      if (!swiperInstance) {
        // small delay to allow DOM to render and then try to initialize
        requestAnimationFrame(() => SwiperManager.createSwiper());
        return;
      }

      // Otherwise destroy and re-init the instance so layout and slides are
      // measured cleanly (we no longer rely on mutation/resize observers).
      requestAnimationFrame(() => SwiperManager.reinitializeSwiper());
    }

    static destroySwiper(): void {
      if (swiperInstance) {
        try {
          swiperInstance.destroy(true, true);
        } catch (err) {
          // ignore
        }
        swiperInstance = null;
        swiperContainerEl = null;
        nextButtonEl = null;
        prevButtonEl = null;
      }
    }
  }

  class CarouselNavigationHandler {
    public static async handleClickNavigateToJob(
      slug: string,
      permalink: CardJob["permalink"],
      job: CardJob
    ): Promise<void> {
      this.carouselSaveCurrentSlideState();
      await this.handlePlatformSpecificNavigation(slug, permalink, job);
    }
    private static carouselSaveCurrentSlideState(): void {
      if (!swiperInstance) return;

      routeStateStore.saveCarouselState({
        slideIndex: swiperInstance?.activeIndex ?? 0,
      });
    }

    private static async handlePlatformSpecificNavigation(
      slug: string,
      permalink: CardJob["permalink"],
      job: CardJob
    ): Promise<void> {
      if (typeof window !== "undefined" && window.innerWidth >= 768) {
        // Desktop: open overlay
        await jobOverlay.openOverlay(slug, job);
      } else {
        // Mobile: use SPA navigation to SingleLowongan.svelte route
        const url = new URL(String(permalink), window.location.origin);
        await navigateTo(url.pathname + url.search + url.hash);
      }
    }
  }

  onMount(() => {
    requestAnimationFrame(() => void SwiperManager.createSwiper());
  });

  // Fetch carousel data if no jobs prop provided
  $effect(() => {
    void SwiperManager.fetchCarouselData();
  });

  // Keep Swiper in sync when job list updates
  $effect(() => {
    void SwiperManager.updateSwiperOnJobsChange();
  });

  onDestroy(() => {
    void SwiperManager.destroySwiper();
  });
</script>

<section class="min-h-[450px] md:min-h-[400px] lg:min-h-[500px]">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-lg md:text-2xl font-semibold mt-4">{title}</h2>
    <div class="flex items-center gap-1">
      <div class="hidden sm:flex gap-1">
        <button
          type="button"
          bind:this={prevButtonEl}
          class="job-carousel-prev btn rounded-full bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)]"
          aria-label="Sebelumnya"
          disabled={isRefreshing}
          aria-disabled={isRefreshing}
        >
          <ChevronCircleLeftSolid class="w-6 h-6" aria-hidden="true" />
        </button>
        <button
          type="button"
          bind:this={nextButtonEl}
          class="job-carousel-next btn btn-md rounded-full bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)]"
          aria-label="Berikutnya"
          disabled={isRefreshing}
          aria-disabled={isRefreshing}
        >
          <ChevronCircleRightSolid class="w-6 h-6" aria-hidden="true" />
        </button>
      </div>
      {#if swiperFailed}
        <button
          class="btn btn-ghost ml-2"
          onclick={() => {
            swiperFailed = false;
            void SwiperManager.reinitializeSwiper({ forceDestroy: true });
          }}
        >
          Coba lagi
        </button>
      {/if}

      <!-- Refresh button is available on all sizes and placed to the far right -->
      <button
        type="button"
        class="job-carousel-refresh btn btn-lg rounded-full ml-2 h-10 w-10 p-0 flex items-center justify-center text-current bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)] overflow-visible focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--wpl-global-color-1)]"
        aria-label="Segarkan lowongan"
        title="Segarkan"
        onclick={SwiperManager.refreshCarousel}
        disabled={isRefreshing || isLoading}
        aria-disabled={isRefreshing || isLoading}
        tabindex="0"
      >
        <RefreshSpinner size="h-5 w-5" spin={isRefreshing || isLoading} />
        <span class="sr-only"
          >{isRefreshing || isLoading ? "Sedang menyegarkan" : "Segarkan"}</span
        >
      </button>
    </div>
  </div>

  {#if isRefreshing}
    <div
      class="flex justify-center items-center min-h-[200px]"
      aria-live="polite"
    >
      <LoadingSpinner srLabel="Memuat carousel..." size="md" />
    </div>
  {:else if jobs && jobs.length}
    <div
      bind:this={swiperContainerEl}
      class="swiper job-carousel invisible"
      role="region"
      aria-label={title}
      aria-live="polite"
    >
      <div class="swiper-wrapper">
        {#each jobs as job, idx (job.id ?? job.permalink ?? idx)}
          <div class="swiper-slide">
            <JobCard
              jobdata={job}
              permalink={job.permalink}
              variant="carousel"
              onClick={async (slug) =>
                await CarouselNavigationHandler.handleClickNavigateToJob(
                  slug,
                  job.permalink ?? "",
                  job
                )}
            />
          </div>
        {/each}
      </div>
      <div class="flex justify-center mt-24">
        <div class="swiper-pagination"></div>
      </div>
    </div>
    {#if swiperFailed}
      <div class="job-carousel-fallback mt-6">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
        >
          {#each jobs as job, idx (job.id ?? job.permalink ?? idx)}
            <div class="fallback-item">
              <JobCard
                jobdata={job}
                permalink={job.permalink ?? ""}
                variant="carousel"
              />
            </div>
          {/each}
        </div>
      </div>
    {/if}
  {:else if isLoading}
    <div class="flex justify-center items-center min-h-[200px]">
      <LoadingSpinner srLabel="Memuat..." size="md" />
    </div>
  {:else if error}
    <p class="text-center text-red-500">{error}</p>
  {:else}
    <p class="text-center text-gray-500">Belum ada lowongan darurat</p>
  {/if}
</section>

<style>
  :global(.job-carousel.no-swiper .swiper-wrapper) {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
  }

  :global(.job-carousel.no-swiper .swiper-slide) {
    width: auto !important;
    transform: none !important;
  }

  :global(.job-carousel.no-swiper .job-carousel-next),
  :global(.job-carousel.no-swiper .job-carousel-prev),
  :global(.job-carousel.no-swiper .swiper-pagination) {
    display: none !important;
  }

  /* Extra styles for the fallback grid items */
  :global(.job-carousel-fallback .fallback-item) {
    display: block;
  }
</style>
