import { MediaQuery, SvelteDate } from 'svelte/reactivity'

export function isMobile() {
    const mobileMq = (typeof window !== 'undefined') ? new MediaQuery('(max-width: 767.98px)') : null
    return mobileMq?.current ?? false;
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