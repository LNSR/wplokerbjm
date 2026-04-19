import type { SearchState, CarouselState, JobCardProps, CardHeightKey } from "@/types";
import { SvelteMap } from "svelte/reactivity";
import { type CardJob } from "@/types";
import typia from "typia";
import { useVirtualization } from "$lib/features/Virtualization.svelte";
import { deviceDetector, type DeviceDetectorInternal } from "$lib/features/DeviceDetector.svelte";

type LastVisitedJobState = {
  slug: CardJob[ "slug" ];
  source?: JobCardProps[ "variant" ];
};

class RouteManager
{
  isInitialLoad = $state(true);
  isLoading = $state(false);
  isTransitioningRoute = $state(false);

  setIsInitialLoad(value: boolean)
  {
    this.isInitialLoad = value;
  }

  setIsLoading(loading: boolean)
  {
    this.isLoading = loading;
  }

  setIsTransitioningRoute(transitioning: boolean)
  {
    this.isTransitioningRoute = transitioning;
  }

}

class RouteStateManager
{
  #scrollPositionsMap = new SvelteMap<string, number>();
  #searchMap = new SvelteMap<string, SearchState | undefined>();
  #visitedJobsMap = new SvelteMap<string, LastVisitedJobState>();
  #carouselMap = new SvelteMap<string, CarouselState>();
  #cardHeightsMap = new SvelteMap<string, SvelteMap<number, number>>();
  public lastVisitedJob = $derived<LastVisitedJobState>(this.#visitedJobsMap.get(`${deviceDetector.currentDevice}`) ?? { slug: undefined, source: undefined });
  #carouselState = $derived<CarouselState | undefined>(this.#carouselMap.get(`${deviceDetector.currentDevice}`));

  constructor()
  {
    //* pass the cache clearing method to the device detector so it can call it when device type changes
    (deviceDetector as DeviceDetectorInternal).setCallbackOnDeviceChange = this.#clearCachesForDevice.bind(this);
  }

  public saveSearchState(path: string, searchState?: SearchState): void
  {
    const key = `${deviceDetector.currentDevice}-${path}`;
    this.#searchMap.set(key, searchState);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.setItem(
          `searchStates-${key}`,
          typia.json.stringify<typeof searchState>(searchState),
        );
      } catch (e)
      {
        console.warn("Failed to save search state to sessionStorage", e);
      }
    }
  }

  public getSearchState(path: string): SearchState | undefined
  {
    const key = `${deviceDetector.currentDevice}-${path}`;
    let state = this.#searchMap.get(key);
    if (!state && typeof sessionStorage !== "undefined")
    {
      try
      {
        const stored = sessionStorage.getItem(`searchStates-${key}`);
        if (stored)
        {
          const parsed = typia.json.assertParse<SearchState | undefined>(stored);
          this.#searchMap.set(key, parsed); // cache in memory
          state = parsed;
        }
      } catch (e)
      {
        console.warn("Failed to load search state from sessionStorage", e);
      }
    }
    return state;
  }

  public clearSearchState(path: string)
  {
    const key = `${deviceDetector.currentDevice}-${path}`;
    this.#searchMap.delete(key);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.removeItem(`searchStates-${key}`);
      } catch (e)
      {
        console.warn("Failed to clear search state from sessionStorage", e);
      }
    }
  }

  public saveCarouselState(carouselState: CarouselState)
  {
    const key = `${deviceDetector.currentDevice}`;
    this.#carouselMap.set(key, carouselState);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        const serialized = typia.json.stringify<typeof carouselState>(carouselState);
        sessionStorage.setItem(
          `carouselState-${key}`,
          serialized,
        );
      } catch (e)
      {
        console.warn("Failed to save carousel state to sessionStorage", e);
      }
    }
  }

  public getCarouselState(): CarouselState | undefined
  {
    const key = `${deviceDetector.currentDevice}`;
    if (this.#carouselState?.slideIndex) return this.#carouselState;
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        const stored = sessionStorage.getItem(
          `carouselState-${key}`,
        );
        if (stored)
        {
          const parsed = typia.json.assertParse<CarouselState>(stored);
          this.#carouselMap.set(key, parsed);
          return this.#carouselState;
        }
      } catch (e)
      {
        console.warn("Failed to load carousel state from sessionStorage", e);
      }
    }
    return undefined;
  }

  public clearCarouselState()
  {
    const key = `${deviceDetector.currentDevice}`;
    this.#carouselMap.delete(key);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.removeItem(`carouselState-${key}`);
      } catch (e)
      {
        console.warn("Failed to clear carousel state from sessionStorage", e);
      }
    }
  }

  // Mark a job slug as the last visited.
  // `source` indicates where the user clicked the job (carousel or grid).
  public MarkVisitedJob(slug: CardJob[ "slug" ], source?: JobCardProps[ "variant" ]): void
  {
    if (!slug || !deviceDetector) return;
    const key = `${deviceDetector.currentDevice}`;
    this.#visitedJobsMap.set(key, { slug, source });
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.setItem(`lastVisitedJob-${key}`, slug);
        sessionStorage.setItem(
          `lastVisitedJobSource-${key}`,
          source ?? "",
        );
      } catch (e)
      {
        console.warn("Failed to save last visited job to sessionStorage", e);
      }
    }
  }

  public restoreVisitedJob(): CardJob[ "slug" ] | undefined
  {
    const key = `${deviceDetector.currentDevice}`;
    if (this.lastVisitedJob.slug) return this.lastVisitedJob.slug;
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        const stored = sessionStorage.getItem(
          `lastVisitedJob-${key}` as NonNullable<CardJob[ "slug" ]>,
        );
        if (stored)
        {
          const src = sessionStorage.getItem(
            `lastVisitedJobSource-${key}`,
          ) as JobCardProps[ "variant" ] | null;
          this.#visitedJobsMap.set(key, {
            slug: stored,
            source: src ?? undefined,
          });
          return this.lastVisitedJob.slug;
        }
      } catch (e)
      {
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
    keyname: CardHeightKey,
  )
  {
    // Accept either SvelteMap or native Map and persist as a plain record for storage
    const record = Object.fromEntries(
      heights as Iterable<readonly [ number, number ]>,
    );
    const key = `${deviceDetector.currentDevice}-${keyname}`;
    this.#cardHeightsMap.set(key, heights);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.setItem(`cardHeights-${key}`, typia.json.stringify<typeof record>(record));
      } catch (e)
      {
        console.warn("Failed to save cardHeights to sessionStorage", e);
      }
    }
  }

  public clearCardHeights(keyname: CardHeightKey)
  {
    const key = `${deviceDetector.currentDevice}-${keyname}`;
    const cardHeights = this.getCardHeights(keyname);
    useVirtualization.invalidateCardHeightsCache(cardHeights);
    cardHeights.clear();
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.removeItem(`cardHeights-${key}`);
      } catch (e)
      {
        console.warn("Failed to clear cardHeights from sessionStorage", e);
      }
    }
  }

  /**
   * 
   */
  public getCardHeights(keyname: CardHeightKey): SvelteMap<number, number>
  {
    if (!deviceDetector) return new SvelteMap<number, number>();
    const key = `${deviceDetector.currentDevice}-${keyname}`;
    let cardHeights = this.#cardHeightsMap.get(key);
    if (cardHeights) return cardHeights;

    cardHeights = new SvelteMap<number, number>();

    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        const stored = sessionStorage.getItem(`cardHeights-${key}`);
        if (stored)
        {
          const record = typia.json.assertParse<Record<number, number> | undefined>(stored);
          if (record)
          {
            for (const [ recordKey, height ] of Object.entries(record))
            {
              cardHeights.set(Number(recordKey), height);
            }
          }
        }
      } catch (e)
      {
        console.warn("Failed to load cardHeights from sessionStorage", e);
      }
    }

    this.#cardHeightsMap.set(key, cardHeights);

    return cardHeights;
  }

  /**
   * Save the current scroll position for a route path
   */
  public saveScrollPosition(path: string, scrollY: number): void
  {
    const key = `${deviceDetector.currentDevice}-${path}`;
    this.#scrollPositionsMap.set(key, scrollY);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.setItem(`scrollPosition-${key}`, String(scrollY));
      } catch (e)
      {
        console.warn("Failed to save scroll position to sessionStorage", e);
      }
    }
  }

  /**
   * Get the saved scroll position for a route path
   */
  public getScrollPosition(path: string): number | undefined
  {
    const key = `${deviceDetector.currentDevice}-${path}`;
    let position = this.#scrollPositionsMap.get(key);
    if (position === undefined && typeof sessionStorage !== "undefined")
    {
      try
      {
        const stored = sessionStorage.getItem(`scrollPosition-${key}`);
        if (stored)
        {
          position = Number(stored);
          if (!isNaN(position))
          {
            this.#scrollPositionsMap.set(key, position);
          } else
          {
            position = undefined;
          }
        }
      } catch (e)
      {
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
  public restoreScrollPosition(path: string, delay: number = 0): void
  {
    const position = this.getScrollPosition(path);
    if (position !== undefined && typeof window !== "undefined")
    {
      setTimeout(() =>
      {
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
  public clearScrollPosition(path: string): void
  {
    const key = `${deviceDetector.currentDevice}-${path}`;
    this.#scrollPositionsMap.delete(key);
    if (typeof sessionStorage !== "undefined")
    {
      try
      {
        sessionStorage.removeItem(`scrollPosition-${key}`);
      } catch (e)
      {
        console.warn("Failed to clear scroll position from sessionStorage", e);
      }
    }
  }

  /**
 * Clear all cache if device type changes to prevent cross-device data issues
 * @internal
 */
  #clearCachesForDevice(): void
  {
    console.log("Device type changed, clearing route state caches to prevent cross-device data issues");

    this.#scrollPositionsMap.clear();
    this.#searchMap.clear();
    this.#visitedJobsMap.clear();
    this.#carouselMap.clear();
    this.#cardHeightsMap.clear();

    // Clear all sessionStorage
    if (typeof sessionStorage !== "undefined")
    {
      sessionStorage.clear();
    }
  }

}

export const routeStore = new RouteManager();
export const routeStateStore = new RouteStateManager();