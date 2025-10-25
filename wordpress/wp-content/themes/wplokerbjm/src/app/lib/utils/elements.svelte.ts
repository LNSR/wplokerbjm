import { MediaQuery } from 'svelte/reactivity'

let _jobPostingJsonLdRemovalAttempted = $state(false); // Ensure we only attempt removal once.

export function isMobile() {
    const mobileMq = (typeof window !== 'undefined') ? new MediaQuery('(max-width: 767.98px)') : null
    return mobileMq?.current ?? false;
}

/**
 * Attempt to remove JobPosting JSON-LD, but only on the first attempt.
 * Subsequent calls become no-ops.
 */
export function removeJobPostingJsonLd(postId?: number | string, context?: string): number {
    if (_jobPostingJsonLdRemovalAttempted) return 0;

    try {
        let removed = 0;

        if (typeof document === 'undefined') return removed;

        if (typeof postId !== 'undefined') {
            const selector = `script[type="application/ld+json"][data-ld-id="jobposting-${String(postId)}"]`;
            const found = document.querySelectorAll(selector);
            found.forEach(el => {
                el.remove();
                removed++;
            });
            if (removed > 0) {
                _jobPostingJsonLdRemovalAttempted = true;
                return removed;
            }
        }

        const explicit = Array.from(document.querySelectorAll('script[type="application/ld+json"][data-ld-type="JobPosting"]'));
        explicit.forEach(s => {
            s.remove();
            removed++;
        });

        _jobPostingJsonLdRemovalAttempted = true;
        return removed;
    } catch (e) {
        console.warn(context ? `Failed to remove JobPosting JSON-LD (${context})` : 'Failed to remove JobPosting JSON-LD', e);
        _jobPostingJsonLdRemovalAttempted = true;
        return 0;
    }
}