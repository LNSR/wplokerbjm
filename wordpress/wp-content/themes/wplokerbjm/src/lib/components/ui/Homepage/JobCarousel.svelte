<script module lang="ts">
  import type { CardJob, JobCardProps } from "@/types";
  import type { SwiperOptions, VirtualData } from "swiper/types";
  function sortJobsByDeadline(jobs: CardJob[] | null): CardJob[] {
    if (!jobs) return [];
    const items = [...jobs];
    const sorted = items.sort((a, b) => {
      const deadlineA = Date.parse(
        a?.ringkasanPekerjaan?.deadline ?? "9999-12-31",
      );
      const deadlineB = Date.parse(
        b?.ringkasanPekerjaan?.deadline ?? "9999-12-31",
      );

      if (deadlineA === deadlineB) {
        return (Number(a?.id) ?? 0) - (Number(b?.id) ?? 0);
      }

      return deadlineA - deadlineB;
    });
    return sorted;
  }
</script>

<script lang="ts">
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import { routeStateStore } from "$lib/stores/Route.svelte";
  import { goto } from "$app/navigation";
  import { APIServiceShared } from "@/services/graphql/APIService";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { flushSync, onMount } from "svelte";
  import { useRIC } from "$lib/utils/window.svelte";
  import { innerWidth } from "svelte/reactivity/window";
  import SwiperCore, { type Swiper } from "swiper";
  import { Navigation, Pagination, Autoplay, Virtual } from "swiper/modules";
  import {
    ChevronCircleLeftSolid,
    ChevronCircleRightSolid,
  } from "svelte-awesome-icons";
  import "swiper/css";
  import "swiper/css/navigation";
  import "swiper/css/pagination";
  import "swiper/css/virtual";
  import { useSidePanel } from "$lib/composables/SidePanel.svelte";
  import type { Attachment } from "svelte/attachments";

  let {
    jobs,
    title = "Lowongan Darurat",
  }: {
    jobs?: CardJob[];
    title?: string;
  } = $props();

  const sortedJobs = $derived(sortJobsByDeadline(jobs ?? null));

  class SwiperManager {
    public virtualData = $state<Omit<VirtualData<any>, "slides">>({
      from: 0,
      to: -1,
      offset: 0,
    });
    public swiperFailed = $state(false);
    public error = $state<string | null>(null);
    public isRefreshing = $state(false);
    public jobCount = $derived(jobs?.length ?? 0);
    public swiperInstance: Swiper | null = null;
    public swiperContainerEl = $state<HTMLElement | null>(null);
    public nextButtonEl = $state<HTMLElement | null>(null);
    public prevButtonEl = $state<HTMLElement | null>(null);
    public virtualIndexes = $derived.by(() => {
      const total = this.jobCount;
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
    #initialSlide = $state(0);
    #slides = $derived(
      Array.from({ length: this.jobCount }, (_, index) => index),
    );
    #isInitializing = $state(false);

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
        watchSlidesProgress: true,
        passiveListeners: true,
        touchStartPreventDefault: false,
        touchStartForcePreventDefault: false,
        initialSlide: this.#initialSlide ?? 0,
        virtual: {
          enabled: true,
          slides: this.#slides,

          addSlidesBefore: 2,
          addSlidesAfter: 2,
          renderExternalUpdate: false,
          renderExternal: (data: VirtualData<any>) => {
            const next = {
              from: Number(data?.from ?? 0),
              to: Number(data?.to ?? -1),
              offset: Number(data?.offset ?? 0),
            };

            // Avoid excessive state writes during Swiper's internal loops.
            if (
              this.virtualData.from === next.from &&
              this.virtualData.to === next.to &&
              this.virtualData.offset === next.offset
            ) {
              return;
            }

            this.virtualData = next;
            flushSync();
            this.swiperInstance?.virtual?.update(true);
            this.swiperInstance?.updateSlides();
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

    /**
     * Setup process to initialize Swiper instance with retries and error handling.
     * @param el The main Swiper container element.
     * @param paginationEl The pagination element for Swiper.
     * @param nextEl The next navigation button element.
     * @param prevEl The previous navigation button element.
     * @throws Will throw an error if Swiper initialization fails after retries.
     */
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
          typeof targetSlideIndex === "number" && this.jobCount > 0
            ? Math.max(0, Math.min(this.jobCount - 1, targetSlideIndex))
            : 0;

        this.#initialSlide = clampedIndex;

        const cfg = this.createSwiperConfig(paginationEl, nextEl, prevEl);
        const finalCfg = {
          ...(cfg || {}),
          modules: [Navigation, Pagination, Autoplay, Virtual],
        };

        this.swiperInstance = new SwiperCore(el, finalCfg);

        requestAnimationFrame(() => {
          if (this.swiperInstance?.virtual)
            this.swiperInstance.virtual.update(true);
          this.swiperInstance?.update();
          if (savedState && typeof savedState.slideIndex === "number")
            this.swiperInstance?.slideTo(clampedIndex, 0);
        });
        useRIC(() => routeStateStore.clearCarouselState(), {
          fallbackDelay: 0,
          fallback: "timeout",
          timeout: 1300,
        });
      } catch {
        throw new Error("Failed to initialize Swiper");
      }
    }

    public createSwiper(): void {
      const el = this.swiperContainerEl;
      if (!el) return;

      if (this.#isInitializing) return;
      this.#isInitializing = true;
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

      try {
        this.initializeSwiperInstance(el, paginationEl, nextEl, prevEl);

        this.swiperFailed = false;
      } catch (e) {
        let attempt = 0;
        console.error(
          `Error initializing Swiper, retrying for attempt ${attempt++}`,
          e,
        );
        if (attempt < 3) {
          this.createSwiper();
        }
      } finally {
        this.#isInitializing = false;
      }
    }

    // Refresh handler: fetch fresh data and reinitialize the carousel
    public async refreshCarousel() {
      if (this.isRefreshing) return;
      this.isRefreshing = true;
      try {
        this.swiperInstance?.disable();
        const data = await APIServiceShared.fetchCarouselGraphQL();
        jobs = data?.jobs ?? null;
        this.error = null;
        // Obligatory RAF
        requestAnimationFrame(() => {
          flushSync();
          this.swiperInstance?.virtual?.update(true);
          this.swiperInstance?.enable();
        });
      } catch (e) {
        console.error("Error refreshing carousel:", e);
        this.error = "Failed to refresh carousel";
      } finally {
        this.isRefreshing = false;
      }
    }

    public destroySwiper(
      ...options: NonNullable<Parameters<Swiper["destroy"]>>
    ): void {
      if (this.swiperInstance) {
        try {
          this.swiperInstance.destroy(...options);
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

  /**
   * Handles navigation actions within the carousel, including saving state
   * and platform-specific navigation behavior.
   */
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
      if (innerWidth.current! >= 768) {
        // Desktop: open overlay
        useSidePanel.openSidePanel(slug, job, "carousel", () => {
          useSidePanel.scrollToJobGridCard(slug, true, "featured"); // jump to featured
        });
      } else {
        // Mobile: mark visited for carousel then use SPA navigation to SingleLowongan.svelte route
        routeStateStore.MarkVisitedJob(slug, "carousel");
        const url = new URL(String(permalink), window.location.origin);
        void goto(url.pathname + url.search + url.hash);
      }
    }
  }

  /**
   * Create/destroy Swiper instance based on carousel visibility.
   * Keeps the DOM-focused initialization scoped to the carousel element.
   */
  const observeIntersectionCarousel: Attachment<HTMLElement> = (() => {
    let observer: IntersectionObserver | null = null;

    function handleIntersection(entries: IntersectionObserverEntry[]) {
      entries.forEach((entry) => {
        if (!swiperManager.swiperInstance) return;
        if (entry.isIntersecting) swiperManager.swiperInstance.enable();
        if (!entry.isIntersecting) swiperManager.swiperInstance.disable();
      });
    }

    return (el: HTMLElement) => {
      observer ??= new IntersectionObserver(handleIntersection, {
        threshold: 0.03,
        rootMargin: "2000px",
      });

      observer.observe(el);

      return () => {
        if (observer) {
          observer.disconnect();
          observer = null;
        }
      };
    };
  })();

  const swiperManager = new SwiperManager();

  onMount(() => {
    swiperManager.createSwiper();
    return () => {
      swiperManager.destroySwiper(true, true);
    };
  });
</script>

<section class="min-h-[400px] md:min-h-[500px] lg:min-h-[600px] mt-12">
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
            swiperManager.createSwiper();
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
        onclick={async () => await swiperManager.refreshCarousel()}
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

  {#if jobs && jobs.length}
    {#if swiperManager.isRefreshing}
      <div
        class="flex justify-center items-center min-h-[200px]"
        aria-live="polite"
      >
        <LoadingSpinner srLabel="Memuat carousel..." size="md" />
      </div>
    {/if}
    <div
      {@attach observeIntersectionCarousel}
      bind:this={swiperManager.swiperContainerEl}
      class="swiper job-carousel"
      role="region"
      style:visibility={swiperManager.isRefreshing ? "hidden" : "visible"}
      aria-hidden={swiperManager.isRefreshing}
      aria-label={title}
      aria-live="polite"
    >
      <div class="swiper-wrapper">
        {#each swiperManager.virtualIndexes as idx}
          {@const job: JobCardProps['jobdata'] = sortedJobs[idx]}
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
          {#each sortedJobs as job, idx (Number(job.id) ?? job.permalink ?? idx)}
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

  :global(.swiper-slide) {
    height: auto;
    width: auto;
    display: flex;
  }

  :global(.job-carousel-fallback .fallback-item) {
    display: block;
  }
</style>
