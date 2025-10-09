import { toStore, type Readable } from 'svelte/store'
import type { Job, SingleOverlayResponse } from '@/types'
import { HeaderUtils } from "$lib/stores/HeaderStore.svelte";
import { APIService } from '@/services/APIService'
import { SEOService } from "$lib/utils/SEO.svelte";

export class JobOverlayManager {
	public overlayOpen = $state(false)
	public selectedSlug = $state<string | null>(null)
	public selectedJob = $state<Job | null>(null)

	// Overlay fetch state
	public overlayData = $state<SingleOverlayResponse | null>(null)
	public overlayLoading = $state(false)
	public overlayError = $state<string | null>(null)

	// Internal abort controller and debounce timer
	private currentAbortController: AbortController | null = null
	private debounceTimer: ReturnType<typeof setTimeout> | null = null
	private deliberateAbort = false

	public readonly store: Readable<{
		overlayOpen: boolean
		selectedSlug: string | null
		selectedJob: Job | null
		overlayData: SingleOverlayResponse | null
		overlayLoading: boolean
		overlayError: string | null
	}>

	constructor() {
		this.store = toStore(() => ({
			overlayOpen: this.overlayOpen,
			selectedSlug: this.selectedSlug,
			selectedJob: this.selectedJob,
			overlayData: this.overlayData,
			overlayLoading: this.overlayLoading,
			overlayError: this.overlayError,
		}))
	}

	public async openOverlay(slug: string, job?: Job): Promise<void> {
		this.selectedSlug = slug
		this.selectedJob = job ?? null
		this.overlayOpen = true

		// Handle page push and SEO for desktop
		if (job && job.permalink && typeof window !== "undefined" && window.innerWidth >= 768) {
			const url = new URL(job.permalink, window.location.origin);
			const path = url.pathname + url.search + url.hash;
			window.history.pushState({}, "", path);

			try {
				await SEOService.fetchHeadData(path);
			} catch (e) {
				// non-fatal
			}
		}

		// Start debounced fetch for overlay data
		this.debouncedFetch(slug)
	}

	public closeOverlay(): void {
		this.overlayOpen = false
		this.selectedSlug = null
		this.selectedJob = null

		this.deliberateAbort = true
		this.currentAbortController?.abort()
		this.clearOverlayState()
	}

	public scrollToCard(slug?: string, delay = 220, buffer = 12): void {
		if (typeof window === "undefined") return
		const targetSlug = slug ?? this.selectedSlug
		if (!targetSlug) return

		setTimeout(() => {
			try {
				const safeSlug = String(targetSlug)
				const selector = `div[data-job-slug="${safeSlug}"]`
				const cardElement = document.querySelector(selector) as HTMLElement | null

				const headerOffset =
					typeof HeaderUtils !== "undefined" && HeaderUtils.getTotalHeaderOffset
						? HeaderUtils.getTotalHeaderOffset()
						: (document.querySelector("header")?.getBoundingClientRect().height ?? 0)
				const extra = Number(buffer) || 12

				if (cardElement) {
					cardElement.scrollIntoView({
						behavior: "smooth",
						block: "start",
						inline: "nearest",
					})
					return
				}

				const jobGridElement = document.getElementById("job-grid")
				if (jobGridElement) {
					const rect = jobGridElement.getBoundingClientRect()
					const scrollTop = window.pageYOffset || document.documentElement.scrollTop
					const targetY = scrollTop + rect.top - headerOffset - extra - 8
					window.scrollTo({ top: targetY, behavior: "smooth" })
				}
			} catch (err) {
				console.error("jobOverlay.scrollToCard error:", err)
			}
		}, delay)
	}

	private clearOverlayState(): void {
		this.overlayData = null
		this.overlayError = null
		this.overlayLoading = false
		if (this.debounceTimer) {
			clearTimeout(this.debounceTimer)
			this.debounceTimer = null
		}
	}

	private async fetchOverlayData(slug: string, signal?: AbortSignal): Promise<void> {
		this.overlayLoading = true
		this.overlayError = null
		try {
			const timeoutPromise = new Promise<never>((_, reject) => {
				setTimeout(() => reject(new Error('Timeout')), 10000)
			})
			const fetchPromise = APIService.fetchSingleOverlay(slug, { signal })
			const data = await Promise.race([fetchPromise, timeoutPromise])
			this.overlayData = data as SingleOverlayResponse
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
			this.fetchOverlayData(slugForFetch, controller.signal)
		}, 600)
	}

	// Subscribe helper so this manager can be used as a Svelte store if needed
	public subscribe(run: (v: {
		overlayOpen: boolean; selectedSlug: string | null; selectedJob: Job |
		null; overlayData: SingleOverlayResponse | null; overlayLoading: boolean; overlayError: string | null
	}) => void) {
		return this.store.subscribe(run)
	}
}

export const jobOverlay = new JobOverlayManager()

