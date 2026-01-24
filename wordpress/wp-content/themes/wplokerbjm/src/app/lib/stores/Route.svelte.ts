import type { SearchState, CarouselState } from '@/types'
import { WPPostRoute } from '@/types'
import { type Component } from 'svelte'
import { SvelteURL } from 'svelte/reactivity'
import { utilsSEO } from '$lib/utils/SEO.svelte'
import { GoogleServices } from '@/services/Google'
import { scrollY } from 'svelte/reactivity/window';
import { getThemeData } from '@/utils'
import type { CardJob } from '@/types'
import { LRUCache } from 'lru-cache'

export class RouteManager {
  currentUrl = $state(new SvelteURL(window.location.href));
  isInitialLoad = $state(true); // Indicates if the page result from SSR
  isLoading = $state(false);
  loadingComponent = $state<string | null>(null);
  CurrentComponent: Component | null = $state(null);
  isTransitioningRoute = $state(false); // Indicates if a route transition is in progress
  elRouteContainer: HTMLElement | null = null; // `.route-container` element reference
  setTimeoutWillChange: number | undefined = undefined; // Timeout ID for removing will-change styles
  currentViewTransition: ViewTransition | null = $state(null); // prevent duplicate transitions
  lockViewTransition: boolean = $state(false); // prevent multiple View Transitions

  setCurrentPath(path: string) {
    this.currentUrl.href = new SvelteURL(path, this.currentUrl.origin).href;
  }

  get getOrigin(): string {
    return this.currentUrl.origin;
  }

  setIsInitialLoad(value: boolean) {
    this.isInitialLoad = value;
  }

  setIsLoading(loading: boolean, component?: string) {
    this.isLoading = loading;
    this.loadingComponent = component || null;
  }

  getComponentNamePath(path: string): string {
    if (path === '/') return 'Homepage';
    if (path.startsWith(`/${WPPostRoute.PasangIklanLoker}`)) return 'PasangIklanLoker';
    if (path.startsWith(`/${WPPostRoute.lowongan}`)) return 'SingleLowongan';
    return 'Unknown';
  }

  performRouteTransitionSideEffects(path: string): void {
    utilsSEO.clearPendingJobSchemas();

    // Fetch RankMath head data
    utilsSEO.fetchHeadData(path).then(() => {
      utilsSEO.removeJobPostingJsonLd();
      // Head data updated
    }).catch((err) => {
      console.error('Failed to fetch head data for route transition to', path, err);
    }).finally(() => {
      GoogleServices.sendPageView(path);
    });

  }

  navigateTo(path: string, searchState?: SearchState, componentName?: string) {

    this.addContainerWillChange();

    requestAnimationFrame(() => {
      // Save current scroll position
      try {
        const y = (typeof scrollY !== 'undefined' && scrollY.current !== undefined)
          ? scrollY.current
          : (typeof window !== 'undefined' && 'scrollY' in window ? (window as any).scrollY : 0);
        routeStateStore.saveScrollPosition(window.location.pathname, Number(y) || 0);
      } catch { }

      if (searchState) {
        routeStateStore.saveSearchState(window.location.pathname, searchState);
      }

      this.setCurrentPath(path);
      routeStateStore.setSkipScrollRestore(path, false);
      if (typeof window !== 'undefined') {
        window.history.pushState(null, '', path);
      }

      this.setIsInitialLoad(false);
      if (componentName) this.setIsLoading(true, componentName);

      // perform side effects (analytics/head updates)
      void this.performRouteTransitionSideEffects(path);

      this.removeContainerWillChange();
    });
  }

  get handlePopstateEvent() {
    if (typeof window === 'undefined') return;
    const newPath = window.location.pathname;
    requestAnimationFrame(() => {
      this.addContainerWillChange();
      this.currentUrl.href = window.location.href;
      this.setIsInitialLoad(false);
      this.setIsLoading(true);
      this.isTransitioningRoute = true;
      this.performRouteTransitionSideEffects(newPath);
      this.removeContainerWillChange();
    });
  }

  private addContainerWillChange() {
    if (!this.elRouteContainer) {
      this.elRouteContainer = document.querySelector('.route-container');
      if (!this.elRouteContainer) return;
    }
    // Skip will-change if View Transition API is supported, as it handles performance optimizations
    if (typeof document !== "undefined" && document.startViewTransition) {
      return;
    }
    const willChangeProps = "transform, opacity, scroll-position, contents";
    try {
      this.elRouteContainer.style.setProperty('will-change', willChangeProps);
    } catch {
      console.warn('Failed to set will-change styles on route container');
    }
  }

