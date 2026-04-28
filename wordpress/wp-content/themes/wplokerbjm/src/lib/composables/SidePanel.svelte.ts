import type { CardJob, JobCardProps } from "@/types";
import { routeStateStore } from "$lib/stores/Route.svelte";
import { deviceDetector } from "$lib/features/DeviceDetector.svelte";
import { useRIC } from "$lib/utils/window.svelte";
import { goto } from "$app/navigation";
/**
 * SidePanelManager
 *
 * Central manager for the side panel UI state and behavior.
 */
class SidePanelManager
{
  public selectedSlug = $derived(routeStateStore.lastVisitedJob.slug);
  #isDesktop = $derived(deviceDetector.isPlatformDesktop);
  public isScrolling: boolean = false;

  /**
   * Update scroll state used by `scrollToCard` to avoid interrupting
   * user-initiated manual scrolling.
   * 
   * @note rely on this instead mutating `isScrolling` directly for better predictability
   */
  public set setScrollState(value: boolean)
  {
    this.isScrolling = value;
  }

  /**
   * Open the side panel for a job.
   *
   * @param slug - The job slug to open
   * @param source - The source of the job card interaction (e.g., "featured", "carousel") for analytics
   * @param gotoCB - Optional callback passed to Sveltekit `goto` to execute after navigation completes (desktop only)
   *
   * Notes:
   * - On desktop this will replace the current history entry with the
   *   job permalink path and trigger SEO/head updates.
  *   to `sidePanelData` from SingleSidePanel.svelte.
   */
  public openSidePanel(
    slug: string,
    job: CardJob,
    source: JobCardProps[ "variant" ] = "featured",
    gotoCB: (() => void | Promise<void>) | null = null,
  ): void
  {
    routeStateStore.MarkVisitedJob(slug, source);

    requestAnimationFrame(() =>
    {
      // Handle page push and SEO for desktop

      if (job && job.permalink && this.#isDesktop)
      {
        const url = new URL(job.permalink, window.location.origin);
        const path = url.pathname + url.search + url.hash;

        goto(path, { replaceState: true, noScroll: true, keepFocus: true }).then(() =>
        {
          if (!gotoCB) return;
          return Promise.try(gotoCB).catch((err) =>
          {
            console.error("gotoCB error:", err);
          });
        });
      }
    });
  }

  /**
   * Smoothly scroll to the job card for the given slug.
   *
   * @param slug - The job slug to scroll to; defaults to `this.selectedSlug`
   * @param skipIfScrolling - If true, skip scrolling if user is actively scrolling (default: true)
   * @param selectedSourceType - Choose source card to jump
   *
   */
  public scrollToJobGridCard(
    slug: string,
    skipIfScrolling: boolean = true,
    selectedSourceType: JobCardProps[ "variant" ] = "featured", // default to "featured"
  ): void
  {
    const targetSlug = slug ?? this.selectedSlug;
    if (!targetSlug) return;

    // Skip if user is still scrolling and skipIfScrolling is true
    if (skipIfScrolling && this.isScrolling) return;

    const performScroll = () =>
    {
      try
      {
        const safeSlug = String(targetSlug);
        const selector = `div[data-job-slug="${safeSlug}"]`;
        const candidates = Array.from(
          document.querySelectorAll(selector),
        ) as HTMLElement[];
        let cardElement: HTMLElement | null = null;

        const isElementVisible = (el: HTMLElement) =>
        {
          const rect = el.getBoundingClientRect();
          const style = window.getComputedStyle(el);
          return (
            rect.width > 0 &&
            rect.height > 0 &&
            style.display !== "none" &&
            el.offsetParent !== null
          );
        }

        const visibleCandidates = candidates.filter(isElementVisible);
        const sourceVisible = visibleCandidates
          .filter((el) => el.dataset.jobSource === selectedSourceType)
          .sort(
            (a, b) =>
              Math.abs(a.getBoundingClientRect().top) -
              Math.abs(b.getBoundingClientRect().top),
          );

        const fallbackVisible = visibleCandidates.sort(
          (a, b) =>
            Math.abs(a.getBoundingClientRect().top) -
            Math.abs(b.getBoundingClientRect().top),
        );

        cardElement = sourceVisible[ 0 ] || fallbackVisible[ 0 ] || null;

        cardElement?.scrollIntoView({
          behavior: "smooth",
          block: "start",
          inline: "nearest",
        });
      } catch (err)
      {
        console.error("scrollToCard error:", err);
      }
    };

    requestAnimationFrame(() =>
    {
      useRIC(performScroll, { timeout: 300, fallbackDelay: 300, fallback: "timeout" });
    });
  }
}
export const useSidePanel = new SidePanelManager();