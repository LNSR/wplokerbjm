import type { Attachment } from "svelte/attachments";
import { MediaQuery, SvelteDate } from "svelte/reactivity";
import { browser } from "$app/environment";
import { page } from "$app/state";

export const isMobile = (): boolean => {
  if (!browser) {
    try {
      return page.data?.deviceType?.isMobile ?? false;
    } catch {
      return false;
    }
  }

  const mobileMq = new MediaQuery("(max-width: 767.98px)");
  return mobileMq?.current ?? false;
}

export const isJobGridEl = (): HTMLElement | null => {
  if (!browser) return null;
  return document.getElementById("job-grid");
};

export class SharedClock {
  public static now = new SvelteDate();
  static #intervalId: ReturnType<typeof setInterval> | null = null;
  static #refCount: number = 0;

  /**
 * @see generalStore.useTimeAgo()
 * @see generalStore.useDeadline()
 * Provides reactive time updates for generalStore.useTimeAgo().
 * Creates a time side effect that updates the SvelteDate every minute.
 * Returns a cleanup function to clear the interval.
 */
  public static timeEffect(): () => void {
    SharedClock.startTimeEffect();

    // Return a cleanup function to clear the interval when the component is destroyed
    return () => {
      SharedClock.stopTimeEffect();
    };
  }

  private static startTimeEffect(): void {
    SharedClock.#refCount += 1;

    if (!SharedClock.#intervalId) {
      SharedClock.#intervalId = setInterval(() => {
        const now = Date.now();
        SharedClock.now.setTime(now);
      }, 60000); // Update every minute
    }
  }

  private static stopTimeEffect(): void {
    SharedClock.#refCount = Math.max(SharedClock.#refCount - 1, 0);
    if (SharedClock.#refCount === 0 && SharedClock.#intervalId) {
      clearInterval(SharedClock.#intervalId);
      SharedClock.#intervalId = null;
    }
  };
}

/**
 * PortalManager encapsulates portal/teleport behaviors for Svelte components, allowing elements to be rendered outside their parent hierarchy. */
export class PortalManager {
  /**
   * Creates a Svelte attachment that teleports the element into the given selector.
   * @param selector - element selector for the target container to teleport into (default: "body")
   * @returns A Svelte attachment function
   * @summary Usage example: <dialog {@attach PortalManager.teleport("#app")} class="modal">...</dialog>
   */
  static teleport(selector: string = "body"): Attachment {
    return (node: Element) => {
      if (!browser) return;

      const target = document.querySelector(selector) ?? document.body;
      try {
        target.appendChild(node);
      } catch (err) {
        console.error("[portal] teleport failed", err);
      }

      return () => {
        try {
          node.parentNode?.removeChild(node);
        } catch (err) {
          console.error("[portal] destroy failed", err);
        }
      };
    };
  };
}
