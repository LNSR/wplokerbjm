import type { JobSchemaResponse } from "@/types";

// This file contains utility functions for generating and attaching JSON-LD scripts for SEO purposes, as well as an inline script for applying theme preferences. These functions are used in server load functions to prepare SEO data and critical scripts for the client.
export function schemaScriptAttach (
    schema: string,
    ldType: JobSchemaResponse[ "type" ],
    ldId?: string,
)
{
    if ( !schema ) return "";

    const dataAttrs = `data-ld-type="${ ldType }"`;
    const idAttr = ldId ? `data-ld-id="${ ldId }"` : "";

    return String.raw /*html*/`<script type="application/ld+json" ${ dataAttrs } ${ idAttr }>${ schema }</script>`;
}
/**
 * *Use Embedded Language for better DX.
 * 
 * This script is used to apply the user's theme preference (dark/light) as early as possible to prevent FOUC. It checks localStorage for a saved theme, falls back to system preference, and applies the theme by setting a data attribute on the root element. After execution, it removes itself from the DOM.
 */
export const inlineScript = ( String.raw /*html*/`<script id="wplokerbjm-theme-inline-script">
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
	</script>`);