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
/**
 * @see generalStore.useTimeAgo()
 * @see generalStore.useDeadline()
 * Provides reactive time updates for generalStore.useTimeAgo().
 * Creates a time side effect that updates the SvelteDate every second.
 * Returns a cleanup function to clear the interval.
 */
export const timeEffect = (now?: SvelteDate): () => void => {
  const id = setInterval(() => now?.setTime(Date.now()), 1000);
  // Return a cleanup function to clear the interval when the component is destroyed
  return () => clearInterval(id);
}

/**
 * PortalManager encapsulates portal/teleport behaviors and exposes a reusable
 * instance for use across the app. Methods accept an optional callback invoked
 * once the append/remove operation has completed.
 */
export class PortalManager {
  /**
   * Creates a Svelte attachment that teleports the element into the given selector.
   * @param selector - element selector for the target container to teleport into (default: "body")
   * @returns A Svelte attachment function
   * @summary Usage example: <dialog {@attach PortalManager.teleport("#app")} class="modal">...</dialog>
   */
  static teleport = (selector: string = "body"): Attachment => {
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

  // Append element into the target container and invoke callback once appended
  static append(
    el: HTMLElement | null,
    selector: string = "body",
    callback?: () => void,
  ) {
    if (!browser) {
      this.safeCallback(callback);
      return;
    }
    const target = document.querySelector(selector) ?? document.body;
    try {
      if (el && el.parentElement !== target) target.appendChild(el);
    } catch (err) {
      console.error("[portal] append failed", err);
    }
    this.safeCallback(callback);
  }

  // Remove element from the target container and invoke callback once removed
  static remove(
    el: HTMLElement | null,
    selector: string = "body",
    callback?: () => void,
  ) {
    if (!browser) {
      this.safeCallback(callback);
      return;
    }
    const target = document.querySelector(selector) ?? document.body;
    try {
      if (el && el.parentElement === target) target.removeChild(el);
    } catch (err) {
      console.error("[portal] remove failed", err);
    }
    this.safeCallback(callback);
  }

  private static safeCallback(cb?: () => void) {
    try {
      cb?.();
    } catch (e) {
      console.error("[portal] callback failed", e);
    }
  }
}
