import type { Attachment } from "svelte/attachments";
import { browser } from "$app/environment";

export const isJobGridEl = browser ? document.getElementById("job-grid") : null;

/**
 * Lightweight portal attachment helper for Svelte.
 * @example <div {@attach teleportTo("#app")}>...</div>
 */
export function teleportTo(selector: string = "body"): Attachment
{
  return (node: Element) =>
  {
    if (!browser) return;

    const target = document.querySelector(selector) ?? document.body;
    try
    {
      target.appendChild(node);
    } catch (err)
    {
      console.error("[portal] teleport failed", err);
    }

    return () =>
    {
      try
      {
        node.remove();
      } catch (err)
      {
        console.error("[portal] destroy failed", err);
      }
    };
  };
}

