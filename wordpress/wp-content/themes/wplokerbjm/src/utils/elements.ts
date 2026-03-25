//* Universal utility functions for DOM manipulation not tied to a specific framework.
import type { JobSchemaResponse } from "@/types";
import { isDevelopmentMode } from "@/utils";

export const isAppEl: string = ".route-container"; // Selector for the main application element

export function parseProps(element: Element | Document, propAttr: string) {
    const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`);
    let props = {};

    if (scriptElement) {
        const raw = scriptElement.textContent || scriptElement.innerHTML || "";
        try {
            props = raw ? JSON.parse(raw) : {};
        } catch {
            props = {};
        }
    }

    return props;
}

export function removePropsScriptFromElement(element: Element | Document, propAttr?: string): void {
    const isDev = isDevelopmentMode();
    if (isDev) return;

    const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`) as HTMLScriptElement | null;
    if (scriptElement) scriptElement.remove();
}


export function schemaScriptAttach(schema: string, ldType: JobSchemaResponse["type"], ldId?: string) {
    if (!schema) return "";
    const dataAttrs = `data-ld-type="${ldType}"`;
    const idAttr = ldId ? `data-ld-id="${ldId}"` : "";

    const html = (String.raw /*html*/ `<script type="application/ld+json" ${dataAttrs} ${idAttr}>${schema}</script>`);
    return html;
}

/**
 *! Change your IDE LSP to HTML or setup embedded language for better typing DX
 *
 * @summary Critical script that need to be applied immediately
 * - Applies theme preferences from localStorage or system settings on initial load
 * - Self-removes after execution to clean up the DOM
 * - Should be inlined in the HTML head for optimal performance and to avoid FOUC
 */
export const inlineScript = (String.raw /*html*/`
  <script id="wplokerbjm-theme-inline-script">
  (() => {
    const cleanUp = () => {
      const el = document.getElementById("wplokerbjm-theme-inline-script");
      if (el) {
        el.remove();
      }
    };

    try {
      const KEY = "wplokerbjm-theme";
      const root = document.documentElement;
      let stored = null;
      try {
        stored = localStorage.getItem(KEY);
      } catch (e) {
        stored = null;
      }
      const apply = (theme) => {
        if (!theme) return;
        root.setAttribute("data-theme", theme);
        root.classList.toggle("wplokerbjm-dark-mode-enable", theme === "dark");
      };
      if (["dark", "light", "lavender"].indexOf(stored) !== -1) {
        apply(stored);
        return;
      }
      let prefersDark = false;
      try {
        prefersDark =
          window.matchMedia?.("(prefers-color-scheme: dark)")?.matches ?? false;
      } catch {}
      const chosen = prefersDark ? "dark" : "light";
      apply(chosen);
    } catch (e) {
      console.log("fail applying theme preferences", e);
    } finally {
      cleanUp();
    }
  })();
  </script>
`);