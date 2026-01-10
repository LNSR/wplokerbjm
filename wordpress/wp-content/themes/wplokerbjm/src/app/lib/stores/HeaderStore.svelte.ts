import { isAppEl } from "@/utils/elements";

export class HeaderStore {

    public headerHeight = $state(0);
    public headerTop = $state(0);
    public totalOffset = $derived(this.headerHeight + this.headerTop);
    public appEl = document.querySelector(isAppEl) as HTMLElement | null;
    getWpAdminBarHeight(): number {
        const el = document.getElementById("wpadminbar");
        if (!el) return 0;
        // If admin bar has been moved into the header, treat it as part of the header
        const header = document.querySelector('header');
        if (header && header.contains(el)) {
            return 0;
        }

        try {
            const cs = getComputedStyle(el);
            if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity || '1') === 0) return 0;
            // Use simple height measurement (avoid parsing transform matrices)
            return el.offsetHeight || 0;
        } catch {
            return 0;
        }
    }

}

export const headerStore = new HeaderStore();