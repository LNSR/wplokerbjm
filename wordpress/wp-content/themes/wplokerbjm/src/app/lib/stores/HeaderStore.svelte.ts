import { isAppEl } from "@/utils/elements";

export class HeaderStore {

    public headerHeight = $state(0);
    public headerTop = $state(0);
    public totalOffset = $derived(this.headerHeight + this.headerTop);
    public appEl = document.querySelector(isAppEl) as HTMLElement | null;
    getWpAdminBarHeight(): number {
        const el = document.getElementById("wpadminbar");
        if (!el) return 0;
        const rect = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        const hiddenByDisplay =
            cs.display === "none" ||
            cs.visibility === "hidden" ||
            parseFloat(cs.opacity || "1") === 0;
        let translateY = 0;
        if (cs.transform && cs.transform !== "none") {
            const m = cs.transform.match(/matrix\(([^)]+)\)/);
            if (m && m[1]) {
                const parts = m[1].split(",").map((p) => parseFloat(p.trim()));
                if (parts.length === 6) translateY = parts[5] || 0;
            } else {
                const ty = cs.transform.match(/translateY\(([-0-9.]+)px\)/);
                if (ty && ty[1]) translateY = parseFloat(ty[1]);
            }
        }
        const inViewport =
            rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight;
        const transformedOff = translateY <= -Math.max(1, rect.height - 1);
        if (!hiddenByDisplay && inViewport && !transformedOff) {
            return rect.height;
        } else {
            return 0;
        }
    }

    /**
     * Compute and set CSS vars for site header (top offset and header height)
     * Returns the previous main padding value (or null) so callers can restore it later.
     */
    setSiteHeaderVars(opts?: {
        headerEl?: HTMLElement | null;
        mainEl?: HTMLElement | null;
        isMobile?: boolean;
        previousMainPadding?: string | null;
    }): { previousMainPadding: string | null } {
        const headerEl = opts?.headerEl ?? (typeof document !== 'undefined' ? document.querySelector('header') as HTMLElement | null : null);

        const adminBarHeight = this.getWpAdminBarHeight();
        const top = adminBarHeight;
        if (typeof document !== 'undefined') {
            this.appEl?.style.setProperty('--site-header-top', top + 'px');
        }

        let prevPadding: string | null = opts?.previousMainPadding ?? null;

        if (headerEl) {
            const headerHeight = headerEl.offsetHeight || 0;

            if (typeof document !== 'undefined') {
                this.appEl?.style.setProperty('--site-header-height', headerHeight + 'px');
            }

            // Also set a scroll-padding-top value so browser-native scrolling
            // (including scrollIntoView with block: 'start') respects fixed headers.
            try {
                const scrollPadding = adminBarHeight + headerHeight;
                this.appEl?.style.setProperty('--site-scroll-padding-top', scrollPadding + 'px');
            } catch {
                // Ignore if browser doesn't support setting this property on the element
            }
        } else {
            if (typeof document !== 'undefined') {
                this.appEl?.style.setProperty('--site-header-height', '0px');
                this.appEl?.style.removeProperty('--site-scroll-padding-top');
            }
        }

        return { previousMainPadding: prevPadding };
    }

}

export const headerStore = new HeaderStore();