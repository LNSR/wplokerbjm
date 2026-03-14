import type { CardJob, JobCardProps, JobDetailResponse } from "@/types";
import { isMobile } from "$lib/utils/elements.svelte";
import { routeStateStore, routeStore } from "$lib/stores/Route.svelte";
import { SvelteURL } from "svelte/reactivity";
import { GlobalNavigateTo } from "$lib/stores/Route.svelte";
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
export class JobOverlayManager {
  public selectedSlug = $derived(routeStateStore.lastVisitedJob);
  public selectedJob = $state<CardJob | null>(null);

  // Overlay detail state, synchronized from page.data.job in SingleOverlay.svelte
  public overlayData = $state<JobDetailResponse | null>(null);

  // Scroll detection
  public isScrolling = false;
  private scrollTimeout: ReturnType<typeof setTimeout> | null = null;

  /**
   * Create a JobOverlayManager instance.
   *
   * Registers a passive scroll listener used to detect when the user is
   * actively scrolling so that methods like `scrollToCard` can optionally
   * avoid interfering while the user scrolls.
   */
  constructor() {
    if (typeof window !== "undefined") {
      window.addEventListener("scroll", this.handleScroll, { passive: true });
    }
  }

  private handleScroll = (): void => {
    // Only set isScrolling when it transitions from false to true to avoid frequent reactive churn
    if (!this.isScrolling) this.isScrolling = true;
    if (this.scrollTimeout) clearTimeout(this.scrollTimeout);
    this.scrollTimeout = setTimeout(() => {
      this.isScrolling = false;
    }, 500); // Adjust delay as needed
  };

  /**
   * Open the overlay for a job.
   *
   * @param slug - The job slug to open
   * @param job - Optional job object to set as the selected job immediately
   *
   * Notes:
   * - On desktop this will replace the current history entry with the
   *   job permalink path and trigger SEO/head updates.
  * - Job detail data is provided by SvelteKit route load and synchronized
  *   to `overlayData` from SingleOverlay.svelte.
   */
  public openOverlay(
    slug: string,
    job?: CardJob,
    source: JobCardProps["variant"] = "featured",
  ): void {
    routeStateStore.MarkVisitedJob(slug, source);
    this.selectedJob = job ?? null;

    requestAnimationFrame(async () => {
      // Handle page push and SEO for desktop
      const isDesktop = !isMobile();

      if (job && job.permalink && isDesktop) {
        const url = new SvelteURL(job.permalink, window.location.origin);
        const path = url.pathname + url.search + url.hash;

        GlobalNavigateTo(path, { replaceState: true, noScroll: true, keepFocus: true });
      }
    });
  }

  /**
   * Smoothly scroll to the job card for the given slug.
   *
   * @param slug - The job slug to scroll to; defaults to `this.selectedSlug`
   * @param skipIfScrolling - If true, skip scrolling if user is actively scrolling (default: true)
   * @param preferredSource - Preferred source of the card ("carousel" or "featured") when multiple matches exist
   *
   * Notes:
   * - If no slug is provided and `this.selectedSlug` is null, no action is taken.
   * - The method attempts to find the closest visible card element matching
   *   the slug, preferring the specified source if provided.
   * - If no matching element is found, no scrolling occurs to avoid jumping
   *   to unrelated sections.
   */
  public scrollToCard(
    slug?: string,
    delay: number = 300,
    skipIfScrolling: boolean = true,
    preferredSource?: JobCardProps["variant"],
  ): void {
    const targetSlug = slug ?? this.selectedSlug;
    if (!targetSlug) return;

    if (typeof window === "undefined") return;

    // Skip if user is still scrolling and skipIfScrolling is true
    if (skipIfScrolling && this.isScrolling) return;

    setTimeout(() => {
      try {
        const safeSlug = String(targetSlug);
        const selector = `div[data-job-slug="${safeSlug}"]`;
        const candidates = Array.from(
          document.querySelectorAll(selector),
        ) as HTMLElement[];
        let cardElement: HTMLElement | null = null;

        // If multiple elements match the slug (e.g., carousel + grid), prefer a
        // candidate that matches the preferred source and is visible/close to the viewport.
        if (candidates.length > 0) {
          const visibleCandidates = candidates.filter((el) => {
            const rect = el.getBoundingClientRect();
            const style = window.getComputedStyle(el);
            return (
              rect.width > 0 &&
              rect.height > 0 &&
              style.display !== "none" &&
              el.offsetParent !== null
            );
          });

          if (preferredSource) {
            // Prefer visible candidates from the requested source
            const sourceVisible = visibleCandidates.filter(
              (el) => el.dataset.jobSource === preferredSource,
            );
            if (sourceVisible.length > 0) {
              sourceVisible.sort(
                (a, b) =>
                  Math.abs(a.getBoundingClientRect().top) -
                  Math.abs(b.getBoundingClientRect().top),
              );
              cardElement = sourceVisible[0];
            } else {
              // No visible matches for the preferred source; prefer any element from that source
              const sourceAny = candidates.filter(
                (el) => el.dataset.jobSource === preferredSource,
              );
              if (sourceAny.length > 0) {
                cardElement = sourceAny[0];
              }
            }
          }

          // If still no element chosen, fall back to the closest visible candidate, otherwise the first match
          if (!cardElement) {
            if (visibleCandidates.length > 0) {
              visibleCandidates.sort(
                (a, b) =>
                  Math.abs(a.getBoundingClientRect().top) -
                  Math.abs(b.getBoundingClientRect().top),
              );
              cardElement = visibleCandidates[0];
            } else {
              cardElement = candidates[0];
            }
          }
        }

        if (cardElement) {
          cardElement.scrollIntoView({
            behavior: "smooth",
            block: "start",
            inline: "nearest",
          });
          return;
        }

        // No card element found — avoid jumping to unrelated sections.
      } catch (err) {
        console.error("scrollToCard error:", err);
      }
    }, delay);
  }
}
export const jobOverlay = new JobOverlayManager();
