import type { SearchState, CarouselState } from '@/types'
import { WPPostRoute } from '@/types'
import { removeJobPostingJsonLd } from '$lib/utils/elements.svelte'
import { SvelteMap, SvelteURL } from 'svelte/reactivity'
import { scrollY } from 'svelte/reactivity/window'
import { SEOService } from '$lib/utils/SEO.svelte'
import { GoogleServices } from '$lib/utils/Google.svelte'
import { WPThemeDataStore } from '$lib/stores/WPThemeData'
import { isDevelopmentMode } from '@/utils'
import type { CardJob } from '@/types'

export class RouteManager {
  currentUrl = $state(new SvelteURL(window.location.href));
  isInitialLoad = $state(true);
  isLoading = $state(false);
  loadingComponent = $state<string | null>(null);

  setCurrentPath(path: string) {
    this.currentUrl.href = new SvelteURL(path, window.location.origin).href;
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

  async performRouteTransitionSideEffects(path: string): Promise<void> {
    // Destroy AdSense ads before route change for clean navigation
    GoogleServices.adSenseDestroy();

    // Fetch RankMath head data
    if (!isDevelopmentMode()) {
      await SEOService.fetchHeadData(path);
    }

    // GTAG / GTM page view
    GoogleServices.sendPageView(path);

    // Trigger optional AdSense refresh
    GoogleServices.adSenseRefresh();
  }
}

export class RouteStateManager {
  scrollPositions = new SvelteMap<string, number>();
  searchStates = new SvelteMap<string, SearchState>(); // Track search states(include initial JobGrid) and per path
  lastVisitedJob: CardJob['slug'] | undefined = undefined; // Remember the last visited job slug for mobile navigation
  carouselState: CarouselState | null = null; // Single carousel state for homepage

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
      const themeData = WPThemeDataStore.getThemeData();
      if (themeData?.lastJobUpdate) {
        const parsed = Date.parse(themeData.lastJobUpdate);
        serverLast = isNaN(parsed) ? 0 : parsed;
      }
    } catch (e) {
      serverLast = 0;
    }

    const stateWithTimestamp = { ...searchState, timestamp: Date.now(), serverLastJobUpdate: serverLast };
    this.searchStates.set(path, stateWithTimestamp);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`searchState_${path}`, JSON.stringify(stateWithTimestamp));
      } catch (e) {
        console.warn('Failed to save search state to sessionStorage', e);
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
      } catch (e) {
        console.warn('Failed to load search state from sessionStorage', e);
      }
    }
    return state;
  }

  clearSearchState(path: string) {
    this.searchStates.delete(path);
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.removeItem(`searchState_${path}`);
      } catch (e) {
        console.warn('Failed to clear search state from sessionStorage', e);
      }
    }
  }

  saveCarouselState(carouselState: CarouselState) {
    this.carouselState = carouselState;
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem('carouselState', JSON.stringify(carouselState));
      } catch (e) {
        console.warn('Failed to save carousel state to sessionStorage', e);
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
      } catch (e) {
        console.warn('Failed to load carousel state from sessionStorage', e);
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
      } catch (e) {
        console.warn('Failed to clear carousel state from sessionStorage', e);
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
      } catch (e) {
        console.warn('Failed to save last visited job to sessionStorage', e);
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
      } catch (e) {
        console.warn('Failed to load last visited job from sessionStorage', e);
      }
    }
    return false;
  }

  restoreScrollForPath(path: string): void {
    if (typeof window === 'undefined') return;
    const savedScroll = this.getScrollPosition(path);
    if (savedScroll !== undefined) {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          setTimeout(() => window.scrollTo({ top: savedScroll, behavior: "smooth" }), 50);
        });
      });
    } else if (path !== "/") {
      // Scroll to top for new routes
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          setTimeout(() => window.scrollTo({ top: 0, behavior: "smooth" }), 50);
        });
      });
    }
  }
}

export const routeStore = new RouteManager();
export const routeStateStore = new RouteStateManager();

/** Navigate to a new path within the SPA.
 * @param path The target path to navigate to.
 * @param searchState Optional search state to save for the current path before navigating away.
 */
export async function navigateTo(path: string, searchState?: SearchState) {
  // Save current scroll position
  routeStateStore.saveScrollPosition(window.location.pathname, scrollY.current ?? 0);

  // Save current search state if provided
  if (searchState) {
    routeStateStore.saveSearchState(window.location.pathname, searchState);
  }

  // Destroy AdSense ads before route change for clean navigation
  GoogleServices.adSenseDestroy();

  // Set loading state
  routeStore.setIsLoading(true, routeStore.getComponentNamePath(path));

  // Remove any leftover JobPosting JSON-LD once.
  removeJobPostingJsonLd();

  routeStore.setCurrentPath(path);
  window.history.pushState(null, '', path);
  routeStore.setIsInitialLoad(false);

  // Fetch RankMath head data only for SPA navigation, not initial load
  if (!routeStore.isInitialLoad) {
    await routeStore.performRouteTransitionSideEffects(path);
  }

  // Add loading timeout (1 second max)
  setTimeout(() => {
    if (routeStore.isLoading) {
      routeStore.setIsLoading(false);
    }
  }, 1000);
}