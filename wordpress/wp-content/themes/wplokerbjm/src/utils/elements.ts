//* Universal utility functions for DOM manipulation not tied to a specific framework.

export function removeJobPostingJsonLd(postId?: number | string): number {
    if (typeof document === 'undefined') return 0;
    let removed = 0;

    if (typeof postId !== 'undefined') {
        const selector = `script[type="application/ld+json"][data-ld-id="jobposting-${String(postId)}"]`;
        const found = document.querySelectorAll(selector);
        found.forEach(el => {
            el.remove();
            removed++;
        });
        if (removed > 0) return removed;
    }

    const explicit = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="JobPosting"]'));
    explicit.forEach(s => {
        s.remove();
        removed++;
    });
    if (removed > 0) return removed;
    return removed;
}

export function parseProps(element: Element, propAttr: string): Record<string, unknown> {
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

export function removePropsScriptFromElement(element: Element, propAttr: string): void {
    const isDev = isDevelopmentMode();
    if (isDev) return;

    try {
        const scriptElement = element.querySelector(`script[type="application/json"][${propAttr}]`) as HTMLScriptElement | null;
        if (scriptElement)
            setTimeout(() => {
                scriptElement.remove();
            }, 1000);
    } catch {
    }
}
