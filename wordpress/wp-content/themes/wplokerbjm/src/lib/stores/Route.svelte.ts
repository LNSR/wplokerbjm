import type { SearchState, CarouselState, JobCardProps } from "@/types";
import { isMobile } from "$lib/utils/elements.svelte";
import { SvelteMap } from "svelte/reactivity";
import { WPPostRoute } from "@/types";
import { type CardJob } from "@/types";
import { LRUCache } from "lru-cache";
import type { goto } from "$app/navigation";


export class RouteManager {
  isInitialLoad = $state(true);
  isLoading = $state(false);
  isTransitioningRoute = $state(false);

  setIsInitialLoad(value: boolean) {
    this.isInitialLoad = value;
  }

  setIsLoading(loading: boolean) {
    this.isLoading = loading;
  }

  /**
   * Map a request path to the internal component name used by the SPA logic.
   * This is only used by a handful of helpers (SEO, job grid) and mirrors
   * the switch in the legacy app.  Keeping it here avoids pulling the
   * mapping into multiple packages.
   */
  getComponentNamePath(path: string): string {
    if (path === "/") return "Homepage";
    if (path.startsWith(`/${WPPostRoute.PasangIklanLoker}`))
      return "PasangIklanLoker";
    if (path.startsWith(`/${WPPostRoute.lowongan}`)) return "SingleLowongan";
    if (path.startsWith(`/${WPPostRoute.KebijakanPrivacy}`))
      return "KebijakanPrivasi";
    return "Unknown";
  }
}
type Device = "desktop" | "mobile";
export class RouteStateManager {
  scrollPositions = new LRUCache<string, number>({ max: 100 }); // Scroll position cache per route
  searchStates = new LRUCache<string, SearchState>({ max: 100 }); // Limit to 50 most recent search states
  lastVisitedJob: CardJob["slug"] | undefined = $state(undefined); // Remember the last visited job slug for mobile navigation
  // Variant source for the last visited job: 'carousel' | 'grid' | undefined
  lastVisitedJobSource: JobCardProps["variant"] | undefined = $state(undefined);
  carouselState: CarouselState | null = $state(null);
  skipScrollRestore = new LRUCache<string, boolean>({ max: 100 }); // Skip scroll restore for specific routes
  cardHeights = new LRUCache<string, Record<number, number>>({ max: 500 }); // Global cache for card heights

  #currentDevice: Device = $derived.by(() => (isMobile() ? "mobile" : "desktop"));
  #prevDevice: Device | undefined = undefined;
  effectCleanup: (() => void) | undefined;

