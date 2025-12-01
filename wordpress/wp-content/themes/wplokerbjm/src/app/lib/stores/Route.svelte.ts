import type { SearchState } from '@/types'
import { removeJobPostingJsonLd } from '$lib/utils/elements.svelte'
import { SvelteMap, SvelteURL } from 'svelte/reactivity'
import { scrollY } from 'svelte/reactivity/window'
import { SEOService } from '$lib/utils/SEO.svelte'
import { GoogleServices } from '$lib/utils/Google.svelte'
import { WPThemeDataStore } from '$lib/stores/WPThemeData'

export class RouteManager {
  currentUrl = $state(new SvelteURL(window.location.href));
  isInitialLoad = $state(true);
  isLoading = $state(false);
  loadingComponent = $state<string | null>(null);

  setCurrentPath(path: string) {
    this.currentUrl.href = new URL(path, window.location.origin).href;
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
    if (path.startsWith('/pasang-iklan-loker')) return 'PasangIklanLoker';
    if (path.startsWith('/lowongan/')) return 'SingleLowongan';
    return 'Unknown';
  }
}

export class RouteStateManager {
  scrollPositions = new SvelteMap<string, number>();
  searchStates = new SvelteMap<string, SearchState>();

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
    await SEOService.fetchHeadData(path);
    // Push page_view to gtag / GTM for SPA navigation after head update
    GoogleServices.sendPageView(path);
    // Trigger optional AdSense refresh for SPA navigation. Debounced in the handler.
    GoogleServices.adSenseRefresh();
  }

  // Add loading timeout (1 second max)
  setTimeout(() => {
    if (routeStore.isLoading) {
      routeStore.setIsLoading(false);
    }
  }, 1000);
}