//* Universal utility functions for DOM manipulation not tied to a specific framework.
import { isDevelopmentMode } from "@/utils";

export const isAppEl: string = ".route-container"; // Selector for the main application element

export function parseProps(element: Element | Document, propAttr: string): Record<string, unknown> {
    const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`);
    let props: Record<string, unknown> = {};

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
