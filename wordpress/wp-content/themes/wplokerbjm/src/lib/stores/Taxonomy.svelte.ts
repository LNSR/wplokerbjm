import { APIService } from '@/services/APIService'
import type { TaxonomyTerm, WPLokerBJMThemedData } from '@/types'
import { TaxonomyType } from '@/types'
import { themeManager } from '$lib/stores/Theme.svelte';
import { SvelteMap } from 'svelte/reactivity'

interface CachedTaxonomyData {
	version: WPLokerBJMThemedData['themeVersion']
	lastTaxonomyUpdate: string
	data: TaxonomyTerm[]
}

type TaxonomyCacheKey = `wplokerbjm_taxonomy_${TaxonomyType}`

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

	private getCachedTerms(type: TaxonomyType): TaxonomyTerm[] | null {
		if (typeof sessionStorage === 'undefined') return null
		const themeData = themeManager.getThemeData()
		if (!themeData?.themeVersion || !themeData?.lastTaxonomyUpdate) return null
		const key: TaxonomyCacheKey = `wplokerbjm_taxonomy_${type}`
		try {
			const stored = sessionStorage.getItem(key)
			if (!stored) return null
			const parsed: CachedTaxonomyData = JSON.parse(stored)
			if (parsed.version === themeData.themeVersion && parsed.lastTaxonomyUpdate === themeData.lastTaxonomyUpdate) {
				return parsed.data
			}
		} catch (err) {
			console.warn(`Failed to parse cached taxonomy ${type}:`, err)
		}
		return null
	}

	private setCachedTerms(type: TaxonomyType, data: TaxonomyTerm[]): void {
		if (typeof sessionStorage === 'undefined') return
		const themeData = themeManager.getThemeData()
		if (!themeData?.themeVersion || !themeData?.lastTaxonomyUpdate) return
		const key: TaxonomyCacheKey = `wplokerbjm_taxonomy_${type}`
		try {
			const toStore: CachedTaxonomyData = { version: themeData.themeVersion, lastTaxonomyUpdate: themeData.lastTaxonomyUpdate, data }
			sessionStorage.setItem(key, JSON.stringify(toStore))
		} catch (err) {
			console.warn(`Failed to cache taxonomy ${type}:`, err)
		}
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

	private getNameFromStorage(type: TaxonomyType, slug: string): string {
		const themeData = themeManager.getThemeData()
		if (!themeData?.themeVersion || !themeData?.lastTaxonomyUpdate) return slug
		const key: TaxonomyCacheKey = `wplokerbjm_taxonomy_${type}`
		try {
			const stored = sessionStorage.getItem(key)
			if (!stored) return slug
			const parsed: CachedTaxonomyData = JSON.parse(stored)
			if (parsed.version === themeData.themeVersion && parsed.lastTaxonomyUpdate === themeData.lastTaxonomyUpdate) {
				function find(terms: TaxonomyTerm[]): string | undefined {
					for (const t of terms) {
						if (t.slug === slug) return t.name
						if (t.children) {
							const found = find(t.children)
							if (found) return found
						}
					}
					return undefined
				}
				return find(parsed.data) || slug
			}
		} catch {
			return slug
		}
		return slug
	}

	// Actions
	public async fetchLokasiTerms(): Promise<void> {
		if (this.lokasiLoaded && !this.lokasiError) return
		// Check cache first
		const cached = this.getCachedTerms(TaxonomyType.lokasi)
		if (cached) {
			this.lokasiTerms = cached
			this.lokasiLoaded = true
			this.buildSlugMap(TaxonomyType.lokasi, cached)
			return
		}
		this.lokasiLoading = true
		this.lokasiError = null
		try {
			const data = await APIService.fetchLokasiTermsGraphQL()
			this.lokasiTerms = data
			this.lokasiLoaded = true
			this.setCachedTerms(TaxonomyType.lokasi, data)
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
		// Check cache first
		const cached = this.getCachedTerms(TaxonomyType.gender)
		if (cached) {
			this.genderTerms = cached
			this.genderLoaded = true
			this.buildSlugMap(TaxonomyType.gender, cached)
			return
		}
		this.genderLoading = true
		this.genderError = null
		try {
			const data = await APIService.fetchGenderTermsGraphQL()
			this.genderTerms = data
			this.genderLoaded = true
			this.setCachedTerms(TaxonomyType.gender, data)
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
		// Check cache first
		const cached = this.getCachedTerms(TaxonomyType.pendidikan)
		if (cached) {
			this.pendidikanTerms = cached
			this.pendidikanLoaded = true
			this.buildSlugMap(TaxonomyType.pendidikan, cached)
			return
		}
		this.pendidikanLoading = true
		this.pendidikanError = null
		try {
			const data = await APIService.fetchPendidikanTermsGraphQL()
			this.pendidikanTerms = data
			this.pendidikanLoaded = true
			this.setCachedTerms(TaxonomyType.pendidikan, data)
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
		const fromMap = map.get(slug)
		if (fromMap) return fromMap
		return this.getNameFromStorage(type, slug)
	}
}

export const taxonomyStore = new TaxonomyManager()

