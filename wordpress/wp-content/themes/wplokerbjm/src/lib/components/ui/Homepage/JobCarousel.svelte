<script module lang="ts">
  let swiperInstance: Swiper | null = null;
  let swiperContainerEl = $state<HTMLElement | null>(null);
  let nextButtonEl = $state<HTMLElement | null>(null);
  let prevButtonEl = $state<HTMLElement | null>(null);
  let isInitializing = $state(false);
  let swiperFailed = $state(false);
  let activeIndex = $state(0);
  let carouselData = $state<{ jobs: CardJob[]; totalJobs?: number } | null>(
    null,
  );
  let isLoading = $state(false);
  let error = $state<string | null>(null);
  let isRefreshing = $state(false);
  let resizeTimer: ReturnType<typeof setTimeout> | null = null;
  let lastBreakpoint = $state("");
  let pendingVirtualOffset: number | null = null;
  let reappliedSavedOffset = false;

  type SwiperVirtualExternalData = {
    from: number;
    to: number;
    offset: number;
  };

  let virtualData = $state<SwiperVirtualExternalData>({
    from: 0,
    to: -1,
    offset: 0,
  });

  function equalizeCardHeights() {
    if (!swiperContainerEl) return;
    const slides = swiperContainerEl.querySelectorAll(".swiper-slide");
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
</script>

<script lang="ts">
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import {
    GlobalNavigateTo,
    routeStateStore,
    routeStore,
  } from "$lib/stores/Route.svelte";
  import type { CardJob } from "@/types";
  import { APIService } from "@/services/APIService";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { onMount, onDestroy, tick } from "svelte";
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
  const { jobs: initialpropJobs = [], title = "Lowongan Darurat" } = $props<{
    jobs?: CardJob[];
    title?: string;
  }>();

  const jobs = $derived(
    initialpropJobs.length > 0 ? initialpropJobs : (carouselData?.jobs ?? []),
  );

  const breakpoint = $derived.by(() => {
    const w = innerWidth.current ?? 0;
    return w >= 1024 ? "lg" : w >= 640 ? "md" : "sm";
  });

  const virtualIndexes = $derived.by(() => {
    const total = jobs?.length ?? 0;
    if (total <= 0) return [] as number[];

    const from = Math.max(0, Math.min(total - 1, virtualData.from ?? 0));
    const to = Math.max(from - 1, Math.min(total - 1, virtualData.to ?? -1));
    if (to < from) return [] as number[];

    const idxs: number[] = [];
    for (let i = from; i <= to; i += 1) idxs.push(i);
    return idxs;
  });

  class SwiperManager {
    private static createSwiperConfig(
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null,
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
        watchslidesProgress: true,
        // Favor passive listeners and avoid forcing touchstart preventDefault to reduce touch input latency
        passiveListeners: true,
        touchStartPreventDefault: false,
        touchStartForcePreventDefault: false,
        virtual: {
          enabled: true,
          slides: Array.from({ length: jobs.length }, (_, i) => i),
          addSlidesBefore: 1,
          addSlidesAfter: 1,
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
            if (pendingVirtualOffset !== null) {
              next.offset = pendingVirtualOffset;
              pendingVirtualOffset = null;
            }

            // Avoid excessive state writes during Swiper's internal loops.
            if (
              virtualData.from === next.from &&
              virtualData.to === next.to &&
              virtualData.offset === next.offset
            ) {
              return;
            }
            virtualData = next;

            // If we have a previously saved state with an offset, reapply its
            // translate after Swiper has provided its own virtual offsets. This
            // helps keep visual position stable when Swiper updates virtualData
            // after initialization. Only do this once per init to avoid thrash.
            try {
              const savedStateNow = routeStateStore.getCarouselState();
              if (
                savedStateNow &&
                typeof (savedStateNow as any).offset === "number" &&
                !reappliedSavedOffset
              ) {
                const savedOffsetNow = (savedStateNow as any).offset;
                // Schedule re-apply on next RAFs to let Swiper finish its work.
                // Ensure Svelte has flushed DOM updates for virtual slides before
                // reapplying the wrapper translate.
                try {
                  tick().then(() => {
                    requestAnimationFrame(() => {
                      try {
                        const wrapper = (
                          swiperContainerEl as HTMLElement | null
                        )?.querySelector(
                          ".swiper-wrapper",
                        ) as HTMLElement | null;
                        if (
                          swiperInstance &&
                          typeof (swiperInstance as any)?.setTranslate ===
                            "function"
                        ) {
                          try {
                            (swiperInstance as any).setTranslate(
                              savedOffsetNow,
                            );
                          } catch {}
                        } else if (wrapper) {
                          try {
                            wrapper.style.transform = `translate3d(${savedOffsetNow}px, 0, 0)`;
                          } catch {}
                        }
                      } catch {}
                    });
                  });
                } catch {}
                reappliedSavedOffset = true;
              }
            } catch {}
          },
        },
        on: {
          slideChange: (swiper: Swiper) => {
            activeIndex = swiper.activeIndex;
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

    private static async waitForSlidesAndWidth(
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
          try {
            el.classList.remove("invisible");
          } catch {
            // ignore
          }
          // If we timed out, mark swiper as failed to fall back to a static grid.
          swiperFailed = true;
          return false;
        }

        await new Promise((res) => setTimeout(res, 120));
      }
    }

    private static async initializeSwiperInstance(
      el: HTMLElement,
      paginationEl: HTMLElement | null,
      nextEl: HTMLElement | null,
      prevEl: HTMLElement | null,
    ): Promise<void> {
      try {
        try {
          if (SwiperCore && (SwiperCore as typeof SwiperCore).use) {
            SwiperCore.use([Navigation, Pagination, Autoplay, Virtual]);
          }
        } catch {}

        // If there is a saved carousel state, set `virtualData` first so
        // Svelte renders slides with the saved offset before Swiper measures
        // layout. This avoids having to forcibly set per-slide transforms
        // after initialization (which would overwrite Swiper's own values).
        const savedState = routeStateStore.getCarouselState();
        if (savedState && typeof savedState.offset === "number") {
          try {
            // Debug: log saved state before applying (snapshot reactive proxies to avoid Svelte proxy warnings)
            // debug info removed

            virtualData = {
              from: virtualData?.from ?? 0,
              to: virtualData?.to ?? -1,
              offset: savedState.offset,
            } as typeof virtualData;
            // Apply pending offset so Swiper's first renderExternal uses it.
            pendingVirtualOffset = savedState.offset;
            // debug info removed
          } catch {}
        }

        const cfg = SwiperManager.createSwiperConfig(
          paginationEl,
          nextEl,
          prevEl,
        );
        const finalCfg = {
          ...(cfg || {}),
          modules: [Navigation, Pagination, Autoplay, Virtual],
        };

        swiperInstance = new SwiperCore(el, finalCfg);

        // Give Svelte a tick so any virtual slides / inline transforms from
        // `virtualData` are painted before we attempt to call Swiper APIs that
        // rely on DOM measurements. This prevents fighting Swiper's internal
        // layout updates and makes our applyTranslate more deterministic.
        try {
          await tick();
        } catch {}

        // After creating Swiper, navigate to the saved slide index (if any)
        // and then clear the stored state so we don't reapply it later.
        if (savedState) {
          try {
            swiperInstance.slideTo(savedState.slideIndex);
          } catch {}
          try {
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
                try {
                  if (typeof swiperInstance?.setTranslate === "function") {
                    try {
                      swiperInstance.setTranslate(savedOffset);
                      swiperInstance?.update?.();
                      swiperInstance?.updateProgress?.();
                      swiperInstance?.updateSlidesClasses?.();
                    } catch {}
                  } else {
                    const wrapper = el.querySelector(
                      ".swiper-wrapper",
                    ) as HTMLElement | null;
                    if (wrapper)
                      wrapper.style.transform = `translate3d(${savedOffset}px, 0, 0)`;
                  }
                } catch {}
              };

              try {
                requestAnimationFrame(() => {
                  applyTranslate();
                  requestAnimationFrame(() => {
                    applyTranslate();
                    // Final attempt after a short delay in case Swiper triggers async updates
                    setTimeout(() => applyTranslate(), 40);
                  });
                });
              } catch {}
            }
          } catch {}
        }

        await tick();
        return;
      } catch {
        throw new Error("Failed to initialize Swiper");
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
      } catch {}

      const paginationEl = el.querySelector(
        ".swiper-pagination",
      ) as HTMLElement | null;

      const nextEl = (nextButtonEl ??
        el.parentElement?.querySelector(
          ".job-carousel-next",
        )) as HTMLElement | null;
      const prevEl = (prevButtonEl ??
        el.parentElement?.querySelector(
          ".job-carousel-prev",
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
        } catch {
          // ignore
        }
        swiperInstance = null;
      }

      try {
        await SwiperManager.initializeSwiperInstance(
          el,
          paginationEl,
          nextEl,
          prevEl,
        );

        swiperFailed = false;
        try {
          el.classList.remove("no-swiper");
        } catch {}
        equalizeCardHeights();
      } catch {
        swiperFailed = true;
        try {
          el.classList.add("no-swiper");
        } catch {}
      } finally {
        try {
          el.classList.remove("invisible");
        } catch {}
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
        } catch {
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
            ".job-carousel",
          ) as HTMLElement | null;
          if (possibleNode) possibleNode.classList.remove("invisible");
        } catch {
          // ignore
        }
        return;
      }

      // Wait a final tick to ensure slides/content have rendered before init.
      await tick();

      // Recreate the instance (this function lazy-loads Swiper JS)
      await SwiperManager.createSwiper();
      equalizeCardHeights();

      // After attempting to initialize, ensure the carousel is visible even if
      // initialization failed. createSwiper removes "invisible" on success/failure
      // but in case it returned early elsewhere ensure we remove it here.
      try {
        el.classList.remove("invisible");
      } catch {
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
          } catch {
            // ignore
          }
          swiperInstance = null;
        }
        // Fetch fresh carousel data
        const data = await APIService.fetchCarouselGraphQL();
        carouselData = data ?? null;
        error = null;
      } catch {
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
    static fetchCarouselData(): void {
      if (initialpropJobs.length === 0 && !carouselData && !isLoading) {
        isLoading = true;
        new Promise<void>(async (resolve) => {
          try {
            const data = await APIService.fetchCarouselGraphQL();
            carouselData = data ?? null;
            error = null;
          } catch {
            error = "Failed to load carousel data";
          } finally {
            isLoading = false;
            resolve();
          }
        });
      }
    }

    // Keep Swiper in sync when job list updates
    static updateSwiperOnJobsChange(): void {
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

      // Update Swiper Virtual slides in-place (much cheaper than re-init for 100+ items).
      try {
        if (swiperInstance.virtual) {
          swiperInstance.virtual.slides = Array.from(
            { length: count },
            (_, i) => i,
          );
          swiperInstance.virtual.update(true);
        }
        swiperInstance.update();
      } catch {
        // As a safe fallback, try a full re-init.
        requestAnimationFrame(() => SwiperManager.reinitializeSwiper());
      }
    }

    static destroySwiper(): void {
      if (swiperInstance) {
        try {
          swiperInstance.destroy(true, true);
        } catch {
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
      job: CardJob,
    ): Promise<void> {
      this.carouselSaveCurrentSlideState();
      await this.handlePlatformSpecificNavigation(slug, permalink, job);
    }
    private static carouselSaveCurrentSlideState(): void {
      if (!swiperInstance) return;

      // Try to capture the current wrapper translateX so virtual positioning
      // can be restored exactly after navigation. Falls back to 0 on errors.
      let offset = 0;
      try {
        const wrapper = swiperContainerEl?.querySelector(
          ".swiper-wrapper",
        ) as HTMLElement | null;
        if (wrapper) {
          const transform =
            wrapper.style.transform ||
            getComputedStyle(wrapper).transform ||
            "";
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
      } catch {
        offset = 0;
      }

      routeStateStore.saveCarouselState({
        slideIndex: swiperInstance?.activeIndex ?? 0,
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
        try {
          routeStateStore.MarkVisitedJob(slug, "carousel");
        } catch (err) {
          // swallow — best-effort marking
          console.warn("Failed to mark visited job (carousel)", err);
        }
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
    void SwiperManager.updateSwiperOnJobsChange();
  });

  // When Swiper Virtual asks us to render a new range, wait for Svelte to paint
  // those slides, then notify Swiper so it can correctly measure/update classes.
  $effect(() => {
    virtualData;
    if (!swiperInstance) return;
    void tick().then(() => {
      try {
        swiperInstance?.updateSlides();
        swiperInstance?.updateProgress();
        swiperInstance?.updateSlidesClasses();
      } catch {
        // ignore
      }
    });
  });

  // Equalize card heights when active slide changes (for virtualization updates)
  $effect(() => {
    activeIndex;
    tick().then(() => equalizeCardHeights());
  });

  $effect(() => {
    breakpoint;
    const bp = breakpoint as string;
    if (bp !== lastBreakpoint) {
      lastBreakpoint = bp;
      if (resizeTimer) clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        void SwiperManager.reinitializeSwiper({ forceDestroy: true });
        equalizeCardHeights();
      }, 120);
    }
  });
  onMount(() => {
    if (initialpropJobs && initialpropJobs.length > 0) {
      if (!carouselData || (carouselData.jobs ?? []).length === 0) {
        carouselData = {
          jobs: initialpropJobs,
          totalJobs: initialpropJobs.length,
        };
      }
    }
    requestAnimationFrame(() => void SwiperManager.createSwiper());
    void SwiperManager.fetchCarouselData();
  });

  onDestroy(() => {
    if (resizeTimer) clearTimeout(resizeTimer);
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
        {#each virtualIndexes as idx (jobs[idx]?.id ?? jobs[idx]?.permalink ?? idx)}
          {@const job = jobs[idx]}
          <div
            class="swiper-slide"
            data-swiper-slide-index={idx}
            style={`transform: translate3d(${virtualData.offset ?? 0}px, 0, 0); `}
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
    {#if swiperFailed}
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
