import type { CardJob, JobDetailResponse } from '@/types'
import { utilsSEO } from "$lib/utils/SEO.svelte";
import { isMobile } from '$lib/utils/elements.svelte';
import { routeStateStore, routeStore } from '$lib/stores/Route.svelte';
import { APIService } from '@/services/APIService'
import { GoogleServices } from "@/services/Google";
/**
 * JobOverlayManager
 *
 * Central manager for the job overlay UI state and behavior.
 * Responsibilities:
 * - Track overlay open/close state and currently selected job
 * - Fetch job detail data with debounce, timeout and abort handling
 * - Provide consistent scrolling to the associated job card
 * - Integrate with browser history and SEO updates for desktop
 */
export class JobOverlayManager {
	public selectedSlug = $derived(routeStateStore.lastVisitedJob)
	public selectedJob = $state<CardJob | null>(null)

	// Overlay fetch state
	public overlayData = $state<JobDetailResponse | null>(null)
	public overlayLoading = $state(false)
	public overlayError = $state<string | null>(null)

	// Internal abort controller and debounce timer
	private currentAbortController: AbortController | null = null
	private debounceTimer: ReturnType<typeof setTimeout> | null = null
	private deliberateAbort = false

	// Scroll detection
	public isScrolling = false
	private scrollTimeout: ReturnType<typeof setTimeout> | null = null

	/**
		 * Create a JobOverlayManager instance.
		 *
		 * Registers a passive scroll listener used to detect when the user is
		 * actively scrolling so that methods like `scrollToCard` can optionally
		 * avoid interfering while the user scrolls.
		 */
	constructor() {
		if (typeof window !== "undefined") {
			window.addEventListener('scroll', this.handleScroll, { passive: true })
		}
	}

	private handleScroll = (): void => {
		// Only set isScrolling when it transitions from false to true to avoid frequent reactive churn
		if (!this.isScrolling) this.isScrolling = true
		if (this.scrollTimeout) clearTimeout(this.scrollTimeout)
		this.scrollTimeout = setTimeout(() => {
			this.isScrolling = false
		}, 500) // Adjust delay as needed
	}

	/**
	 * Open the overlay for a job.
	 *
	 * @param slug - The job slug to open
	 * @param job - Optional job object to set as the selected job immediately
	 *
	 * Notes:
	 * - On desktop this will replace the current history entry with the
	 *   job permalink path and trigger SEO/head updates.
	 * - The job detail fetch is started in a debounced manner.
	 */
	public openOverlay(slug: string, job?: CardJob): void {
		routeStateStore.MarkVisitedJob(slug)
		this.selectedJob = job ?? null

		requestAnimationFrame(() => {
			// Handle page push and SEO for desktop
			const isDesktop = !isMobile();

			if (job && job.permalink && isDesktop) {
				const url = new URL(job.permalink, routeStore.currentUrl.origin);
				const path = url.pathname + url.search + url.hash;

				// Temporarily opt-out the route container from view-transition so
				// changing history here (replaceState) does not trigger a document
				// transition animation unintended for overlays.
				const routeContainer = typeof document !== 'undefined' ? document.querySelector('.route-container') as HTMLElement | null : null;
				if (routeContainer) routeContainer.setAttribute('data-no-view-transition', 'true');

				window.history.replaceState({}, "", path);

				// Remove the flag after a couple of frames to be safe
				requestAnimationFrame(() => {
					requestAnimationFrame(() => {
						if (routeContainer) routeContainer.removeAttribute('data-no-view-transition');
					});
				});
				utilsSEO.fetchHeadData(path).then(() => {
					void utilsSEO.clearPendingJobSchemas();
					void utilsSEO.removeJobPostingJsonLd();
					void utilsSEO.addJobPostingJsonLd([Number(job.id)] as number[]);
				}).catch(() => {
					console.error('Failed to fetch head data for overlay open to', path);
				}).finally(() => {
					GoogleServices.sendPageView(path, 'overlay_page_view');
				});
			}

			// Start debounced fetch for overlay data
			this.debouncedFetch(slug)
		})
	}

