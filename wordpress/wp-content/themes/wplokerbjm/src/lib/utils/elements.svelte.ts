import type { Attachment } from "svelte/attachments";
import { MediaQuery } from "svelte/reactivity";
import { browser } from "$app/environment";
import { page } from "$app/state";

const mobileMq = new MediaQuery("(max-width: 767.98px)");
export const isMobile = (): boolean => {
  if (!browser) {
    try {
      return page.data?.deviceType?.isMobile ?? false;
    } catch {
      return false;
    }
  }
  return mobileMq?.current ?? false;
}

export const isJobGridEl = (): HTMLElement | null => {
  if (!browser) return null;
  return document.getElementById("job-grid");
};

/**
 * Lightweight portal attachment helper for Svelte.
 * @example <div {@attach attachPortal("#app")}>...</div>
 */
export function attachPortal(selector: string = "body"): Attachment {
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
}

