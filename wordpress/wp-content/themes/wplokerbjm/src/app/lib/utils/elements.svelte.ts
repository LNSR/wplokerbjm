import { MediaQuery, SvelteDate } from 'svelte/reactivity'

export function isMobile(): boolean {
    const mobileMq = (typeof window !== 'undefined') ? new MediaQuery('(max-width: 767.98px)') : null
    return mobileMq?.current ?? false;
}

export const isJobGridEl = (): HTMLElement | null => {
    if (typeof window === 'undefined') return null;
    return document.getElementById('job-grid');
}
/**
 * @see generalStore.useTimeAgo()
 * @see generalStore.useDeadline()
 * Provides reactive time updates for generalStore.useTimeAgo().
 * Creates a time side effect that updates the SvelteDate every second.
 * Returns a cleanup function to clear the interval.
 */
export function timeEffect(now?: SvelteDate): () => void {
    const id = setInterval(() => now?.setTime(Date.now()), 1000);
    return () => clearInterval(id);
}

/**
 * PortalManager encapsulates portal/teleport behaviors and exposes a reusable
 * instance for use across the app. Methods accept an optional callback invoked
 * once the append/remove operation has completed.
 */
export class PortalManager {
    static teleport = (node: HTMLElement, selector: string = 'body') => {
        if (typeof document === 'undefined') {
            return { destroy() { } };
        }
        const target = document.querySelector(selector) ?? document.body;
        try {
            target.appendChild(node);
        } catch (err) {
            try { console.error('[portal] teleport failed', err); } catch { }
        }

        return {
            destroy() {
                try { node.parentNode?.removeChild(node); } catch (err) { try { console.error('[portal] destroy failed', err); } catch { } }
            },
        };
    };

    // Append element into the target container and invoke callback once appended
    static append(el: HTMLElement | null, selector: string = 'body', callback?: () => void) {
        if (typeof document === 'undefined') { this.safeCallback(callback); return; }
        const target = document.querySelector(selector) ?? document.body;
        try {
            if (el && el.parentElement !== target) target.appendChild(el);
        } catch (err) {
            try { console.error('[portal] append failed', err); } catch { }
        }
        this.safeCallback(callback);
    }

    // Remove element from the target container and invoke callback once removed
    static remove(el: HTMLElement | null, selector: string = 'body', callback?: () => void) {
        if (typeof document === 'undefined') { this.safeCallback(callback); return; }
        const target = document.querySelector(selector) ?? document.body;
        try {
            if (el && el.parentElement === target) target.removeChild(el);
        } catch (err) {
            try { console.error('[portal] remove failed', err); } catch { }
        }
        this.safeCallback(callback);
    }

    private static safeCallback(cb?: () => void) {
        try { cb?.(); } catch (e) { try { console.error('[portal] callback failed', e); } catch { } }
    }
}
