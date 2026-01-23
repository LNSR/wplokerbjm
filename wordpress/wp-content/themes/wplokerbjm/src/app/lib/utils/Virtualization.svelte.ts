import { LRUCache } from 'lru-cache';
import type { Attachment } from "svelte/attachments";
import { SvelteMap } from "svelte/reactivity";

/**
 * Represents the state of a virtualized list, including visible items and positioning data.
 * This interface is used to manage the rendering of large lists efficiently by only showing items in view.
 */
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

/**
 * Options for configuring the list virtualization computation.
 * These parameters control how the virtualized list behaves, including scroll position, container size, and item measurements.
 */
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

/**
 * Service for handling list virtualization logic.
 * This class computes which items should be visible based on scroll position and container size,
 * using caching and binary search for performance. Note: Window-based virtualization with infinite scroll
 * inherently contributes to Cumulative Layout Shift (CLS) due to dynamic content loading, which is a limitation
 * of the web model and not addressed here.
 */
class VirtualizationService {
    private listCache = new LRUCache<string, Omit<ListVirtualizationState, 'visibleJobs'>>({ max: 100 });

    /**
     * Binary search helper for finding the insertion point in a sorted array.
     * Used to efficiently locate the starting and ending indices of visible items based on scroll position.
     * @param arr - The sorted array of cumulative positions
     * @param target - The target position to find
     * @returns The index where the target would be inserted
     */
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

    /**
     * Computes the virtualization state for a list based on the provided options.
     * This method determines which items are visible, their positions, and caches results for performance.
     * @param opts - The options for virtualization computation
     * @returns The computed virtualization state
     */
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

        // Round values for caching to reduce cache misses due to minor variations
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

    /**
     * Creates a height measurement attachment for virtualized items.
     * This function measures the height of DOM elements and updates the cardHeights map,
     * triggering reactivity for virtualization calculations.
     * @param cardHeights - The SvelteMap to store measured heights
     * @param jobId - The optional job ID to associate with the height
     * @returns An attachment function for measuring element height
     */
    createMeasureHeight(cardHeights: SvelteMap<number, number>, jobId?: number): Attachment<HTMLElement> {
        return (node: HTMLElement) => {
            const updateHeight = () => {
                const height = node.offsetHeight;
                if (typeof jobId === "number" && height > 0 && cardHeights.get(jobId) !== height) {
                    cardHeights.set(jobId, height);
                    // Trigger reactivity
                    cardHeights = new SvelteMap(cardHeights);
                }
            };

            updateHeight();

            const ro = new ResizeObserver(updateHeight);
            ro.observe(node);
            const timeoutId = setTimeout(updateHeight, 1000);

            return () => {
                ro.disconnect();
                clearTimeout(timeoutId);
            };
        };
    }
}

export const Virtualization = new VirtualizationService();
export default Virtualization;