	/**
	 * Smoothly scroll to the job card for the given slug.
	 *
	 * @param slug - The job slug to scroll to; defaults to `this.selectedSlug`
	 * @param skipIfScrolling - If true, skip scrolling if user is actively scrolling (default: true)
	 * @param preferredSource - Preferred source of the card ("carousel" or "grid") when multiple matches exist
	 *
	 * Notes:
	 * - If no slug is provided and `this.selectedSlug` is null, no action is taken.
	 * - The method attempts to find the closest visible card element matching
	 *   the slug, preferring the specified source if provided.
	 * - If no matching element is found, no scrolling occurs to avoid jumping
	 *   to unrelated sections.
	 */
	public scrollToCard(
		slug?: string,
		delay: number = 300,
		skipIfScrolling: boolean = true,
		preferredSource?: "carousel" | "grid"
	): void {
		const targetSlug = slug ?? this.selectedSlug;
		if (!targetSlug) return;

		if (typeof window === "undefined") return;

		// Skip if user is still scrolling and skipIfScrolling is true
		if (skipIfScrolling && this.isScrolling) return;

		setTimeout(() => {
			try {
				const safeSlug = String(targetSlug);
				const selector = `div[data-job-slug="${safeSlug}"]`;
				const candidates = Array.from(document.querySelectorAll(selector)) as HTMLElement[];
				let cardElement: HTMLElement | null = null;

				// If multiple elements match the slug (e.g., carousel + grid), prefer a
				// candidate that matches the preferred source and is visible/close to the viewport.
				if (candidates.length > 0) {
					const visibleCandidates = candidates.filter((el) => {
						const rect = el.getBoundingClientRect();
						const style = window.getComputedStyle(el);
						return (
							rect.width > 0 &&
							rect.height > 0 &&
							style.display !== "none" &&
							el.offsetParent !== null
						);
					});

					if (preferredSource) {
						// Prefer visible candidates from the requested source
						const sourceVisible = visibleCandidates.filter((el) => el.dataset.jobSource === preferredSource);
						if (sourceVisible.length > 0) {
							sourceVisible.sort((a, b) => Math.abs(a.getBoundingClientRect().top) - Math.abs(b.getBoundingClientRect().top));
							cardElement = sourceVisible[0];
						} else {
							// No visible matches for the preferred source; prefer any element from that source
							const sourceAny = candidates.filter((el) => el.dataset.jobSource === preferredSource);
							if (sourceAny.length > 0) {
								cardElement = sourceAny[0];
							}
						}
					}

					// If still no element chosen, fall back to the closest visible candidate, otherwise the first match
					if (!cardElement) {
						if (visibleCandidates.length > 0) {
							visibleCandidates.sort((a, b) => Math.abs(a.getBoundingClientRect().top) - Math.abs(b.getBoundingClientRect().top));
							cardElement = visibleCandidates[0];
						} else {
							cardElement = candidates[0];
						}
					}
				}


				if (cardElement) {
					cardElement.scrollIntoView({
						behavior: "smooth",
						block: "start",
						inline: "nearest",
					});
					return;
				}

				// No card element found — avoid jumping to unrelated sections.
			} catch (err) {
				console.error("scrollToCard error:", err);
			}
		}, delay);

	}

	/**
	 * Fetch detailed job data for the provided slug.
	 *
	 * Uses a 60 second timeout and treats aborts as non-errors when
	 * `this.deliberateAbort` is set (e.g., when closing the overlay).
	 *
	 * @param slug - The job slug to fetch details for
	 * @param signal - Optional AbortSignal to cancel the request
	 */
	private async fetchOverlayData(slug: string, signal?: AbortSignal): Promise<void> {
		this.overlayLoading = true
		this.overlayError = null
		try {
			const timeoutPromise = new Promise<never>((_, reject) => {
				setTimeout(() => reject(new Error('Timeout')), 60000)
			})
			const fetchPromise = APIService.fetchJobDetailGraphQL(slug, signal)
			const data = await Promise.race([fetchPromise, timeoutPromise])
			this.overlayData = data as JobDetailResponse
		} catch (err: any) {
			// Treat intentional aborts and AbortError-like rejections as non-errors
			const isAbortError = (err && (err as any).name === 'AbortError')
			if (this.deliberateAbort || isAbortError) {
				this.deliberateAbort = false
				this.overlayData = null
				return
			}
			if (err instanceof Error && err.message === 'Timeout') {
				this.overlayError = 'Request timed out. Please check your connection and try again.'
				this.overlayData = null
			} else {
				console.error('Error fetching single overlay:', err)
				this.overlayError = 'Gagal memuat lowongan. Silakan coba lagi.'
				this.overlayData = null
			}
		} finally {
			this.overlayLoading = false
		}
	}

	/**
	 * Debounce starting a fetch for job detail.
	 *
	 * Cancels any previous pending fetch by aborting its controller, resets
	 * overlay fetch state, and schedules `fetchOverlayData` after 600ms.
	 *
	 * @param jobSlug - Slug of the job to fetch
	 */
	private debouncedFetch(jobSlug: string): void {
		if (!jobSlug) return

		this.deliberateAbort = true
		this.currentAbortController?.abort()
		this.overlayData = null
		this.overlayError = null
		this.overlayLoading = true

		const controller = new AbortController()
		this.currentAbortController = controller

		if (this.debounceTimer) clearTimeout(this.debounceTimer)
		const slugForFetch = jobSlug
		this.debounceTimer = setTimeout(() => {
			void this.fetchOverlayData(slugForFetch, controller.signal)
		}, 600)
	}

}
export const jobOverlay = new JobOverlayManager();