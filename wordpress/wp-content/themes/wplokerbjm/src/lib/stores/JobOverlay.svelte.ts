import type { CardJob, JobCardProps } from "@/types";
import { isMobile } from "$lib/utils/elements.svelte";
import { routeStateStore } from "$lib/stores/Route.svelte";
import { SvelteURL } from "svelte/reactivity";
import { goto } from "$app/navigation";
/**
 * JobOverlayManager
 *
 * Central manager for the job overlay UI state and behavior.
 * Responsibilities:
 * - Track overlay open/close state and currently selected job
 * - Hold current overlay detail data sourced from SvelteKit page data
 * - Provide consistent scrolling to the associated job card
 * - Integrate with browser history and SEO updates for desktop
 */
export class JobOverlayManager
{
  // Current slug of the job that was last activated through routeStateStore
  // This is derived from shared route state so other components can react.
  public selectedSlug = $derived( routeStateStore.lastVisitedJob );

  // Fully resolved job card element data from the UI list (optional).
  // This allows immediate overlay content while the remote detail may still load.
  public selectedJob = $state<CardJob | null>( null );

  // Overlay detail payload from the server response, set by SingleOverlay.svelte.
  // This is the canonical detail state for the overlay panel content.

  // Scroll detection state to avoid interrupting user-initiated scrolling.
  public isScrolling: boolean = false;

  /**
   * Update scroll state used by `scrollToCard` to avoid interrupting
   * user-initiated manual scrolling.
   */
  public set setScrollState(value: boolean) {
    this.isScrolling = value;
  }

  /**
   * Open the overlay for a job.
   *
   * @param slug - The job slug to open
   * @param job - Optional job object to set as the selected job immediately
   * @param source - The source of the job card interaction (e.g., "featured", "carousel") for analytics
   * @param gotoCB - Optional callback to execute after navigation completes (desktop only)
   *
   * Notes:
   * - On desktop this will replace the current history entry with the
   *   job permalink path and trigger SEO/head updates.
  * - Job detail data is provided by SvelteKit route load and synchronized
  *   to `overlayData` from SingleOverlay.svelte.
   */
  public openOverlay (
    slug: string,
    job?: CardJob,
    source: JobCardProps[ "variant" ] = "featured",
    { gotoCB }: { gotoCB?: () => void | Promise<void> } = {},
  ): void
  {
    routeStateStore.MarkVisitedJob( slug, source );
    this.selectedJob = job ?? null;

    requestAnimationFrame( () =>
    {
      // Handle page push and SEO for desktop
      const isDesktop = !isMobile();

      if ( job && job.permalink && isDesktop )
      {
        const url = new SvelteURL( job.permalink, window.location.origin );
        const path = url.pathname + url.search + url.hash;

        goto( path, { replaceState: true, noScroll: true, keepFocus: true } ).then( () =>
        {
          if ( !gotoCB ) return;
          try
          {
            const gotoResult = gotoCB();
            if ( typeof gotoResult?.then === "function" || gotoResult instanceof Promise )
            {
              return Promise.resolve( gotoResult ).catch( err =>
              {
                console.error( "gotoCB Promise error:", err );
              } );
            } else
            {
              return void gotoResult;
            }
          } catch ( err )
          {
            console.error( "gotoCB error:", err );
          }
        } );
      }
    } );
  }

  /**
   * Smoothly scroll to the job card for the given slug.
   *
   * @param slug - The job slug to scroll to; defaults to `this.selectedSlug`
   * @param skipIfScrolling - If true, skip scrolling if user is actively scrolling (default: true)
   * @param selectedSourceType - Preferred source of the card ("carousel" or "featured")
   *
   * Notes:
   * - If no slug is provided and `this.selectedSlug` is null, no action is taken.
   * - The method attempts to find the closest visible card element matching
   *   the slug, preferring the specified source if provided.
   * - If no matching element is found, no scrolling occurs to avoid jumping
   *   to unrelated sections.
   */
  public scrollToCard (
    slug: string,
    skipIfScrolling: boolean = true,
    selectedSourceType: JobCardProps[ "variant" ] = "featured",
  ): void
  {
    const targetSlug = slug ?? this.selectedSlug;
    if ( !targetSlug ) return;

    if ( typeof window === "undefined" ) return;

    // Skip if user is still scrolling and skipIfScrolling is true
    if ( skipIfScrolling && this.isScrolling ) return;

    const performScroll = () =>
    {
      try
      {
        const safeSlug = String( targetSlug );
        const selector = `div[data-job-slug="${ safeSlug }"]`;
        const candidates = Array.from(
          document.querySelectorAll( selector ),
        ) as HTMLElement[];
        let cardElement: HTMLElement | null = null;

        const isElementVisible = ( el: HTMLElement ) =>
        {
          const rect = el.getBoundingClientRect();
          const style = window.getComputedStyle( el );
          return (
            rect.width > 0 &&
            rect.height > 0 &&
            style.display !== "none" &&
            el.offsetParent !== null
          );
        }

        const visibleCandidates = candidates.filter( isElementVisible );
        const sourceVisible = visibleCandidates
          .filter( ( el ) => el.dataset.jobSource === selectedSourceType )
          .sort(
            ( a, b ) =>
              Math.abs( a.getBoundingClientRect().top ) -
              Math.abs( b.getBoundingClientRect().top ),
          );

        const fallbackVisible = visibleCandidates.sort(
          ( a, b ) =>
            Math.abs( a.getBoundingClientRect().top ) -
            Math.abs( b.getBoundingClientRect().top ),
        );

        cardElement = sourceVisible[ 0 ] || fallbackVisible[ 0 ] || null;

        cardElement?.scrollIntoView( {
          behavior: "smooth",
          block: "start",
          inline: "nearest",
        } );
      } catch ( err )
      {
        console.error( "scrollToCard error:", err );
      }
    };
    if ( typeof window.requestIdleCallback === "function" )
    {
      window.requestIdleCallback( performScroll, { timeout: 300 } );
    } else
    {
      setTimeout( performScroll, 300 );
    }
  }
}
export const jobOverlayManager = new JobOverlayManager();