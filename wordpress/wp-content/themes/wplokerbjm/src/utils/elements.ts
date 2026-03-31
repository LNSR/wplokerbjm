//* Universal utility functions for DOM manipulation not tied to a specific framework.
import { dev } from "$app/environment";

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
    if (dev) return;

    const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`) as HTMLScriptElement | null;
    if (scriptElement) scriptElement.remove();
}