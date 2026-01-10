/**
 * Singleton Virtualization helper for common virtualization calculations
 * Used by JobCarousel and JobGrid to keep logic DRY and testable
 */

import { LRUCache } from 'lru-cache';
import { innerWidth, innerHeight, scrollY } from 'svelte/reactivity/window';


export interface GridVirtualizationState<T = any> {
    /** Number of items displayed per row in the grid */
    itemsPerRow: number;
    /** Total number of rows in the grid */
    totalRows: number;
    /** Height of each row, including item height and gap */
    rowHeight: number;
    /** Top position offset of the grid section */
    sectionTop: number;
    /** Starting row index of visible rows */
    startRow: number;
    /** Ending row index of visible rows */
    endRow: number;
    /** Starting index of visible items in the flat array */
    startIndex: number;
    /** Ending index of visible items in the flat array */
    endIndex: number;
    /** Array of jobs that are currently visible */
    visibleJobs: T[];
    /** Total height of the entire grid */
    totalHeight: number;
}

export type GridOptions<T = any> = {
    /** Array of jobs to be displayed in the grid */
    displayJobs: T[];
    /** Width of the container (viewport or element) */
    innerWidth?: number;
    /** Whether an overlay is currently open, affecting layout */
    overlayOpen?: boolean;
    /** Height of the container (viewport or element) */
    innerHeight?: number;
    /** Current vertical scroll position */
    scrollY?: number;
    /** Top position of the grid section relative to the page */
    sectionTop?: number | null;
    /** Height of each individual item in the grid */
    itemHeight?: number;
    /** Gap between items in the grid */
    gap?: number;
    /** Number of extra rows/columns to render outside visible area for smooth scrolling */
    buffer?: number;
};

class VirtualizationService {
    /** LRU cache for storing computed grid virtualization states to avoid recalculations */
    private gridCache = new LRUCache<string, Omit<GridVirtualizationState, 'visibleJobs'>>({ max: 100 });

    computeGrid<T = any>(opts: GridOptions<T>): GridVirtualizationState<T> {
        const {
            displayJobs,
            innerWidth: innerWidthParam = innerWidth.current,
            overlayOpen = false,
            innerHeight: innerHeightParam = innerHeight.current,
            scrollY: scrollYParam = scrollY.current,
            sectionTop = 0,
            itemHeight = 320,
            gap = 24,
            buffer = 3,
        } = opts;

        const key = JSON.stringify({
            innerWidth: innerWidthParam,
            overlayOpen,
            innerHeight: innerHeightParam,
            scrollY: scrollYParam,
            sectionTop,
            itemHeight,
            gap,
            buffer,
            length: displayJobs.length,
        });

        const cached = this.gridCache.get(key);
        if (cached) {
            const vj = displayJobs.slice(cached.startIndex, cached.endIndex);
            return { ...cached, visibleJobs: vj };
        }

        const ipr = innerWidthParam! >= 1024 ? (overlayOpen ? 1 : 3) : innerWidthParam! >= 768 ? (overlayOpen ? 1 : 2) : 1;
        const tr = Math.ceil(displayJobs.length / ipr);
        const rh = itemHeight + gap;
        const st = sectionTop || 0;
        const sr = Math.max(0, Math.floor((scrollYParam! - st) / rh) - buffer);
        const er = Math.min(tr, sr + Math.ceil(innerHeightParam! / rh) + buffer * 2);
        const si = sr * ipr;
        const ei = Math.min(displayJobs.length, er * ipr);
        const th = tr * rh;

        const state: Omit<GridVirtualizationState<T>, 'visibleJobs'> = {
            itemsPerRow: ipr,
            totalRows: tr,
            rowHeight: rh,
            sectionTop: st,
            startRow: sr,
            endRow: er,
            startIndex: si,
            endIndex: ei,
            totalHeight: th,
        };

        this.gridCache.set(key, state);

        const vj = displayJobs.slice(si, ei);
        return { ...state, visibleJobs: vj };
    }
}

export const Virtualization = new VirtualizationService();
export default Virtualization;
