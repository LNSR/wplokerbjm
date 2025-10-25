//* Universal utility functions for DOM manipulation not tied to a specific framework.

import _ from "lodash";

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

export function isDevelopmentMode(): boolean {
    type ImportMetaLike = { env?: { DEV?: boolean } };
    return typeof import.meta !== 'undefined' && Boolean((import.meta as unknown as ImportMetaLike).env?.DEV);
}

export function removePropsScriptFromElement(element: Element | Document, propAttr?: string): void {
    const isDev = isDevelopmentMode();
    if (isDev) return;

    try {
        const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`) as HTMLScriptElement | null;
        if (scriptElement)
            setTimeout(() => {
                scriptElement.remove();
            }, 1000);
    } catch {
        // Ignore
    }
}
