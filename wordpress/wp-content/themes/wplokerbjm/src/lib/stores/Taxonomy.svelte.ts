import { APIService } from '@/services/APIService'
import type { TaxonomyTerm } from '@/types'
import { TaxonomyType } from '@/types'
import { SvelteMap } from 'svelte/reactivity'

class TaxonomyManager {
	// State
	public lokasiTerms = $state<TaxonomyTerm[]>([])
	public genderTerms = $state<TaxonomyTerm[]>([])
	public pendidikanTerms = $state<TaxonomyTerm[]>([])

	public lokasiLoaded = $state(false)
	public genderLoaded = $state(false)
	public pendidikanLoaded = $state(false)

	public lokasiLoading = $state(false)
	public genderLoading = $state(false)
	public pendidikanLoading = $state(false)

	public lokasiError = $state<string | null>(null)
	public genderError = $state<string | null>(null)
	public pendidikanError = $state<string | null>(null)

	public lokasiSlugMap = new SvelteMap<string, string>()
	public genderSlugMap = new SvelteMap<string, string>()
	public pendidikanSlugMap = new SvelteMap<string, string>()

	// Computed accessors
	public get loading(): boolean {
		return this.lokasiLoading || this.genderLoading || this.pendidikanLoading
	}

	public get isLoaded(): boolean {
		return this.lokasiLoaded && this.genderLoaded && this.pendidikanLoaded
	}

	public get hasTerms(): boolean {
		return (
			this.lokasiTerms.length > 0 ||
			this.genderTerms.length > 0 ||
			this.pendidikanTerms.length > 0
		)
	}


	private buildSlugMap(type: TaxonomyType, terms: TaxonomyTerm[]): void {
		let map: SvelteMap<string, string>
		if (type === TaxonomyType.lokasi) map = this.lokasiSlugMap
		else if (type === TaxonomyType.gender) map = this.genderSlugMap
		else map = this.pendidikanSlugMap
		map.clear()
		function addToMap(termsList: TaxonomyTerm[]) {
			for (const t of termsList) {
				map.set(t.slug, t.name)
				if (t.children && t.children.length) {
					addToMap(t.children)
				}
			}
		}
		addToMap(terms)
	}


	// Actions
	public async fetchLokasiTerms(): Promise<void> {
		if (this.lokasiLoaded && !this.lokasiError) return
		this.lokasiLoading = true
		this.lokasiError = null
		try {
			const data = await APIService.fetchLokasiTermsGraphQL()
			this.lokasiTerms = data
			this.lokasiLoaded = true
			this.buildSlugMap(TaxonomyType.lokasi, data)
		} catch (err) {
			this.lokasiError = err instanceof Error ? err.message : 'Failed to fetch lokasi terms'
			this.lokasiLoaded = false
		} finally {
			this.lokasiLoading = false
		}
	}

	public async fetchGenderTerms(): Promise<void> {
		if (this.genderLoaded && !this.genderError) return
		this.genderLoading = true
		this.genderError = null
		try {
			const data = await APIService.fetchGenderTermsGraphQL()
			this.genderTerms = data
			this.genderLoaded = true
			this.buildSlugMap(TaxonomyType.gender, data)
		} catch (err) {
			this.genderError = err instanceof Error ? err.message : 'Failed to fetch gender terms'
			this.genderLoaded = false
		} finally {
			this.genderLoading = false
		}
	}

	public async fetchPendidikanTerms(): Promise<void> {
		if (this.pendidikanLoaded && !this.pendidikanError) return
		this.pendidikanLoading = true
		this.pendidikanError = null
		try {
			const data = await APIService.fetchPendidikanTermsGraphQL()
			this.pendidikanTerms = data
			this.pendidikanLoaded = true
			this.buildSlugMap(TaxonomyType.pendidikan, data)
		} catch (err) {
			this.pendidikanError = err instanceof Error ? err.message : 'Failed to fetch pendidikan terms'
			this.pendidikanLoaded = false
		} finally {
			this.pendidikanLoading = false
		}
	}

	public clearTerms(): void {
		this.lokasiTerms = []
		this.genderTerms = []
		this.pendidikanTerms = []
		this.lokasiLoaded = false
		this.genderLoaded = false
		this.pendidikanLoaded = false
		this.lokasiError = null
		this.genderError = null
		this.pendidikanError = null
		this.lokasiSlugMap.clear()
		this.genderSlugMap.clear()
		this.pendidikanSlugMap.clear()
	}

	public resetLokasiError(): void {
		this.lokasiError = null
	}
	public resetGenderError(): void {
		this.genderError = null
	}
	public resetPendidikanError(): void {
		this.pendidikanError = null
	}

	public getTermNameBySlug(type: TaxonomyType, slug: string): string {
		let map: SvelteMap<string, string>
		if (type === TaxonomyType.lokasi) map = this.lokasiSlugMap
		else if (type === TaxonomyType.gender) map = this.genderSlugMap
		else map = this.pendidikanSlugMap
		return map.get(slug) ?? slug
	}
}

export const taxonomyStore = new TaxonomyManager()

