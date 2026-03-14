<script module lang="ts">
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import { GlobalNavigateTo, routeStateStore } from "$lib/stores/Route.svelte";
  import type { CardJob } from "@/types";
  import { APIService } from "@/services/APIService";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount, onDestroy, tick } from "svelte";
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

  let lastBreakpoint = $state("");

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

  const breakpoint = $derived.by(() => {
    const w = innerWidth.current ?? 0;
    return w >= 1024 ? "lg" : w >= 640 ? "md" : "sm";
  });

  class SwiperManager {
    pendingVirtualOffset: number | null = null;
    virtualData = $state<SwiperVirtualExternalData>({
      from: 0,
      to: -1,
      offset: 0,
    });
    swiperFailed = $state(false);
    resizeTimer: ReturnType<typeof setTimeout> | null = null;
    error = $state<string | null>(null);
    isRefreshing = $state(false);
    reappliedSavedOffset = $state(false);
    activeIndex = $state(0);
    swiperInstance: Swiper | null = null;
    isInitializing = $state(false);
    swiperContainerEl = $state<HTMLElement | null>(null);
    nextButtonEl = $state<HTMLElement | null>(null);
    prevButtonEl = $state<HTMLElement | null>(null);
    virtualIndexes = $derived.by(() => {
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

    equalizeCardHeights() {
      if (!this.swiperContainerEl) return;
      const slides = this.swiperContainerEl.querySelectorAll(".swiper-slide");
      let maxHeight = 0;
      slides.forEach((slide) => {
        const card = slide.querySelector(
          ".card-base-carousel",
        ) as HTMLElement | null;
        if (card) {
          card.style.height = "auto";
          maxHeight = Math.max(maxHeight, card.offsetHeight);
        }
      });
      slides.forEach((slide) => {
        const card = slide.querySelector(
          ".card-base-carousel",
        ) as HTMLElement | null;
        if (card) {
          card.style.height = maxHeight + "px";
        }
      });
    }

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
        virtual: {
          enabled: true,
          slides: Array.from({ length: jobs.length }, (_, i) => i),
          addSlidesBefore: 2,
          addSlidesAfter: 2,
          renderExternalUpdate: false,
          renderExternal: (data: any) => {
            const next = {
              from: Number(data?.from ?? 0),
              to: Number(data?.to ?? -1),
              offset: Number(data?.offset ?? 0),
            } satisfies SwiperVirtualExternalData;

            // If we have a pendingVirtualOffset (from saved state) prefer it
            // for the first renderExternal call so Svelte's virtual slides are
            // positioned exactly where the user left them. Clear it after use.
            if (this.pendingVirtualOffset !== null) {
              next.offset = this.pendingVirtualOffset;
              this.pendingVirtualOffset = null;
            }

            // Avoid excessive state writes during Swiper's internal loops.
            if (
              this.virtualData.from === next.from &&
              this.virtualData.to === next.to &&
              this.virtualData.offset === next.offset
            ) {
              return;
            }
            this.virtualData = next;

            // If we have a previously saved state with an offset, reapply its
            // translate after Swiper has provided its own virtual offsets. This
            // helps keep visual position stable when Swiper updates virtualData
            // after initialization. Only do this once per init to avoid thrash.
            const savedStateNow = routeStateStore.getCarouselState();
            if (
              savedStateNow &&
              typeof (savedStateNow as any).offset === "number" &&
              !this.reappliedSavedOffset
            ) {
              const savedOffsetNow = (savedStateNow as any).offset;
              // Schedule re-apply on next RAFs to let Swiper finish its work.
              // Ensure Svelte has flushed DOM updates for virtual slides before
              // reapplying the wrapper translate.
              tick().then(() => {
                requestAnimationFrame(() => {
                  const wrapper = (
                    this.swiperContainerEl as HTMLElement | null
                  )?.querySelector(".swiper-wrapper") as HTMLElement | null;
                  if (
                    this.swiperInstance &&
                    typeof (this.swiperInstance as any)?.setTranslate ===
                      "function"
                  ) {
                    (this.swiperInstance as any).setTranslate(savedOffsetNow);
                  } else if (wrapper) {
                    wrapper.style.transform = `translate3d(${savedOffsetNow}px, 0, 0)`;
                  }
                });
              });
              this.reappliedSavedOffset = true;
            }
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

    private async waitForSlidesAndWidth(
      el: HTMLElement | null,
    ): Promise<boolean> {
      if (!el) return false;
      const MAX_INIT_ATTEMPTS = 12;
      let attempts = 0;
      while (true) {
        await tick();
        const width = el.getBoundingClientRect().width;
        // With Swiper Virtual + renderExternal we may have 0 slides in DOM until
        // Swiper calls renderExternal; width is the primary readiness signal.
        if (width > 0) return true;
        attempts += 1;
        if (attempts >= MAX_INIT_ATTEMPTS) {
          el.classList.remove("invisible");
          // If we timed out, mark swiper as failed to fall back to a static grid.
          this.swiperFailed = true;
          return false;
        }

        await new Promise((res) => setTimeout(res, 120));
      }
    }

    private async initializeSwiperInstance(
      el: HTMLElement,
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null,
    ): Promise<void> {
      try {
        if (SwiperCore && (SwiperCore as typeof SwiperCore).use) {
          SwiperCore.use([Navigation, Pagination, Autoplay, Virtual]);
        }

        // If there is a saved carousel state, set `virtualData` first so
        // Svelte renders slides with the saved offset before Swiper measures
        // layout. This avoids having to forcibly set per-slide transforms
        // after initialization (which would overwrite Swiper's own values).
        const savedState = routeStateStore.getCarouselState();
        if (savedState && typeof savedState.offset === "number") {
          this.virtualData = {
            from: this.virtualData?.from ?? 0,
            to: this.virtualData?.to ?? -1,
            offset: savedState.offset,
          } satisfies SwiperVirtualExternalData;
          this.pendingVirtualOffset = savedState.offset;
        }

        const cfg = this.createSwiperConfig(paginationEl, nextEl, prevEl);
        const finalCfg = {
          ...(cfg || {}),
          modules: [Navigation, Pagination, Autoplay, Virtual],
        };

        this.swiperInstance = new SwiperCore(el, finalCfg);

        // After creating Swiper, navigate to the saved slide index (if any)
        // and then clear the stored state so we don't reapply it later.
        if (savedState) {
          this.swiperInstance?.slideTo(savedState.slideIndex);
          // If we saved the wrapper translate previously, set Swiper's
          // translate directly after slideTo so the visual position matches
          // exactly. Use the Swiper API where available; fall back to
          // writing the wrapper style if necessary.
          const savedOffset = (savedState as any).offset;
          if (typeof savedOffset === "number") {
            // Re-apply translate across multiple RAFs to avoid being stomped by
            // Swiper's internal layout steps. We do not clear the saved state
            // yet — keep it for debugging.
            const applyTranslate = () => {
              if (typeof this.swiperInstance?.setTranslate === "function") {
                this.swiperInstance.setTranslate(savedOffset);
                this.swiperInstance?.update?.();
                this.swiperInstance?.updateProgress?.();
                this.swiperInstance?.updateSlidesClasses?.();
              } else {
                const wrapper = el.querySelector(
                  ".swiper-wrapper",
                ) as HTMLElement | null;
                if (wrapper)
                  wrapper.style.transform = `translate3d(${savedOffset}px, 0, 0)`;
              }
            };

            requestAnimationFrame(() => {
              applyTranslate();
              requestAnimationFrame(() => {
                applyTranslate();
                // Final attempt after a short delay in case Swiper triggers async updates
                setTimeout(() => applyTranslate(), 40);
              });
            });
          }
        }

        await tick();
        return;
      } catch {
        throw new Error("Failed to initialize Swiper");
      }
    }

    async createSwiper(): Promise<void> {
      const el = this.swiperContainerEl as HTMLElement | null;
      if (!el) return;

      if (this.isInitializing) return;
      this.isInitializing = true;
      this.swiperFailed = false;

      await tick();

      el.classList.remove("invisible");

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
      const ready = await this.waitForSlidesAndWidth(el);
      if (!ready) {
        this.isInitializing = false;
        return;
      }

      if (this.swiperInstance) {
        this.swiperInstance.destroy(true, true);
        this.swiperInstance = null;
      }

      try {
        await this.initializeSwiperInstance(el, paginationEl, nextEl, prevEl);

        this.swiperFailed = false;
        el.classList.remove("no-swiper");
        this.equalizeCardHeights();
      } catch {
        this.swiperFailed = true;
        el.classList.add("no-swiper");
      } finally {
        el.classList.remove("invisible");
        this.isInitializing = false;
      }
    }

    // Destroy and recreate the swiper instance (used by refresh button)
    async reinitializeSwiper({ forceDestroy = false } = {}) {
      // If we already have an instance, optionally destroy it first to ensure a
      // full re-measure on re-init.
      if (this.swiperInstance) {
        try {
          this.swiperInstance.destroy(forceDestroy, true);
        } catch {
          // ignore
        }
        this.swiperInstance = null;
      }

      // Wait a tick to ensure DOM has a chance to render the carousel element.
      // During a "refresh" the carousel DOM may be hidden (isRefreshing=true),
      // so we need to wait until it is present and has layout before creating
      // the Swiper instance.
      const MAX_DOM_ATTEMPTS = 12;
      let attempts = 0;
      while (
        (!this.swiperContainerEl ||
          !(this.swiperContainerEl as HTMLElement).isConnected ||
          (this.swiperContainerEl as HTMLElement).getBoundingClientRect()
            .width === 0) &&
        attempts < MAX_DOM_ATTEMPTS
      ) {
        // Give the renderer some time to mount/unmount nodes.
        await tick();
        await new Promise((res) => setTimeout(res, 80));
        attempts += 1;
      }

      const el = this.swiperContainerEl as HTMLElement | null;
      if (!el) {
        const possibleNode = document.querySelector(
          ".job-carousel",
        ) as HTMLElement | null;
        if (possibleNode) possibleNode.classList.remove("invisible");
        return;
      }

      // Wait a final tick to ensure slides/content have rendered before init.
      await tick();

      // Recreate the instance (this function lazy-loads Swiper JS)
      await this.createSwiper();
      this.equalizeCardHeights();

      // After attempting to initialize, ensure the carousel is visible even if
      // initialization failed. createSwiper removes "invisible" on success/failure
      // but in case it returned early elsewhere ensure we remove it here.
      el.classList.remove("invisible");
    }

    // Refresh handler: fetch fresh data and reinitialize the carousel
    async refreshCarousel() {
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
        const data = await APIService.fetchCarouselGraphQL();
        jobs = data?.jobs ?? null;
        this.error = null;
      } catch (e) {
        console.error("Error refreshing carousel:", e);
        this.error = "Failed to refresh carousel";
      } finally {
        // Ensure the loading state is cleared before attempting to reinitialize
        // the Swiper instance so the DOM element for the carousel is mounted.
        this.isRefreshing = false;
      }

      // Give Svelte one tick to update the DOM now that `isRefreshing` is false,
      // then reinitialize the Swiper. This ensures the carousel element exists
      // and that `.invisible` will be removed by the initializer.
      await tick();
      await this.reinitializeSwiper({ forceDestroy: true });
    }

    // Keep Swiper in sync when job list updates
    updateSwiperOnJobsChange(): void {
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

    destroySwiper(): void {
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
    public static async handleClickNavigateToJob(
      slug: string,
      permalink: CardJob["permalink"],
      job: CardJob,
    ): Promise<void> {
      this.carouselSaveCurrentSlideState();
      await this.handlePlatformSpecificNavigation(slug, permalink, job);
    }
    private static carouselSaveCurrentSlideState(): void {
      if (!swiperManager.swiperInstance) return;

      // Try to capture the current wrapper translateX so virtual positioning
      // can be restored exactly after navigation. Falls back to 0 on errors.
      let offset = 0;
      const wrapper = swiperManager.swiperContainerEl?.querySelector(
        ".swiper-wrapper",
      ) as HTMLElement | null;
      if (wrapper) {
        const transform =
          wrapper.style.transform || getComputedStyle(wrapper).transform || "";
        // translate3d(xpx, ypx, zpx)
        const t3 = transform.match(
          /translate3d\((-?\d+(?:\.\d+)?)px,\s*(-?\d+(?:\.\d+)?)px,\s*(-?\d+(?:\.\d+)?)px\)/,
        );
        if (t3) {
          offset = Number(t3[1]);
        } else {
          // matrix(a,b,c,d,tx,ty) -> tx is index 4
          const m = transform.match(/matrix\(([^)]+)\)/);
          if (m) {
            const parts = m[1].split(",").map((s) => s.trim());
            if (parts.length >= 6) {
              offset = Number(parts[4]) || 0;
            }
          }
        }
      }

      routeStateStore.saveCarouselState({
        slideIndex: swiperManager.swiperInstance?.activeIndex ?? 0,
        offset: offset,
      });
    }

    private static handlePlatformSpecificNavigation(
      slug: string,
      permalink: CardJob["permalink"],
      job: CardJob,
    ): void {
      if (typeof window !== "undefined" && window.innerWidth >= 768) {
        // Desktop: open overlay
        const jobgridElement = isJobGridEl();
        jobOverlay.openOverlay(slug, job, "carousel");
        jobgridElement?.scrollIntoView({ behavior: "smooth", block: "start" });
      } else {
        // Mobile: mark visited for carousel then use SPA navigation to SingleLowongan.svelte route
        routeStateStore.MarkVisitedJob(slug, "carousel");
        const url = new URL(String(permalink), window.location.origin);
        void GlobalNavigateTo(url.pathname + url.search + url.hash);
      }
    }
  }
  // Derive a simple jobs count to drive updates without reacting to every
  // internal jobs change. This lets us limit side-effectful $effect usage.
  const jobsCount = $derived.by(() => jobs?.length ?? 0);

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

  // Equalize card heights when active slide changes (for virtualization updates)
  $effect(() => {
    swiperManager.activeIndex;
    requestAnimationFrame(() => swiperManager.equalizeCardHeights());
  });

  $effect(() => {
    breakpoint;
    const bp = breakpoint as string;
    if (bp !== lastBreakpoint) {
      lastBreakpoint = bp;
      if (swiperManager.resizeTimer) clearTimeout(swiperManager.resizeTimer);
      swiperManager.resizeTimer = setTimeout(() => {
        void swiperManager.reinitializeSwiper({ forceDestroy: true });
        swiperManager.equalizeCardHeights();
      }, 50);
    }
  });

  onMount(() => {
    // Eagerly attempt to initialize Swiper after bindings settle.
    void tick().then(() => void swiperManager.createSwiper());
  });

  onDestroy(() => {
    if (swiperManager.resizeTimer) clearTimeout(swiperManager.resizeTimer);
    void swiperManager.destroySwiper();
  });
</script>

<section class="min-h-[450px] md:min-h-[400px] lg:min-h-[500px]">
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
      class="swiper job-carousel invisible"
      role="region"
      aria-label={title}
      aria-live="polite"
    >
      <div class="swiper-wrapper">
        {#each swiperManager.virtualIndexes as idx (jobs[idx]?.id ?? jobs[idx]?.permalink ?? idx)}
          {@const job = jobs[idx]}
          <div
            class="swiper-slide"
            data-swiper-slide-index={idx}
            style={`transform: translate3d(${swiperManager.virtualData.offset ?? 0}px, 0, 0); `}
          >
            <JobCard
              jobdata={job}
              permalink={job.permalink}
              isVisited={routeStateStore.hasVisitedJob(
                job.slug ?? "",
                "carousel",
              )}
              variant="carousel"
              onClick={(slug: string) =>
                CarouselNavigationHandler.handleClickNavigateToJob(
                  slug,
                  job.permalink ?? "",
                  job,
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
                isVisited={routeStateStore.hasVisitedJob(
                  job.slug ?? "",
                  "carousel",
                )}
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
