import type { SearchState, CarouselState, JobCardProps } from "@/types";
import { isMobile } from "$lib/utils/elements.svelte";
import { SvelteMap } from "svelte/reactivity";
import { type CardJob } from "@/types";
import { LRUCache } from "lru-cache";
import typia from "typia";
import { dev } from "$app/environment";

type Device = "desktop" | "mobile";

class RouteManager {
  isInitialLoad = $state(true);
  isLoading = $state(false);
  isTransitioningRoute = $state(false);

  setIsInitialLoad(value: boolean) {
    this.isInitialLoad = value;
  }

  setIsLoading(loading: boolean) {
    this.isLoading = loading;
  }

  setIsTransitioningRoute(transitioning: boolean) {
    this.isTransitioningRoute = transitioning;
  }

}

class RouteStateManager {
  scrollPositions = new LRUCache<string, number>({ max: 100 }); // Scroll position cache per route
  searchStates = new LRUCache<string, SearchState>({ max: 100 }); // Limit to 50 most recent search states
  lastVisitedJob: CardJob["slug"] | undefined = $state(undefined); // Remember the last visited job slug for mobile navigation
  lastVisitedJobSource: JobCardProps["variant"] | undefined = $state(undefined);
  carouselState: CarouselState | undefined = $state(undefined);
  cardHeights = new LRUCache<string, Record<number, number>>({ max: 500 }); // Global cache for card heights
  #currentDevice = $derived.by<Device>(() => (isMobile() ? "mobile" : "desktop"));
  #prevDevice: Device | undefined;
  effectCleanup: (() => void) | undefined = undefined;

  /**
   * track breakpoint for better DX during development
   */
  public observeBreakpointChanges(): (() => void) | undefined {
    if (this.effectCleanup || !dev) return; // Skip if already observing or not in development mode

    this.effectCleanup = $effect.root(() => {
      $inspect("Previous device", this.#prevDevice, ", Current device", this.#currentDevice);
      $effect.pre(() => {
        if (this.#currentDevice !== this.#prevDevice) {
          this.clearCachesForDevice();
          this.#prevDevice = this.#currentDevice;
        }
      });
    });

    /**
   * Clean up the breakpoint tracking effect when no longer needed
   */
    const cleanUpEffectObserveBreakpointChanges = () => {
      if (this.effectCleanup) {
        this.effectCleanup();
        this.effectCleanup = undefined;
      }
    }

    return () => {
      cleanUpEffectObserveBreakpointChanges();
    };
  }

  set setInitialDevice(device: Device) {
    if (!typia.is<Device>(device)) {
      console.warn("Invalid device type provided to setInitialDevice:", device);
      return;
    }
    this.#currentDevice = device;
  }

  get getCurrentDevice(): Device {
    return this.#currentDevice;
  }

  /**
   * When using simulation of mobile/desktop in devtools, clear caches to prevent cross-device data issues
   */
  private clearCachesForDevice() {
    // Clear all LRU caches
    this.scrollPositions.clear();
    this.searchStates.clear();
    this.cardHeights.clear();

    // Clear all sessionStorage
    if (typeof sessionStorage !== "undefined") {
      sessionStorage.clear();
    }

    // Clear state properties
    this.lastVisitedJob = undefined;
    this.lastVisitedJobSource = undefined;
    this.carouselState = undefined;
  }

  public saveSearchState(path: string, searchState: SearchState | undefined): void {
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

  public getSearchState(path: string): SearchState | undefined {
    const key = `${this.#currentDevice}-${path}`;
    let state = this.searchStates.get(key);
    if (!state && typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(`searchStates-${key}`);
        if (stored) {
          const parsed = JSON.parse(stored);
          this.searchStates.set(key, parsed); // cache in memory
          state = parsed;
        }
      } catch (e) {
        console.warn("Failed to load search state from sessionStorage", e);
      }
    }
    return state;
  }

  public clearSearchState(path: string) {
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

  public saveCarouselState(carouselState: CarouselState) {
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

  public getCarouselState(): CarouselState | undefined {
    if (this.carouselState) return this.carouselState;
    if (typeof sessionStorage !== "undefined") {
      try {
        const stored = sessionStorage.getItem(
          `carouselState-${this.#currentDevice}`,
        );
        if (stored) {
          this.carouselState = JSON.parse(stored);
          return this.carouselState;
        }
      } catch (e) {
        console.warn("Failed to load carousel state from sessionStorage", e);
      }
    }
    return undefined;
  }

  public clearCarouselState() {
    if (!this.carouselState) return;
    this.carouselState = undefined;
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
  public MarkVisitedJob(slug: CardJob["slug"], source?: JobCardProps["variant"]): void {
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

  public restoreVisitedJob(): CardJob["slug"] | undefined {
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

  public saveCardHeights(
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

  public getCardHeights(keyname: string = "global"): SvelteMap<number, number> {
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
  public saveScrollPosition(path: string, scrollY: number): void {
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
  public getScrollPosition(path: string): number | undefined {
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
  public restoreScrollPosition(path: string, delay: number = 0): void {
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
   * Clear scroll position for a route path
   */
  public clearScrollPosition(path: string): void {
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