  private removeContainerWillChange() {
    if (this.setTimeoutWillChange) {
      clearTimeout(this.setTimeoutWillChange);
      this.setTimeoutWillChange = undefined;
    }

    this.setTimeoutWillChange = window.setTimeout(() => {
      if (!this.elRouteContainer) return;
      this.elRouteContainer.style.removeProperty('will-change');

      // Remove style attribute only if there are no other inline styles
      if (!this.elRouteContainer.getAttribute('style') ||
        this.elRouteContainer.style.cssText.trim() === '' ||
        this.elRouteContainer.style.length === 0) {
        this.elRouteContainer.removeAttribute('style');
      }
    }, 5000);
  }

  loadStart(componentName?: string) {
    this.setIsLoading(true, componentName);
    this.isTransitioningRoute = true;
    // this.CurrentComponent = null;
  }

  loadEnd() {
    this.setIsLoading(false);
    // this.loadingComponent = null;
  }
}

export class RouteStateManager {
  scrollPositions = new LRUCache<string, number>({ max: 50 }); // Limit to 50 most recent scroll positions
  searchStates = new LRUCache<string, SearchState>({ max: 50 }); // Limit to 50 most recent search states
  lastVisitedJob: CardJob['slug'] | undefined = $state(undefined); // Remember the last visited job slug for mobile navigation
  carouselState: CarouselState | null = $state(null); // Single carousel state for homepage
  skipScrollRestore = new LRUCache<string, boolean>({ max: 50 }); // Limit to 50 most recent skip flags
  cardHeights = new LRUCache<string, Record<number, number>>({ max: 500 }); // Global cache for card heights

