<script module lang="ts">
  import type { CardJob, CarouselProps, JobCardProps } from "@/types";
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
  import { useRIC } from "@/utils/window";
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
  import { browser } from "$app/environment";

  let { jobs }: CarouselProps = $props();
  const title = "Lowongan Darurat";
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
    public paginationEl = $state<HTMLElement | null>(null);
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
        slidesPerView: 1,
        centeredSlides: false,
        spaceBetween: 12,
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
          nextEl: nextEl,
          prevEl: prevEl,
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
          480: {
            slidesPerView: 1.15,
            spaceBetween: 16,
          },
          640: {
            slidesPerView: 2,
            spaceBetween: 20,
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

    /**
     * @param attempt The current retry attempt count for initializing Swiper. Used internally for retry logic and error handling.
     *
     * */
    public createSwiper(attempt = 0): void {
      const containerEl: Element | null =
        document.querySelector(".job-carousel");
      const el =
        containerEl instanceof HTMLElement
          ? containerEl
          : this.swiperContainerEl;
      if (!el) return;

      if (this.#isInitializing) return;
      this.#isInitializing = true;
      this.swiperFailed = false;

      const paginationEl =
        (document.querySelector(".swiper-pagination") as HTMLElement | null) ??
        this.paginationEl;

      const nextEl =
        (document.querySelector(".job-carousel-next") as HTMLElement | null) ??
        this.nextButtonEl;

      const prevEl =
        (document.querySelector(".job-carousel-prev") as HTMLElement | null) ??
        this.prevButtonEl;

      try {
        this.initializeSwiperInstance(el, paginationEl, nextEl, prevEl);

        this.swiperFailed = false;
      } catch (e) {
        console.error(
          `Error initializing Swiper, retrying for attempt ${++attempt}:`,
          e,
        );
        if (attempt < 3) {
          this.createSwiper(attempt);
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
        if (!this.swiperInstance || !this.swiperContainerEl) {
          this.createSwiper();
        }
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
      if (!this.swiperInstance) return;
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

    /**
     * Create/disable Swiper instance based on carousel visibility.
     * Keeps the DOM-focused initialization scoped to the carousel element.
     */
    public observeIntersectionCarousel(): Attachment<HTMLElement> {
      let observer: IntersectionObserver | null = null;

      const handleIntersection = (entries: IntersectionObserverEntry[]) => {
        entries.forEach((entry) => {
          if (!this.swiperInstance) return;
          if (entry.isIntersecting) this.swiperInstance.enable();
          if (!entry.isIntersecting) this.swiperInstance.disable();
        });
      };

      return (el: HTMLElement) => {
        observer ??= new IntersectionObserver(handleIntersection, {
          threshold: 0.03,
          rootMargin: "2000px",
        });

        observer.observe(this.swiperContainerEl ?? el);

        return () => {
          if (observer) {
            observer.disconnect();
            observer = null;
          }
        };
      };
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
          useSidePanel.scrollToJobGridCard(slug, "featured"); // jump to featured
        });
      } else {
        // Mobile: mark visited for carousel then use SPA navigation to SingleLowongan.svelte route
        routeStateStore.MarkVisitedJob(slug, "carousel");
        const url = new URL(String(permalink), window.location.origin);
        void goto(url.pathname + url.search + url.hash);
      }
    }
  }

  const swiperManager = new SwiperManager();

  onMount(() => {
    swiperManager.createSwiper();
    return () => {
      swiperManager.destroySwiper(true, true);
    };
  });
</script>

<section
  class="mt-12 min-w-0 overflow-hidden md:min-h-[500px] lg:min-h-[600px]"
>
  <div class="mb-6 flex min-w-0 flex-wrap items-center justify-between gap-3">
    <h2
      class="mt-4 min-w-0 break-words text-lg font-semibold leading-tight md:text-2xl"
    >
      {title}
    </h2>
    <div class="flex shrink-0 items-center gap-1">
      <div class="hidden gap-1 sm:flex">
        <button
          type="button"
          bind:this={swiperManager.prevButtonEl}
          class="job-carousel-prev btn btn-circle rounded-full bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)]"
          aria-label="Sebelumnya"
          disabled={swiperManager.isRefreshing}
          aria-disabled={swiperManager.isRefreshing}
        >
          <ChevronCircleLeftSolid class="w-6 h-6" aria-hidden="true" />
        </button>
        <button
          type="button"
          bind:this={swiperManager.nextButtonEl}
          class="job-carousel-next btn btn-circle rounded-full bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-1)]"
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
        class="job-carousel-refresh btn btn-circle ml-2 flex h-10 w-10 items-center justify-center overflow-visible rounded-full bg-[var(--wpl-global-color-5)] p-0 text-current hover:bg-[var(--wpl-global-color-1)] focus:outline-none focus:ring-2 focus:ring-[var(--wpl-global-color-1)] focus:ring-offset-2"
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
    {#if browser}
      <div
        {@attach swiperManager.observeIntersectionCarousel()}
        bind:this={swiperManager.swiperContainerEl}
        class="swiper job-carousel min-w-0"
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
              class="swiper-slide min-w-0"
              data-swiper-slide-index={idx}
              style={`transform: translate3d(${swiperManager.virtualData.offset ?? 0}px, 0, 0); `}
            >
              <JobCard
                jobdata={job}
                permalink={job?.permalink}
                variant="carousel"
                onclick={() => {
                  if (!job) return;
                  CarouselNavigationHandler.handleClickNavigateToJob(
                    job.slug ?? "",
                    job.permalink ?? "",
                    job,
                    idx,
                  );
                }}
              />
            </div>
          {/each}
        </div>
        <div class="mt-16 flex justify-center sm:mt-20 lg:mt-24">
          <div
            bind:this={swiperManager.paginationEl}
            class="swiper-pagination"
          ></div>
        </div>
      </div>
    {:else}
      <!-- SSR rendering -->
      <div class="min-w-0 overflow-hidden">
        <!-- Please match according Swiper breakpoints -->
        <div class="flex w-full gap-3 min-[480px]:gap-4 sm:gap-5 lg:gap-8">
          {#each sortedJobs as job, idx (Number(job.id) ?? job.permalink ?? idx)}
            <div
              class="flex min-w-0 shrink-0 grow-0 basis-full min-[480px]:basis-[calc((100%-2.4px)/1.15)] sm:basis-[calc((100%-20px)/2)] lg:basis-[calc((100%-96px)/4)]"
            >
              <JobCard
                jobdata={job}
                permalink={job.permalink ?? ""}
                variant="carousel"
                onclick={() => {
                  CarouselNavigationHandler.handleClickNavigateToJob(
                    job.slug ?? "",
                    job.permalink ?? "",
                    job,
                    idx,
                  );
                }}
              />
            </div>
          {/each}
        </div>
      </div>
    {/if}
    {#if swiperManager.swiperFailed}
      <div class="min-w-0 overflow-hidden">
        <!-- Please match according Swiper breakpoints -->
        <div class="flex w-full gap-3 min-[480px]:gap-4 sm:gap-5 lg:gap-8">
          {#each sortedJobs as job, idx (Number(job.id) ?? job.permalink ?? idx)}
            <div
              class="flex min-w-0 shrink-0 grow-0 basis-full min-[480px]:basis-[calc((100%-2.4px)/1.15)] sm:basis-[calc((100%-20px)/2)] lg:basis-[calc((100%-96px)/4)]"
            >
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

<style lang="postcss">
  @reference "@css/app.css";

  :global(.job-carousel),
  :global(.job-carousel .swiper-wrapper),
  :global(.job-carousel .swiper-slide) {
    @apply touch-pan-y select-none;
    -ms-touch-action: pan-y;
  }

  :global(.swiper-slide) {
    @apply flex h-auto min-w-0;
  }
</style>
