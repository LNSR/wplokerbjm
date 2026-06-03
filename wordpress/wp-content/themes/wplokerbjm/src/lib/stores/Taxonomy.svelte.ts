import { APIServiceBrowser } from '@/services/graphql/APIService'
import type { TaxonomyTerm, TaxonomyTermsResponse, TaxonomyType, TaxonomyGroup, WPBasePost } from '@/types'
import { SvelteMap } from 'svelte/reactivity'
import typia from 'typia'

class TaxonomyManager
{
	// State
	public terms = $state<Record<TaxonomyGroup, TaxonomyTerm[]>>({
		lokasi: [],
		gender: [],
		pendidikan: [],
	})
	public loading = $state<Record<TaxonomyGroup, boolean>>({
		lokasi: false,
		gender: false,
		pendidikan: false,
	})

	public error = $state<Record<TaxonomyGroup, string | null>>({
		lokasi: null,
		gender: null,
		pendidikan: null,
	})

	public get getLoadingStatus(): boolean
	{
		return this.loading.lokasi || this.loading.gender || this.loading.pendidikan
	}

	public get anyError(): string | null
	{
		return this.error.lokasi || this.error.gender || this.error.pendidikan
	}

	public getTerms(type: TaxonomyType): TaxonomyTerm[]
	{
		return this.terms[ this.#getGroupFromType(type) ]
	}

	public async fetchTerms(type: TaxonomyType): Promise<void>
	{
		if (!typia.is<TaxonomyType>(type)) return;
		this.loading[ this.#getGroupFromType(type) ] = true
		this.error[ this.#getGroupFromType(type) ] = null

		const TAXONOMY_KEY_MAP: Record<TaxonomyType, keyof TaxonomyTermsResponse> = {
			lokasi_pekerjaan: 'lokasiTerms',
			gender: 'genderTerms',
			pendidikan: 'pendidikanTerms',
		}

		const keyAPI: keyof TaxonomyTermsResponse = TAXONOMY_KEY_MAP[ type ]

		try
		{
			const data = await APIServiceBrowser.fetchTaxonomyTermsByTypeGraphQL(keyAPI)
			this.terms[ this.#getGroupFromType(type) ] = data
			this.#loaded[ this.#getGroupFromType(type) ] = true
			this.#buildSlugMap(type, data)
		} catch (err)
		{
			this.error[ this.#getGroupFromType(type) ] = err instanceof Error ? err.message : `Failed to fetch ${keyAPI}`
			this.#loaded[ this.#getGroupFromType(type) ] = false
		} finally
		{
			this.loading[ this.#getGroupFromType(type) ] = false
		}
	}

	public getTermNameBySlug(type: TaxonomyType, slug: NonNullable<WPBasePost[ 'slug' ]>): string
	{
		const map = this.#slugMaps[ this.#getGroupFromType(type) ]
		return map.get(slug) ?? slug
	}

	#slugMaps: Record<TaxonomyGroup, SvelteMap<string, string>> = {
		lokasi: new SvelteMap<string, string>(),
		gender: new SvelteMap<string, string>(),
		pendidikan: new SvelteMap<string, string>(),
	}

	#loaded = $state<Record<TaxonomyGroup, boolean>>({
		lokasi: false,
		gender: false,
		pendidikan: false,
	})


	#buildSlugMap(type: TaxonomyType, terms: TaxonomyTerm[]): void
	{
		const map = this.#slugMaps[ this.#getGroupFromType(type) ]
		map.clear()
		function addToMap(termsList: TaxonomyTerm[])
		{
			for (const t of termsList)
			{
				map.set(t.slug, t.name)
				if (t.children && t.children.length)
				{
					addToMap(t.children)
				}
			}
		}
		addToMap(terms)
	}

	#getGroupFromType(type: TaxonomyType): TaxonomyGroup
	{
		if (type === 'lokasi_pekerjaan') return 'lokasi' // WP use lokasi_pekerjaan internally, lokasi for UI
		if (type === 'gender') return 'gender'
		return 'pendidikan'
	}
}

export const taxonomyStore = new TaxonomyManager();

