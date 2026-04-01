<script module lang="ts">
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import { jobOverlayManager } from "$lib/stores/JobOverlay.svelte";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import { routeStateStore } from "$lib/stores/Route.svelte";
  import { goto } from "$app/navigation";
  import type { CardJob, JobCardProps } from "@/types";
  import { APIServiceShared } from "@/services/APIService";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount } from "svelte";
  import { innerWidth } from "svelte/reactivity/window";
  import SwiperCore, { type Swiper } from "swiper";
  import type { SwiperOptions } from "swiper/types";
  import { Navigation, Pagination, Autoplay, Virtual } from "swiper/modules";
  import {
    ChevronCircleLeftSolid,
    ChevronCircleRightSolid,
  } from "svelte-awesome-icons";
  import "swiper/css";
  import "swiper/css/navigation";
  import "swiper/css/pagination";
  import "swiper/css/virtual";

  type SwiperVirtualExternalData = {
    from: number;
    to: number;
    offset: number;
  };
</script>

<script lang="ts">
  /*
    JobCarousel uses Swiper's Virtual module (renderExternal) to efficiently
    render very large lists (100+ items). Implementation notes and reasoning:

    - Swiper Virtual is configured with an index array (0..n-1) and calls
      `renderExternal` with { from, to, offset } when it needs DOM updates.
      We store that in `virtualData` and render only `jobs[from..to]`.

    - We apply `style="transform: translate3d(${virtualData.offset}px, 0, 0)"` on each rendered
      `.swiper-slide` so Swiper's expected positioning matches Svelte's DOM.
      This avoids the common "blank slides" issue where Swiper expects slides
      at certain positions but the framework hasn't applied offsets yet.

    - After Svelte paints (tick), we call a few Swiper update helpers to let
      Swiper recompute sizes/classes. Keep these minimal to avoid thrashing.

    - The previous `Virtualization.computeCarousel` helper was removed from
      this component in favor of Swiper Virtual. See
      `src/app/lib/utils/Virtualization.svelte.ts` for a deprecation note.

    TODOs for future maintainers:
    - If you change how Swiper Virtual is configured (breakpoints / slidesPerView),
      ensure `addSlidesBefore`/`addSlidesAfter` are sufficient to prevent
      visible white-space during fast swipes.
    - If switching to a different virtual strategy, preserve `data-swiper-slide-index`
      so Swiper internals map indexes correctly.
  */
  let { jobs, title = "Lowongan Darurat" } = $props<{
    jobs?: CardJob[];
    title?: string;
  }>();

  let lastBreakpoint = $state("");

  const breakpoint = $derived.by(() => {
    const w = innerWidth.current ?? 0;
    return w >= 1024 ? "lg" : w >= 640 ? "md" : "sm";
  });

  class SwiperManager {
    public virtualData = $state<SwiperVirtualExternalData>({
      from: 0,
      to: -1,
      offset: 0,
    });
    public swiperFailed = $state(false);
    public resizeTimer: ReturnType<typeof setTimeout> | null = null;
    public error = $state<string | null>(null);
    public isRefreshing = $state(false);
    public initialSlide = $state(0);
    public activeIndex = $state(0);
    public swiperInstance: Swiper | null = null;
    public isInitializing = $state(false);
    public swiperContainerEl = $state<HTMLElement | null>(null);
    public nextButtonEl = $state<HTMLElement | null>(null);
    public prevButtonEl = $state<HTMLElement | null>(null);
    public virtualIndexes = $derived.by(() => {
      const total = jobs?.length ?? 0;
      if (total <= 0) return [] as number[];

      const from = Math.max(0, Math.min(total - 1, this.virtualData.from ?? 0));
      const to = Math.max(
        from - 1,
        Math.min(total - 1, this.virtualData.to ?? -1),
      );
      if (to < from) return [] as number[];

      const idxs: number[] = [];
      for (let i = from; i <= to; i += 1) idxs.push(i);
      return idxs;
    });

    private createSwiperConfig(
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null,
    ): SwiperOptions {
      return {
        loop: false,
        rewind: false,
        slidesPerView: 1.1,
        centeredSlides: false,
        spaceBetween: 16,
        autoplay: {
          delay: 5000,
          disableOnInteraction: false,
          stopOnLastSlide: true,
        },
        pagination: paginationEl
          ? {
              el: paginationEl,
              clickable: true,
              dynamicBullets: true,
              dynamicMainBullets: 4,
            }
          : { clickable: true, dynamicBullets: true, dynamicMainBullets: 4 },
        navigation: {
          nextEl: nextEl ?? undefined,
          prevEl: prevEl ?? undefined,
        },
        watchSlidesProgress: false,
        passiveListeners: true,
        touchStartPreventDefault: false,
        touchStartForcePreventDefault: false,
        initialSlide: this.initialSlide ?? 0,
        virtual: {
          enabled: true,
          slides: Array.from({ length: jobs.length }, (_, i) => i),
          addSlidesBefore: 2,
          addSlidesAfter: 2,
          renderExternalUpdate: false,
          renderExternal: (data) => {
            const next = {
              from: Number(data?.from ?? 0),
              to: Number(data?.to ?? -1),
              offset: Number(data?.offset ?? 0),
            } satisfies SwiperVirtualExternalData;

            // Avoid excessive state writes during Swiper's internal loops.
            if (
              this.virtualData.from === next.from &&
              this.virtualData.to === next.to &&
              this.virtualData.offset === next.offset
            ) {
              return;
            }
            this.virtualData = next;
          },
        },
        on: {
          slideChange: (swiper: Swiper) => {
            this.activeIndex = swiper.activeIndex;
          },
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

    private waitForSlidesAndWidth(el: HTMLElement | null): boolean {
      if (!el) return false;
      const MAX_INIT_ATTEMPTS = 12;
      let attempts = 0;
      while (true) {
        const width = el.getBoundingClientRect().width;
        // With Swiper Virtual + renderExternal we may have 0 slides in DOM until
        // Swiper calls renderExternal; width is the primary readiness signal.
        if (width > 0) return true;
        attempts += 1;
        if (attempts >= MAX_INIT_ATTEMPTS) {
          this.swiperFailed = true;
          return false;
        }
      }
    }

    private initializeSwiperInstance(
      el: HTMLElement,
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null,
    ): void {
      try {
        if (SwiperCore && (SwiperCore as typeof SwiperCore).use) {
          SwiperCore.use([Navigation, Pagination, Autoplay, Virtual]);
        }

        const savedState = routeStateStore.getCarouselState();
        const targetSlideIndex = savedState?.slideIndex;
        const clampedIndex =
          typeof targetSlideIndex === "number" && jobs?.length
            ? Math.max(0, Math.min((jobs?.length ?? 1) - 1, targetSlideIndex))
            : 0;

        this.initialSlide = clampedIndex;

        const cfg = this.createSwiperConfig(paginationEl, nextEl, prevEl);
        const finalCfg = {
          ...(cfg || {}),
          modules: [Navigation, Pagination, Autoplay, Virtual],
        };

        this.swiperInstance = new SwiperCore(el, finalCfg);

        if (savedState && typeof savedState.slideIndex === "number") {
          this.swiperInstance?.slideTo(clampedIndex, 0);
          this.activeIndex = clampedIndex;
          this.swiperInstance?.update?.();
          this.swiperInstance?.updateSlides?.();
          this.swiperInstance?.updateProgress?.();
          this.swiperInstance?.updateSlidesClasses?.();
        }
      } catch {
        throw new Error("Failed to initialize Swiper");
      }
    }

    public createSwiper(): void {
      const el = this.swiperContainerEl;
      if (!el) return;

      if (this.isInitializing) return;
      this.isInitializing = true;
      this.swiperFailed = false;

      const paginationEl = el.querySelector(
        ".swiper-pagination",
      ) as HTMLElement | null;

      const nextEl = (this.nextButtonEl ??
        el.parentElement?.querySelector(
          ".job-carousel-next",
        )) as HTMLElement | null;
      const prevEl = (this.prevButtonEl ??
        el.parentElement?.querySelector(
          ".job-carousel-prev",
        )) as HTMLElement | null;

      // Wait until there are slides and the container has width
      const ready = this.waitForSlidesAndWidth(el);
      if (!ready) {
        this.isInitializing = false;
        return;
      }

      if (this.swiperInstance) {
        this.swiperInstance.destroy(true, true);
        this.swiperInstance = null;
      }

      try {
        this.initializeSwiperInstance(el, paginationEl, nextEl, prevEl);

        this.swiperFailed = false;
      } catch {
        this.swiperFailed = true;
      } finally {
        this.isInitializing = false;
        requestIdleCallback(() => routeStateStore.clearCarouselState());
      }
    }

    // Destroy and recreate the swiper instance (used by refresh button)
    public reinitializeSwiper({ forceDestroy = false } = {}) {
      // If we already have an instance, optionally destroy it first to ensure a
      // full re-measure on re-init.
      if (this.swiperInstance) {
        this.swiperInstance.destroy(forceDestroy, false);
        this.swiperInstance = null;
      }
      const MAX_DOM_ATTEMPTS = 12;
      let attempts = 0;
      while (
        (!this.swiperContainerEl ||
          !this.swiperContainerEl.isConnected ||
          this.swiperContainerEl.getBoundingClientRect().width === 0) &&
        attempts < MAX_DOM_ATTEMPTS
      ) {
        attempts += 1;
      }

      // Recreate the instance (this function lazy-loads Swiper JS)
      this.createSwiper();
    }

    // Refresh handler: fetch fresh data and reinitialize the carousel
    public async refreshCarousel() {
      if (this.isRefreshing) return;
      this.isRefreshing = true;
      try {
        // Stop and remove any existing instance so we re-create from scratch
        if (this.swiperInstance) {
          try {
            this.swiperInstance.destroy(true, true);
          } catch (e) {
            console.error(
              "Error destroying Swiper instance during refresh:",
              e,
            );
          }
          this.swiperInstance = null;
        }
        // Fetch fresh carousel data
        const data = await APIServiceShared.fetchCarouselGraphQL();
        jobs = data?.jobs ?? null;
        this.error = null;
      } catch (e) {
        console.error("Error refreshing carousel:", e);
        this.error = "Failed to refresh carousel";
      } finally {
        this.isRefreshing = false;
      }
      this.reinitializeSwiper({ forceDestroy: true });
    }

    // Keep Swiper in sync when job list updates
    public updateSwiperOnJobsChange(): void {
      // When job list changes, ensure Swiper exists and is in sync.
      const count = jobs?.length ?? 0;

      if (count === 0) {
        // No jobs: destroy any existing instance
        if (this.swiperInstance) {
          this.swiperInstance.destroy(true, true);
          this.swiperInstance = null;
        }
        return;
      }

      // If no instance yet, create one (ensures initialization after async data)
      if (!this.swiperInstance) {
        // small delay to allow DOM to render and then try to initialize
        requestAnimationFrame(() => this.createSwiper());
        return;
      }

      // Update Swiper Virtual slides in-place (much cheaper than re-init for 100+ items).
      try {
        if (this.swiperInstance?.virtual) {
          this.swiperInstance.virtual.slides = Array.from(
            { length: count },
            (_, i) => i,
          );
          this.swiperInstance.virtual.update(true);
        }
        this.swiperInstance?.update();
      } catch {
        // As a safe fallback, try a full re-init.
        requestAnimationFrame(() => this.reinitializeSwiper());
      }
    }

    public destroySwiper(): void {
      if (this.swiperInstance) {
        try {
          this.swiperInstance.destroy(true, true);
        } catch (e) {
          console.error("Error destroying Swiper instance:", e);
        }
        this.swiperInstance = null;
        this.swiperContainerEl = null;
        this.nextButtonEl = null;
        this.prevButtonEl = null;
      }
    }
  }

  const swiperManager = new SwiperManager();

  class CarouselNavigationHandler {
    public static handleClickNavigateToJob(
      slug: string,
      permalink: CardJob["permalink"],
      job: CardJob,
      clickedSlideIndex?: number,
    ): void {
      this.carouselSaveCurrentSlideState(clickedSlideIndex);
      this.handlePlatformSpecificNavigation(slug, permalink, job);
    }
    private static carouselSaveCurrentSlideState(
      clickedSlideIndex?: number,
    ): void {
      if (!swiperManager.swiperInstance) return;

      const slideIndex =
        typeof clickedSlideIndex === "number"
          ? clickedSlideIndex
          : (swiperManager.swiperInstance?.activeIndex ?? 0);

      routeStateStore.saveCarouselState({ slideIndex });
    }

    private static handlePlatformSpecificNavigation(
      slug: string,
      permalink: CardJob["permalink"],
      job: CardJob,
    ): void {
      if (typeof window !== "undefined" && window.innerWidth >= 768) {
        // Desktop: open overlay
        const jobgridElement = isJobGridEl();
        jobOverlayManager?.openOverlay(slug, job, "carousel");
        jobgridElement?.scrollIntoView({ behavior: "smooth", block: "start" });
      } else {
        // Mobile: mark visited for carousel then use SPA navigation to SingleLowongan.svelte route
        routeStateStore.MarkVisitedJob(slug, "carousel");
        const url = new URL(String(permalink), window.location.origin);
        void goto(url.pathname + url.search + url.hash);
      }
    }
  }
  // Derive a simple jobs count to drive updates without reacting to every
  // internal jobs change. This lets us limit side-effectful $effect usage.
  const jobsCount = $derived(jobs?.length ?? 0);

  // Keep Swiper in sync when job list updates — react to `jobsCount` only.
  $effect(() => {
    jobsCount;
    void swiperManager.updateSwiperOnJobsChange();
  });

  // When Swiper Virtual asks us to render a new range, wait for Svelte to paint
  // those slides, then notify Swiper so it can correctly measure/update classes.
  $effect(() => {
    swiperManager.virtualData;
    if (!swiperManager.swiperInstance) return;
    requestAnimationFrame(() => {
      swiperManager.swiperInstance?.updateSlides();
      swiperManager.swiperInstance?.updateProgress();
      swiperManager.swiperInstance?.updateSlidesClasses();
    });
  });

  $effect(() => {
    breakpoint;
    const bp = breakpoint as string;
    if (bp !== lastBreakpoint) {
      lastBreakpoint = bp;
      if (swiperManager.resizeTimer) clearTimeout(swiperManager.resizeTimer);
      swiperManager.resizeTimer = setTimeout(() => {
        void swiperManager.reinitializeSwiper({ forceDestroy: true });
      }, 50);
    }
  });

  onMount(() => {
    swiperManager.createSwiper();
    return () => {
      if (swiperManager.resizeTimer) clearTimeout(swiperManager.resizeTimer);
      swiperManager.destroySwiper();
    };
  });
</script>

<section class="min-h-[450px] md:min-h-[400px] lg:min-h-[500px] mt-12">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-lg md:text-2xl font-semibold mt-4">{title}</h2>
    <div class="flex items-center gap-1">
      <div class="hidden sm:flex gap-1">
        <button
          type="button"
          bind:this={swiperManager.prevButtonEl}
          class="job-carousel-prev btn rounded-full bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)]"
          aria-label="Sebelumnya"
          disabled={swiperManager.isRefreshing}
          aria-disabled={swiperManager.isRefreshing}
        >
          <ChevronCircleLeftSolid class="w-6 h-6" aria-hidden="true" />
        </button>
        <button
          type="button"
          bind:this={swiperManager.nextButtonEl}
          class="job-carousel-next btn btn-md rounded-full bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)]"
          aria-label="Berikutnya"
          disabled={swiperManager.isRefreshing}
          aria-disabled={swiperManager.isRefreshing}
        >
          <ChevronCircleRightSolid class="w-6 h-6" aria-hidden="true" />
        </button>
      </div>
      {#if swiperManager.swiperFailed}
        <button
          class="btn btn-ghost ml-2"
          onclick={() => {
            swiperManager.swiperFailed = false;
            void swiperManager.reinitializeSwiper({ forceDestroy: true });
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
        onclick={() => void swiperManager.refreshCarousel()}
        disabled={swiperManager.isRefreshing}
        aria-disabled={swiperManager.isRefreshing}
        tabindex="0"
      >
        <RefreshSpinner size="h-5 w-5" spin={swiperManager.isRefreshing} />
        <span class="sr-only"
          >{swiperManager.isRefreshing
            ? "Sedang menyegarkan"
            : "Segarkan"}</span
        >
      </button>
    </div>
  </div>

  {#if swiperManager.isRefreshing}
    <div
      class="flex justify-center items-center min-h-[200px]"
      aria-live="polite"
    >
      <LoadingSpinner srLabel="Memuat carousel..." size="md" />
    </div>
  {:else if jobs && jobs.length}
    <div
      bind:this={swiperManager.swiperContainerEl}
      class="swiper job-carousel"
      role="region"
      aria-label={title}
      aria-live="polite"
    >
      <div class="swiper-wrapper">
        {#each swiperManager.virtualIndexes as idx (jobs[idx]?.id ?? jobs[idx]?.permalink ?? idx)}
          {@const job: JobCardProps['jobdata'] = jobs[idx]}
          <div
            class="swiper-slide"
            data-swiper-slide-index={idx}
            style={`transform: translate3d(${swiperManager.virtualData.offset ?? 0}px, 0, 0); `}
          >
            <JobCard
              jobdata={job}
              permalink={job?.permalink}
              index={idx}
              variant="carousel"
              onClick={(slug: string, _event: MouseEvent, index: number) =>
                CarouselNavigationHandler.handleClickNavigateToJob(
                  slug,
                  job?.permalink ?? "",
                  job!,
                  index,
                )}
            />
          </div>
        {/each}
      </div>
      <div class="flex justify-center mt-24">
        <div class="swiper-pagination"></div>
      </div>
    </div>
    {#if swiperManager.swiperFailed}
      <div class="job-carousel-fallback mt-6">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
        >
          {#each jobs as job, idx (Number(job.id) ?? job.permalink ?? idx)}
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
  {:else if swiperManager.swiperFailed}
    <p class="text-center text-red-500">{swiperManager.error}</p>
  {:else}
    <p class="text-center text-gray-500">Belum ada lowongan darurat</p>
  {/if}
</section>

<style>
  /* Prefer browser native gestures for vertical scrolling to avoid blocking touchstart */
  :global(.job-carousel),
  :global(.job-carousel .swiper-wrapper),
  :global(.job-carousel .swiper-slide) {
    touch-action: pan-y;
    -ms-touch-action: pan-y;
    user-select: none;
  }

  /* Flexbox solution for equal card heights */
  :global(.swiper-wrapper, .swiper-slide) {
    display: flex;
  }

  :global(.swiper-slide) {
    height: auto;
  }

  :global(.job-carousel-fallback .fallback-item) {
    display: block;
  }
</style>