  /**
   * track breakpoint for better DX during development
   */
  observeBreakpointChanges = () => {
    if (this.effectCleanup) return; // Skip if already observing

    // Set up the effect and store the cleanup function
    this.effectCleanup = $effect.root(() => {
      $effect.pre(() => {
        if (this.#currentDevice !== this.#prevDevice) {
          $inspect("Previous device", this.#prevDevice, ", Current device", this.#currentDevice);
          this.clearCachesForDevice();
          this.#prevDevice = this.#currentDevice;
        }
      });
    });
  };

  /**
   * Clean up the breakpoint tracking effect when no longer needed
   */
  cleanUpEffectObserveBreakpointChanges() {
    if (this.effectCleanup) {
      void this.effectCleanup();
      this.effectCleanup = undefined;
    }
  }

  setInitialDevice(device: Device) {
    this.#prevDevice = device;
  }

  /**
   * When using simulation of mobile/desktop in devtools, clear caches to prevent cross-device data issues
   */
  private clearCachesForDevice() {
    // Clear all LRU caches
    this.scrollPositions.clear();
    this.searchStates.clear();
    this.skipScrollRestore.clear();
    this.cardHeights.clear();

    // Clear all sessionStorage
    if (typeof sessionStorage !== "undefined") {
      sessionStorage.clear();
    }

    // Clear state properties
    this.lastVisitedJob = undefined;
    this.lastVisitedJobSource = undefined;
    this.carouselState = null;
  }

  saveSearchState(path: string, searchState: SearchState) {
    const key = `${this.#currentDevice}-${path}`;
    this.searchStates.set(key, searchState);
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.setItem(
          `searchStates-${key}`,
          JSON.stringify(searchState),
        );
      } catch (e) {
        console.warn("Failed to save search state to sessionStorage", e);
      }
    }
  }

  getSearchState(path: string): SearchState | undefined {
    const key = `${this.#currentDevice}-${path}`;
    let state = this.searchStates.get(key);
    if (!state && typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(`searchStates-${key}`);
        if (stored) {
          const parsed = JSON.parse(stored) as SearchState;
          this.searchStates.set(key, parsed); // cache in memory
          state = parsed;
        }
      } catch (e) {
        console.warn("Failed to load search state from sessionStorage", e);
      }
    }
    return state;
  }

  clearSearchState(path: string) {
    const key = `${this.#currentDevice}-${path}`;
    this.searchStates.delete(key);
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.removeItem(`searchStates-${key}`);
      } catch (e) {
        console.warn("Failed to clear search state from sessionStorage", e);
      }
    }
  }

  saveCarouselState(carouselState: CarouselState) {
    this.carouselState = carouselState;
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.setItem(
          `carouselState-${this.#currentDevice}`,
          JSON.stringify(carouselState),
        );
      } catch (e) {
        console.warn("Failed to save carousel state to sessionStorage", e);
      }
    }
  }

  getCarouselState(): CarouselState | undefined {
    if (this.carouselState) return this.carouselState;
    if (typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(
          `carouselState-${this.#currentDevice}`,
        );
        if (stored) {
          this.carouselState = JSON.parse(stored) as CarouselState;
          return this.carouselState;
        }
      } catch (e) {
        console.warn("Failed to load carousel state from sessionStorage", e);
      }
    }
    return undefined;
  }

  clearCarouselState() {
    if (!this.carouselState) return;
    this.carouselState = null;
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.removeItem(`carouselState-${this.#currentDevice}`);
      } catch (e) {
        console.warn("Failed to clear carousel state from sessionStorage", e);
      }
    }
  }

  // Mark a job slug as the last visited for mobile navigation.
  // `source` indicates where the user clicked the job (carousel or grid).
  MarkVisitedJob(slug: CardJob["slug"], source?: JobCardProps["variant"]): void {
    if (!slug) return;
    this.lastVisitedJob = slug;
    this.lastVisitedJobSource = source;
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.setItem(`lastVisitedJob-${this.#currentDevice}`, slug);
        sessionStorage.setItem(
          `lastVisitedJobSource-${this.#currentDevice}`,
          source ?? "",
        );
      } catch (e) {
        console.warn("Failed to save last visited job to sessionStorage", e);
      }
    }
  }

  // Check if a job slug is the last visited for mobile navigation.
  // If `source` is provided, only return true when the stored source matches.
  hasVisitedJob(slug: CardJob["slug"], source?: JobCardProps["variant"]): boolean {
    if (!slug) return false;
    if (this.lastVisitedJob === slug) {
      if (!source) return true;
      return this.lastVisitedJobSource === source;
    }
    if (typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(
          `lastVisitedJob-${this.#currentDevice}`,
        );
        if (stored && stored === slug) {
          this.lastVisitedJob = stored;
          // Try to restore stored source if available
          try {
            const src = sessionStorage.getItem(
              `lastVisitedJobSource-${this.#currentDevice}`,
            ) as JobCardProps["variant"] | null;
            if (src) this.lastVisitedJobSource = src;
          } catch { }
          if (!source) return true;
          return this.lastVisitedJobSource === source;
        }
      } catch (e) {
        console.warn("Failed to load last visited job from sessionStorage", e);
      }
    }
    return false;
  }

  restoreVisitedJob(): CardJob["slug"] | undefined {
    if (this.lastVisitedJob) return this.lastVisitedJob;
    if (typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(
          `lastVisitedJob-${this.#currentDevice}`,
        );
        if (stored) {
          this.lastVisitedJob = stored;
          const src = sessionStorage.getItem(
            `lastVisitedJobSource-${this.#currentDevice}`,
          ) as JobCardProps["variant"] | null;
          if (src) this.lastVisitedJobSource = src;
          return this.lastVisitedJob;
        }
      } catch (e) {
        console.error(
          "Failed to load last visited job from sessionStorage:",
          e,
        );
        return undefined;
      }
    }
    return undefined;
  }

  saveCardHeights(
    heights: SvelteMap<number, number>, 
    keyname: string = "global",
  ) {
    // Accept either SvelteMap or native Map and persist as a plain record for storage
    const record = Object.fromEntries(
      heights as Iterable<readonly [number, number]>,
    );
    const key = `${this.#currentDevice}-${keyname}`;
    this.cardHeights.set(key, record);
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.setItem(`cardHeights-${key}`, JSON.stringify(record));
      } catch (e) {
        console.warn("Failed to save cardHeights to sessionStorage", e);
      }
    }
  }

  getCardHeights(keyname: string = "global"): SvelteMap<number, number> {
    const key = `${this.#currentDevice}-${keyname}`;
    let record = this.cardHeights.get(key);
    if (!record && typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(`cardHeights-${key}`);
        if (stored) {
          record = JSON.parse(stored);
          this.cardHeights.set(key, record);
        }
      } catch (e) {
        console.warn("Failed to load cardHeights from sessionStorage", e);
      }
    }

    const entries: Array<[number, number]> = record
      ? Object.entries(record).map(([k, v]) => [Number(k), Number(v)])
      : [];

    return new SvelteMap<number, number>(entries);
  }

  /**
   * Save the current scroll position for a route path
   */
  saveScrollPosition(path: string, scrollY: number): void {
    const key = `${this.#currentDevice}-${path}`;
    this.scrollPositions.set(key, scrollY);
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.setItem(`scrollPosition-${key}`, String(scrollY));
      } catch (e) {
        console.warn("Failed to save scroll position to sessionStorage", e);
      }
    }
  }

  /**
   * Get the saved scroll position for a route path
   */
  getScrollPosition(path: string): number | undefined {
    const key = `${this.#currentDevice}-${path}`;
    let position = this.scrollPositions.get(key);
    if (position === undefined && typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(`scrollPosition-${key}`);
        if (stored) {
          position = Number(stored);
          if (!isNaN(position)) {
            this.scrollPositions.set(key, position);
          } else {
            position = undefined;
          }
        }
      } catch (e) {
        console.warn("Failed to load scroll position from sessionStorage", e);
      }
    }
    return position;
  }

  /**
   * Restore scroll position for a route path with smooth scrolling
   * @param path - The route path
   * @param delay - Optional delay before scrolling (default: 0ms)
   */
  restoreScrollPosition(path: string, delay: number = 0): void {
    const key = `${this.#currentDevice}-${path}`;
    if (this.skipScrollRestore.get(key) && routeStore.isInitialLoad) {
      this.skipScrollRestore.delete(key);
      return;
    }

    const position = this.getScrollPosition(path);
    if (position !== undefined && typeof window !== "undefined") {
      setTimeout(() => {
        window.scrollTo({
          top: position,
          behavior: "instant",
        });
      }, delay);
    }
  }

  /**
   * Mark a route to skip scroll restoration on next visit
   */
  setSkipScrollRestore(path: string): void {
    const key = `${this.#currentDevice}-${path}`;
    this.skipScrollRestore.set(key, true);
  }

  /**
   * Clear scroll position for a route path
   */
  clearScrollPosition(path: string): void {
    const key = `${this.#currentDevice}-${path}`;
    this.scrollPositions.delete(key);
    if (typeof sessionStorage !== "undefined") {
      try {
        sessionStorage.removeItem(`scrollPosition-${key}`);
      } catch (e) {
        console.warn("Failed to clear scroll position from sessionStorage", e);
      }
    }
  }
}

export const routeStore = new RouteManager();
export const routeStateStore = new RouteStateManager();
export type GotoOptions = Parameters<typeof goto>[1];
/** Navigate to a new path within the SPA.
 * @param path The target path to navigate to.
 * @param gotoOpts Optional SvelteKit goto options to control navigation behavior (e.g. replaceState, noScroll, etc.).
 */
export function GlobalNavigateTo(path: string, gotoOpts?: GotoOptions) {

  routeStore.setIsInitialLoad(false);
  routeStore.setIsLoading(true);
  routeStore.isTransitioningRoute = true;

  // Use SvelteKit's goto so Kit handles fetch/history; afterNavigate will run post-navigation side-effects
  void (async () => {
    try {
      const { goto } = await import("$app/navigation");
      await goto(path, gotoOpts);
    } catch (e) {
      console.error("goto failed for", path, e);
    }
  })();
}
