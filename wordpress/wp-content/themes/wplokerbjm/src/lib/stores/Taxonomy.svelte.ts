import { APIServiceBrowser } from '@/services/graphql/APIService'
import type { TaxonomyTerm, TaxonomyTermsResponse, TaxonomyType, TaxonomyGroup } from '@/types'
import { SvelteMap } from 'svelte/reactivity'
import typia from 'typia'

class TaxonomyManager
{
	// State
	public terms = $state( {
		lokasi: [] as TaxonomyTerm[],
		gender: [] as TaxonomyTerm[],
		pendidikan: [] as TaxonomyTerm[],
	} )

	private loaded = $state( {
		lokasi: false,
		gender: false,
		pendidikan: false,
	} )

	public loading = $state( {
		lokasi: false,
		gender: false,
		pendidikan: false,
	} )

	public error = $state( {
		lokasi: null as string | null,
		gender: null as string | null,
		pendidikan: null as string | null,
	} )

	public get anyError(): string | null
	{
		return this.error.lokasi || this.error.gender || this.error.pendidikan
	}

	private slugMaps: Record<TaxonomyGroup, SvelteMap<string, string>> = {
		lokasi: new SvelteMap<string, string>(),
		gender: new SvelteMap<string, string>(),
		pendidikan: new SvelteMap<string, string>(),
	}

	public get getLoadingStatus(): boolean
	{
		return this.loading.lokasi || this.loading.gender || this.loading.pendidikan
	}

	private getGroupFromType( type: TaxonomyType ): TaxonomyGroup
	{
		if ( type === 'lokasi_pekerjaan' ) return 'lokasi'
		if ( type === 'gender' ) return 'gender'
		return 'pendidikan'
	}

	public getTerms( type: TaxonomyType ): TaxonomyTerm[]
	{
		return this.terms[ this.getGroupFromType( type ) ]
	}


	private buildSlugMap( type: TaxonomyType, terms: TaxonomyTerm[] ): void
	{
		const map = this.slugMaps[ this.getGroupFromType( type ) ]
		map.clear()
		function addToMap( termsList: TaxonomyTerm[] )
		{
			for ( const t of termsList )
			{
				map.set( t.slug, t.name )
				if ( t.children && t.children.length )
				{
					addToMap( t.children )
				}
			}
		}
		addToMap( terms )
	}

	public async fetchTerms( type: TaxonomyType ): Promise<void>
	{
		if ( !typia.is<TaxonomyType>( type ) ) return;
		this.loading[ this.getGroupFromType( type ) ] = true
		this.error[ this.getGroupFromType( type ) ] = null
		let keyAPI: keyof TaxonomyTermsResponse
		switch ( type )
		{
			case 'lokasi_pekerjaan':
				keyAPI = 'lokasiTerms'
				break
			case 'gender':
				keyAPI = 'genderTerms'
				break
			case 'pendidikan':
				keyAPI = 'pendidikanTerms'
				break
			default:
				throw new Error( `Unsupported taxonomy type: ${ type }` )
		}

		try
		{
			const data = await APIServiceBrowser.fetchTaxonomyTermsByTypeGraphQL( keyAPI )
			this.terms[ this.getGroupFromType( type ) ] = data
			this.loaded[ this.getGroupFromType( type ) ] = true
			this.buildSlugMap( type, data )
		} catch ( err )
		{
			this.error[ this.getGroupFromType( type ) ] = err instanceof Error ? err.message : `Failed to fetch ${ keyAPI }`
			this.loaded[ this.getGroupFromType( type ) ] = false
		} finally
		{
			this.loading[ this.getGroupFromType( type ) ] = false
		}
	}

	public getTermNameBySlug( type: TaxonomyType, slug: string ): string
	{
		const map = this.slugMaps[ this.getGroupFromType( type ) ]
		return map.get( slug ) ?? slug
	}
}

export const taxonomyStore = new TaxonomyManager();

