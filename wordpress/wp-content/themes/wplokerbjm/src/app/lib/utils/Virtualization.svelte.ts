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

export interface ListVirtualizationState<T = any> {
    /** Array of jobs that are currently visible */
    visibleJobs: T[];
    /** Total height of the entire list */
    totalHeight: number;
    /** Starting index of visible items in the flat array */
    startIndex: number;
    /** Ending index of visible items in the flat array */
    endIndex: number;
    /** Array of cumulative positions for each item */
    itemPositions: number[];
}

export type ListOptions<T = any> = {
    /** Array of jobs to be displayed in the list */
    displayJobs: T[];
    /** Current vertical scroll position */
    scrollY: number;
    /** Height of the container */
    containerHeight: number;
    /** Map of job ID to measured card height */
    cardHeights: Map<number, number>;
    /** Fallback height for items that haven't been measured yet */
    fallbackHeight?: number;
    /** Gap between items */
    gap?: number;
    /** Number of extra items to render outside visible area for smooth scrolling */
    buffer?: number;
};

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
    /** LRU cache for storing computed list virtualization states to avoid recalculations */
    private listCache = new LRUCache<string, Omit<ListVirtualizationState, 'visibleJobs'>>({ max: 100 });

    // Binary search helper for finding insertion point in sorted array
    private binarySearch(arr: number[], target: number): number {
        let low = 0;
        let high = arr.length - 1;
        while (low <= high) {
            const mid = Math.floor((low + high) / 2);
            if (arr[mid] < target) {
                low = mid + 1;
            } else {
                high = mid - 1;
            }
        }
        return low;
    }

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

        const roundedScrollY = Math.round((scrollYParam || 0) / 50) * 50;
        const roundedInnerWidth = Math.round((innerWidthParam || 0) / 100) * 100;
        const roundedInnerHeight = Math.round((innerHeightParam || 0) / 100) * 100;

        const key = `${roundedInnerWidth}-${overlayOpen}-${roundedInnerHeight}-${roundedScrollY}-${sectionTop}-${itemHeight}-${gap}-${buffer}-${displayJobs.length}`;

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

    computeList<T = any>(opts: ListOptions<T>): ListVirtualizationState<T> {
        const {
            displayJobs,
            scrollY,
            containerHeight,
            cardHeights,
            fallbackHeight = 200,
            gap = 12,
            buffer = 2,
        } = opts;

        const roundedScrollY = Math.round(scrollY / 50) * 50;
        const roundedContainerHeight = Math.round(containerHeight / 100) * 100;
        const heightsString = Array.from(cardHeights.entries()).sort().map(([id, h]) => `${id}:${h}`).join(',');

        const key = `${roundedScrollY}-${roundedContainerHeight}-${fallbackHeight}-${gap}-${buffer}-${displayJobs.length}-${heightsString}`;

        const cached = this.listCache.get(key);
        if (cached) {
            const vj = displayJobs.slice(cached.startIndex, cached.endIndex);
            return { ...cached, visibleJobs: vj };
        }

        if (displayJobs.length === 0) {
            return {
                visibleJobs: [],
                totalHeight: 0,
                startIndex: 0,
                endIndex: 0,
                itemPositions: [],
            };
        }

        // Calculate positions based on actual card heights
        const itemPositions: number[] = [];
        let cumulativeHeight = 0;

        for (let i = 0; i < displayJobs.length; i++) {
            const job = displayJobs[i];
            const jobId = (job as any).id || 0;
            const height = cardHeights.get(jobId) || fallbackHeight;
            itemPositions.push(cumulativeHeight);
            cumulativeHeight += height + gap;
        }

        const totalHeight = cumulativeHeight;

        // Find visible items based on scroll position using binary search for performance
        const bufferHeight = 200; // Approximate height for buffer calculations
        const startPos = Math.max(0, scrollY - buffer * bufferHeight);
        const startCandidate = this.binarySearch(itemPositions, startPos);
        const startIndex = Math.max(0, startCandidate - buffer);

        // Find the end index
        const endPos = scrollY + containerHeight + buffer * bufferHeight;
        const endCandidate = this.binarySearch(itemPositions, endPos + 1); // +1 to find first > endPos
        const endIndex = endCandidate === itemPositions.length
            ? displayJobs.length
            : Math.min(displayJobs.length, endCandidate + buffer);

        const visibleJobs = displayJobs.slice(
            Math.max(0, startIndex),
            Math.max(0, endIndex)
        );

        const state: Omit<ListVirtualizationState<T>, 'visibleJobs'> = {
            totalHeight,
            startIndex: Math.max(0, startIndex),
            endIndex: Math.max(0, endIndex),
            itemPositions,
        };

        this.listCache.set(key, state);

        return { ...state, visibleJobs };
    }
}

export const Virtualization = new VirtualizationService();
export default Virtualization;
