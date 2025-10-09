import type { SearchFilters, SearchContext, Job } from '@/types'
import { removeJobPostingJsonLd } from '@/utils'
import { SEOService } from '$lib/utils/SEO.svelte'

interface SearchState {
  jobs: Job[]
  context: SearchContext
  title: string
  totalJobs: number
  maxNumPages: number
  page: number
  filters: SearchFilters
  loading: boolean
  error: string | null
  timestamp?: number
  serverLastJobUpdate?: number
}

class RouteManager {
  currentPath = $state('');
  isInitialLoad = $state(true);
  isLoading = $state(false);
  loadingComponent = $state<string | null>(null);
  scrollPositions = $state<Record<string, number>>({});
  searchStates = $state<Record<string, SearchState>>({});

  setCurrentPath(path: string) {
    this.currentPath = path;
  }

  setIsInitialLoad(value: boolean) {
    this.isInitialLoad = value;
  }

  setIsLoading(loading: boolean, component?: string) {
    this.isLoading = loading;
    this.loadingComponent = component || null;
  }

  saveScrollPosition(path: string) {
    this.scrollPositions[path] = window.scrollY;
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`scroll_${path}`, window.scrollY.toString());
      } catch (e) {
        console.warn('Failed to save scroll position to sessionStorage', e);
      }
    }
  }

  getScrollPosition(path: string): number | undefined {
    let pos = this.scrollPositions[path];
    if (pos === undefined && typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(`scroll_${path}`);
        if (stored) {
          pos = parseInt(stored, 10);
          this.scrollPositions[path] = pos; // cache in memory
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
      if (typeof window !== 'undefined' && window.wpTheme?.lastJobUpdate) {
        const parsed = Date.parse(window.wpTheme.lastJobUpdate);
        serverLast = isNaN(parsed) ? 0 : parsed;
      }
    } catch (e) {
      serverLast = 0;
    }

    const stateWithTimestamp = { ...searchState, timestamp: Date.now(), serverLastJobUpdate: serverLast };
    this.searchStates[path] = stateWithTimestamp;
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.setItem(`searchState_${path}`, JSON.stringify(stateWithTimestamp));
      } catch (e) {
        console.warn('Failed to save search state to sessionStorage', e);
      }
    }
  }

  getSearchState(path: string): SearchState | undefined {
    let state = this.searchStates[path];
    if (!state && typeof sessionStorage !== 'undefined') {
      try {
        const stored = sessionStorage.getItem(`searchState_${path}`);
        if (stored) {
          state = JSON.parse(stored);

          // Normalize numeric timestamp fields that may have been stringified
          if (state) {
            state.timestamp = typeof state.timestamp === 'number' ? state.timestamp : (state.timestamp ? Number(state.timestamp) : undefined);
            state.serverLastJobUpdate = typeof state.serverLastJobUpdate === 'number' ? state.serverLastJobUpdate : (state.serverLastJobUpdate ? Number(state.serverLastJobUpdate) : undefined);
          }

          this.searchStates[path] = state; // cache in memory
        }
      } catch (e) {
        console.warn('Failed to load search state from sessionStorage', e);
      }
    }
    return state;
  }

  clearSearchState(path: string) {
    delete this.searchStates[path];
    if (typeof sessionStorage !== 'undefined') {
      try {
        sessionStorage.removeItem(`searchState_${path}`);
      } catch (e) {
        console.warn('Failed to clear search state from sessionStorage', e);
      }
    }
  }

  static getComponentNamePath(path: string): string {
    if (path === '/') return 'Homepage';
    if (path.startsWith('/pasang-iklan-loker')) return 'PasangIklanLoker';
    if (path.startsWith('/lowongan/')) return 'SingleLowongan';
    return 'Unknown';
  }
}

export const routeStore = new RouteManager();

/**
 * Attempt to remove JobPosting JSON-LD, but only on the first successful attempt.
 * Subsequent calls become no-ops.
*/
let _jobPostingJsonLdRemovalAttempted = $state(false); // Ensure we only attempt removal once.
function removeJobPostingJsonLdOnce(postId?: number | string, context?: string): number {
  if (_jobPostingJsonLdRemovalAttempted) return 0;
  try {
    const removed = removeJobPostingJsonLd(postId);
    _jobPostingJsonLdRemovalAttempted = true;
    return removed;
  } catch (e) {
    console.warn(context ? `Failed to remove JobPosting JSON-LD (${context})` : 'Failed to remove JobPosting JSON-LD', e);
    return 0;
  }
}

export function navigateTo(path: string, searchState?: SearchState) {
  // Save current scroll position
  routeStore.saveScrollPosition(window.location.pathname);

  // Save current search state if provided
  if (searchState) {
    routeStore.saveSearchState(window.location.pathname, searchState);
  }

  // Set loading state
  routeStore.setIsLoading(true, RouteManager.getComponentNamePath(path));

  // Remove any leftover JobPosting JSON-LD once.
  removeJobPostingJsonLdOnce();

  routeStore.setCurrentPath(path);
  window.history.pushState(null, '', path);
  routeStore.setIsInitialLoad(false);

  // Fetch RankMath head data only for SPA navigation, not initial load
  if (!routeStore.isInitialLoad) {
    SEOService.fetchHeadData(path);
  }

  // Add loading timeout (1 second max)
  setTimeout(() => {
    if (routeStore.isLoading) {
      routeStore.setIsLoading(false);
    }
  }, 1000);
}

// Listen to browser back/forward
if (typeof window !== 'undefined') {
  window.addEventListener('popstate', () => {
    const newPath = window.location.pathname;
    routeStore.setCurrentPath(newPath);
    routeStore.setIsInitialLoad(false);
    routeStore.setIsLoading(true, RouteManager.getComponentNamePath(newPath));

    // Ensure we only attempt removal once on popstate as well.
    removeJobPostingJsonLdOnce(undefined, 'popstate');

    // Fetch RankMath head data
    SEOService.fetchHeadData(newPath);

    // Add loading timeout for popstate as well
    setTimeout(() => {
      if (routeStore.isLoading) {
        routeStore.setIsLoading(false);
      }
    }, 500);
  });
}