  saveScrollPosition(path: string, scrollY: number) {
    this.scrollPositions.set(path, scrollY);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`scroll_${path}`, scrollY.toString());
      } catch (e) {
        console.warn('Failed to save scroll position to sessionStorage', e);
      }
    }
  }

  getScrollPosition(path: string): number | undefined {
    let pos = this.scrollPositions.get(path);
    if (pos === undefined && typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(`scroll_${path}`);
        if (stored) {
          pos = parseInt(stored, 10);
          this.scrollPositions.set(path, pos); // cache in memory
        }
      } catch (e) {
        console.warn('Failed to load scroll position from sessionStorage', e);
      }
    }
    return pos;
  }

  saveSearchState(path: string, searchState: SearchState) {
    // Capture server-side lastJobUpdate if available (more reliable for freshness checks)
    let serverLast = 0;
    try {
      const themeData = getThemeData();
      if (themeData?.lastJobUpdate) {
        const parsed = Date.parse(themeData.lastJobUpdate);
        serverLast = isNaN(parsed) ? 0 : parsed;
      }
    } catch {
      serverLast = 0;
    }

    const stateWithTimestamp = { ...searchState, timestamp: Date.now(), serverLastJobUpdate: serverLast };
    this.searchStates.set(path, stateWithTimestamp);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`searchState_${path}`, JSON.stringify(stateWithTimestamp));
      } catch {
        console.warn('Failed to save search state to sessionStorage');
      }
    }
  }

  getSearchState(path: string): SearchState | undefined {
    let state = this.searchStates.get(path);
    if (!state && typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(`searchState_${path}`);
        if (stored) {
          const parsed = JSON.parse(stored) as SearchState;
          if (parsed) {
            // Normalize numeric timestamp fields that may have been stringified
            parsed.timestamp = typeof parsed.timestamp === 'number' ? parsed.timestamp : (parsed.timestamp ? Number(parsed.timestamp) : undefined);
            parsed.serverLastJobUpdate = typeof parsed.serverLastJobUpdate === 'number' ? parsed.serverLastJobUpdate : (parsed.serverLastJobUpdate ? Number(parsed.serverLastJobUpdate) : undefined);

            this.searchStates.set(path, parsed); // cache in memory
            state = parsed;
          }
        }
      } catch {
        console.warn('Failed to load search state from sessionStorage');
      }
    }
    return state;
  }

  clearSearchState(path: string) {
    this.searchStates.delete(path);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.removeItem(`searchState_${path}`);
      } catch {
        console.warn('Failed to clear search state from sessionStorage');
      }
    }
  }

  saveCarouselState(carouselState: CarouselState) {
    this.carouselState = carouselState;
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem('carouselState', JSON.stringify(carouselState));
      } catch {
        console.warn('Failed to save carousel state to sessionStorage');
      }
    }
  }

  getCarouselState(): CarouselState | undefined {
    if (this.carouselState) return this.carouselState;
    if (typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem('carouselState');
        if (stored) {
          this.carouselState = JSON.parse(stored) as CarouselState;
          return this.carouselState;
        }
      } catch {
        console.warn('Failed to load carousel state from sessionStorage');
      }
    }
    return undefined;
  }

  clearCarouselState() {
    if (!this.carouselState) return;
    this.carouselState = null;
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.removeItem('carouselState');
      } catch {
        console.warn('Failed to clear carousel state from sessionStorage');
      }
    }
  }

  // Mark a job slug as the last visited for mobile navigation.
  MarkVisitedJob(slug: CardJob['slug']) {
    if (!slug) return;
    this.lastVisitedJob = slug;
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem('lastVisitedJob', slug);
      } catch {
        console.warn('Failed to save last visited job to sessionStorage');
      }
    }
  }

  // Check if a job slug is the last visited for mobile navigation.
  hasVisitedJob(slug: CardJob['slug']): boolean {
    if (!slug) return false;
    if (this.lastVisitedJob === slug) return true;
    if (typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem('lastVisitedJob');
        if (stored && stored === slug) {
          return true;
        }
      } catch {
        console.warn('Failed to load last visited job from sessionStorage');
      }
    }
    return false;
  }

  setSkipScrollRestore(path: string, skip: boolean) {
    this.skipScrollRestore.set(path, skip);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`skipScrollRestore_${path}`, skip.toString());
      } catch (e) {
        console.warn('Failed to save skipScrollRestore to sessionStorage', e);
      }
    }
  }

  getSkipScrollRestore(path: string): boolean {
    let skip = this.skipScrollRestore.get(path);
    if (skip === undefined && typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(`skipScrollRestore_${path}`);
        if (stored) {
          skip = stored === 'true';
          this.skipScrollRestore.set(path, skip); // cache in memory
        }
      } catch (e) {
        console.warn('Failed to load skipScrollRestore from sessionStorage', e);
      }
    }
    return skip || false;
  }

  restoreScrollForPath(path: string): void {
    if (typeof window === 'undefined') return;
    if (this.getSkipScrollRestore(path)) {
      // Skip scroll restoration for this path
      this.setSkipScrollRestore(path, false); // Reset after skipping
      return;
    }

    let attempts = Number(0);
    let restoring = false;
    const maxAttempts = Number(3);

    const isReady = () => !routeStore.isInitialLoad && !routeStore.isLoading && routeStore.CurrentComponent && !routeStore.isTransitioningRoute;

    const attemptRestore = () => {
      const savedScroll = this.getScrollPosition(path);

      restoring = true;

      if (savedScroll !== undefined) {
        window.scrollTo({ top: savedScroll, behavior: 'instant' });
        // Check if scrollY is less than saved, and retry if attempts allow
        requestAnimationFrame(() => {
          if (window.scrollY < savedScroll && attempts < maxAttempts) {
            attempts++;
            requestAnimationFrame(tryRestore);
            restoring = false;
            return;
          }
        });
      } else if (path !== "/") {
        // Scroll to top for new routes
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    };

    const tryRestore = () => {
      if (isReady()) {
        requestAnimationFrame(attemptRestore);
        restoring = false;
        return;
      }

      // To be safe, initial restore doesn't restore somehow
      if (attempts < maxAttempts && !restoring && isReady()) {
        attempts++;
        requestAnimationFrame(tryRestore);
        restoring = false;
      }
    };

    requestAnimationFrame(tryRestore);
  }

  saveCardHeights(heights: Map<number, number>, keyname: string = 'global') {
    const record = Object.fromEntries(heights);
    this.cardHeights.set(keyname, record);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`cardHeights_${keyname}`, JSON.stringify(record));
      } catch (e) {
        console.warn('Failed to save cardHeights to sessionStorage', e);
      }
    }
  }

  getCardHeights(keyname: string = 'global'): Map<number, number> {
    let record = this.cardHeights.get(keyname);
    if (!record && typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(`cardHeights_${keyname}`);
        if (stored) {
          record = JSON.parse(stored);
          this.cardHeights.set(keyname, record);
        }
      } catch (e) {
        console.warn('Failed to load cardHeights from sessionStorage', e);
      }
    }
    return record ? new Map(Object.entries(record).map(([k, v]) => [Number(k), Number(v)])) : new Map();
  }
}

export const routeStore = new RouteManager();
export const routeStateStore = new RouteStateManager();

/** Navigate to a new path within the SPA.
 * @param path The target path to navigate to.
 * @param searchState Optional search state to save for the current path before navigating away.
 */
export function GlobalNavigateTo(path: string, searchState?: SearchState) {
  requestAnimationFrame(() => {
    routeStore.navigateTo(path, searchState);
  });
}