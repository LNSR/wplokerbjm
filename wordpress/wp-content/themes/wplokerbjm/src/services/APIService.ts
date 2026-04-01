import typia from "typia";
import type {
  SearchFilters,
  LoadMoreFilters,
  JobGridFilters,
  CarouselProps,
  LoadMoreResponse,
  JobGridProps,
  CardJob,
  JobSchemaResponse,
  JobDetailResponse,
  WPLokerBJMThemedData,
  SearchResponse,
  TaxonomyTermsResponse,
} from "@/types";
import { createClient, fetchExchange } from "urql";
import type { Client, ClientOptions, DocumentInput, AnyVariables } from "urql";
import { persistedExchange } from "@urql/exchange-persisted";
import { getCmsOrigin } from "@/utils/environment";
import { themeManager } from "$lib/stores/Theme.svelte";
import
{
  GET_JOB_DETAIL,
  GET_RANK_MATH_HEAD,
  GET_JOB_SCHEMA,
  GET_THEME_DATA,
  GET_JWT,
  GET_AUTO_SUGGESTIONS,
  GET_LOKASI_TERMS,
  GET_GENDER_TERMS,
  GET_PENDIDIKAN_TERMS,
  GET_SEARCH_JOBS,
  GET_LOAD_MORE,
  GET_THEME_NONCE,
  SYNC_BOOKMARK,
  GET_CAROUSEL,
  GET_JOB_GRID,
} from "@/services/api/graphql/query";

type BookmarkResponse = CardJob[];
class URQLClientManager
{
  private static clients = new Map<string, Client>();

  private static preferHTTPMethod (
    httpMethod?: ClientOptions[ "preferGetMethod" ],
  ): ClientOptions[ "preferGetMethod" ]
  {
    return typia.assertEquals<ClientOptions[ "preferGetMethod" ]>( httpMethod ?? "within-url-limit" );
  }

  private static urqlOptions (
    preferHTTPMethodOption?: ClientOptions[ "preferGetMethod" ],
  ): ClientOptions
  {
    return {
      url: `${ getCmsOrigin() }/graphql`,
      exchanges: [
        persistedExchange( {
          preferGetForPersistedQueries: this.preferHTTPMethod( preferHTTPMethodOption ),
          enforcePersistedQueries: false,
          enableForMutation: true,
          enableForSubscriptions: true,
        } ), fetchExchange ],
      preferGetMethod: this.preferHTTPMethod( preferHTTPMethodOption ),
      fetchOptions: () => ( {
        credentials: "include",
        mode: "cors",
        headers: {
          ...( themeManager.getNonce
            ? { "X-WP-Nonce": themeManager.getNonce }
            : {} ),
        },
      } ),
    };
  }

  static getClient (
    preferHTTPMethodOption?: ClientOptions[ "preferGetMethod" ],
    fetchFn?: typeof fetch,
  ): Client
  {
    const baseOptions = this.urqlOptions( preferHTTPMethodOption );

    const key = String( preferHTTPMethodOption ?? "default" );

    if ( fetchFn )
    {
      return createClient( { ...baseOptions, fetch: fetchFn } );
    }

    if ( this.clients.has( key ) ) return this.clients.get( key )!;

    const client = createClient( { ...baseOptions } );
    this.clients.set( key, client );
    return client;
  }

  static async runQuery<T, V extends AnyVariables = AnyVariables> (
    query: DocumentInput<T, V>,
    variables: V,
    context?: any,
    httpMethodPref?: ClientOptions[ "preferGetMethod" ],
    fetchFn?: typeof fetch,
  ): Promise<T>
  {
    const graphqlClient = this.getClient( httpMethodPref, fetchFn );
    const result = await graphqlClient
      .query<T, V>( query, variables, context )
      .toPromise();
    if ( result.error ) throw result.error;
    return result.data as T;
  }

  static async runMutation<T, V extends AnyVariables = AnyVariables> (
    mutation: DocumentInput<T, V>,
    variables: V,
    context?: any,
    fetchFn?: typeof fetch,
  ): Promise<T>
  {
    const graphqlClient = this.getClient( undefined, fetchFn );
    const result = await graphqlClient
      .mutation<T, V>( mutation, variables, context )
      .toPromise();
    if ( result.error ) throw result.error;
    return result.data as T;
  }

  static mergedFetchOptionsContext ( signal?: AbortSignal ):
    {
      fetchOptions: (
        clientFetchOptions?: RequestInit | ( () => RequestInit ) | undefined,
      ) => RequestInit;
    }
  {
    return {
      fetchOptions: ( clientFetchOptions?: RequestInit | ( () => RequestInit ) ) =>
      {
        const defaultGetter = this.urqlOptions?.().fetchOptions;
        const baseDefaults =
          typeof defaultGetter === "function"
            ? ( defaultGetter() as RequestInit )
            : ( defaultGetter as RequestInit | undefined );
        const base =
          typeof clientFetchOptions === "function"
            ? clientFetchOptions()
            : ( clientFetchOptions ) || baseDefaults || {};
        const baseHeaders = base.headers instanceof Headers ? Object.fromEntries( base.headers.entries() ) : ( base.headers as Record<string, string> | undefined ) || {};
        return {
          ...base,
          ...( signal ? { signal } : {} ),
          headers: {
            ...baseHeaders,
          },
        };
      },
    };
  }
}

export class APIServiceHelper
{
  // Strip WP origin from permalink
  public static normalizeJob<T extends Record<string, unknown> | null> ( job: T ): T
  {
    if ( !job || typeof job !== "object" ) 
    {
      return job;
    }
    if ( typeof job.permalink === "string" )
    {
      let p = job.permalink.replace( /\/+$/g, "" );
      try
      {
        const u = new URL( p );
        p = u.pathname;
      } catch ( e )
      {
        console.error( "Invalid URL in job permalink:", job.permalink, e );
      }
      job.permalink = p;
    }
    // trailing slash in slug can cause issues with job detail fetching, so normalize it as well
    if ( typeof job.slug === "string" )
    {
      job.slug = job.slug.replace( /\/+$/g, "" );
    }
    return job;
  }
  /** 
   * GraphQL Taxonomies use JSON Scalar so detect and parse JSON strings in the response 
   * @param jsonString - The JSON string to parse from GraphQL response
   * @returns Parsed object of type T
   * @throws Error if the input is not a string or if JSON parsing fails
   * @remarks This is necessary because the GraphQL API returns taxonomy terms as JSON-encoded strings, which need to be parsed back into objects for use in the application.
  */
  public static parseGQLJSON<T> ( jsonString: unknown ): T
  {
    try
    {
      typia.assertGuard<string>( jsonString );
      return JSON.parse( jsonString ) as T;
    } catch ( e )
    {
      console.error( "Failed to parse JSON from GraphQL response:", jsonString, e );
      throw new Error( "Invalid JSON format in GraphQL response" );
    }
  }
}

/**
 * @todo Move to dedicated *.server file
 */
export class APIServiceServer
{
  static async fetchJobDetailGraphQL (
    slug: string,
    signal?: AbortSignal,
    fetchFn?: typeof fetch,
  ): Promise<JobDetailResponse>
  {
    slug = slug.replace( /\/+$/g, "" ); // ensure no trailing slash
    const data = await URQLClientManager.runQuery(
      GET_JOB_DETAIL,
      { slug },
      URQLClientManager.mergedFetchOptionsContext( signal ),
      undefined,
      fetchFn,
    );
    const job = data.jobDetail;

    const normalizedJob = APIServiceHelper.normalizeJob( job );
    return typia.assertEquals<JobDetailResponse>( normalizedJob );
  }
  //* SEO related (GraphQL proxied version)
  static async getRankMathHeadGraphQL (
    url: string,
    signal?: AbortSignal,
    fetchFn?: typeof fetch,
  ): Promise<string>
  {
    const data = await URQLClientManager.runQuery(
      GET_RANK_MATH_HEAD,
      { url },
      URQLClientManager.mergedFetchOptionsContext( signal ),
      undefined,
      fetchFn,
    );

    return typia.assertEquals<string>( data.rankMathHead ?? "" );
  }

  static async fetchJobSchemasGraphQL (
    idsOrSlug?: number[] | string,
    signal?: AbortSignal,
    type?: JobSchemaResponse[ "type" ],
    fetchFn?: typeof fetch,
  ): Promise<JobSchemaResponse[ "schemas" ]>
  {

    const variables: { slug?: string; ids?: number[]; type?: string } = {};

    if ( typia.is<string>( idsOrSlug ) )
    {
      variables.slug = idsOrSlug;
    } else if ( typia.is<number[]>( idsOrSlug ) )
    {
      variables.ids = idsOrSlug;
    }

    if ( type ) variables.type = typia.assertEquals<typeof type>( type );

    const data = await URQLClientManager.runQuery(
      GET_JOB_SCHEMA,
      variables,
      URQLClientManager.mergedFetchOptionsContext( signal ),
      undefined,
      fetchFn,
    );

    const schemas = data.jobSchema?.schemas;

    return schemas?.filter( ( s ): s is string => s !== null ).map( ( s ) => JSON.parse( s ) );
  }

  //* Theme data related
  static async getThemeDataGraphQL (
    signal?: AbortSignal,
    fetchFn?: typeof fetch,
  ): Promise<WPLokerBJMThemedData>
  {
    const data = await URQLClientManager.runQuery(
      GET_THEME_DATA,
      {},
      URQLClientManager.mergedFetchOptionsContext( signal ),
      undefined,
      fetchFn,
    );
    return typia.assertEquals<WPLokerBJMThemedData>( data.themeData );
  }
}

export class APIServiceBrowser
{

  static async getJWTGraphQL (
    options: {
      username?: string;
      password?: string;
      token?: string;
    },
    fetchFn?: typeof fetch,
  ): Promise<string | null>
  {
    const assert = typia.createAssertEquals<typeof options>();
    const validated = assert( options );
    const data = await URQLClientManager.runMutation(
      GET_JWT,
      {
        username: validated.username,
        password: validated.password,
        token: validated.token,
      },
      URQLClientManager.mergedFetchOptionsContext(),
      fetchFn,
    );
    return typia.assertEquals<string | null>( data.jwt );
  }

  //* Jobs related (GraphQL versions)
  static async getAutoSuggestionsGraphQL (
    query: string,
    fetchFn?: typeof fetch,
  ): Promise<string[]>
  {
    const data = await URQLClientManager.runQuery(
      GET_AUTO_SUGGESTIONS,
      { query },
      undefined,
      undefined,
      fetchFn,
    );
    return ( data.autoSuggestions ?? [] ).filter( ( s ): s is string => s !== null );
  }
  static async fetchTaxonomyTermsByTypeGraphQL (
    type: keyof TaxonomyTermsResponse,
    fetchFn?: typeof fetch,
  ): Promise<TaxonomyTermsResponse[ typeof type ]>
  {
    const queryMap: Record<keyof TaxonomyTermsResponse, DocumentInput> = {
      lokasiTerms: GET_LOKASI_TERMS,
      genderTerms: GET_GENDER_TERMS,
      pendidikanTerms: GET_PENDIDIKAN_TERMS,
    };

    const query = queryMap[ type ];
    if ( !query )
    {
      throw new Error( `Unsupported taxonomy type: ${ type }` );
    }

    const data = await URQLClientManager.runQuery( query, {}, undefined, undefined, fetchFn );
    const terms = data[ type ];
    return typia.assertEquals<TaxonomyTermsResponse[ typeof type ]>( APIServiceHelper.parseGQLJSON( terms ) );
  }
  //* Jobs related
  static async searchJobsGraphQL (
    filters: SearchFilters,
    fetchFn?: typeof fetch,
  ): Promise<SearchResponse>
  {
    const data = await URQLClientManager.runQuery(
      GET_SEARCH_JOBS,
      { filters },
      undefined,
      undefined,
      fetchFn,
    );
    const resp = data.searchJobs;
    if ( resp?.jobs && Array.isArray( resp.jobs ) )
    {
      resp.jobs = resp.jobs.map( ( j ) => APIServiceHelper.normalizeJob( j ) );
    }
    return typia.assert<SearchResponse>( resp );
  }
  static async loadMoreJobsGraphQL (
    filters: LoadMoreFilters,
    fetchFn?: typeof fetch,
  ): Promise<LoadMoreResponse>
  {
    const { paged, context, ...filterFields } = filters;
    const data = await URQLClientManager.runQuery(
      GET_LOAD_MORE,
      { paged, context, filters: filterFields },
      undefined,
      undefined,
      fetchFn,
    );
    const result = data.loadMore;
    if ( result?.jobs && Array.isArray( result.jobs ) )
    {
      result.jobs = result.jobs.map( ( j ) => APIServiceHelper.normalizeJob( j ) );
    }

    return typia.assertEquals<LoadMoreResponse>( result );
  }

  static async getThemeNonceGraphQL (): Promise<WPLokerBJMThemedData[ "wpRestNonce" ]>
  {
    const data = await URQLClientManager.runQuery( GET_THEME_NONCE, {}, undefined, false );
    return typia.assertEquals<string | null>( data.themeData?.wpRestNonce );
  }

  static async syncBookmarkGraphQL (
    ids: number[]
  ): Promise<BookmarkResponse>
  {
    const data = await URQLClientManager.runQuery( SYNC_BOOKMARK, { ids }, undefined, undefined );
    const normalized = ( data.syncBookmark )?.map( ( j ) => APIServiceHelper.normalizeJob( j ) );

    return typia.assertEquals<BookmarkResponse>( normalized );
  }

}

export class APIServiceShared
{
  //* Jobs related
  static async fetchCarouselGraphQL ( fetchFn?: typeof fetch ): Promise<CarouselProps>
  {
    const data = await URQLClientManager.runQuery( GET_CAROUSEL, {}, undefined, undefined, fetchFn );
    const carousel = data.carousel;
    if ( carousel?.jobs && Array.isArray( carousel.jobs ) )
    {
      carousel.jobs = carousel.jobs.map( ( j ) => APIServiceHelper.normalizeJob( j ) );
    }
    return typia.assertEquals<CarouselProps>( carousel );
  }
  static async fetchJobGridGraphQL (
    filters: JobGridFilters,
    fetchFn?: typeof fetch,
  ): Promise<JobGridProps>
  {
    const { sort, paged, context, title, total_jobs, ...filterFields } = filters;
    const data = await URQLClientManager.runQuery(
      GET_JOB_GRID,
      { sort, paged, context, title, total_jobs, filters: filterFields },
      undefined,
      undefined,
      fetchFn,
    );
    const grid = data.jobGrid;
    if ( grid?.jobs && Array.isArray( grid.jobs ) )
    {
      grid.jobs = grid.jobs.map( ( j ) => APIServiceHelper.normalizeJob( j ) );
    }

    return typia.assertEquals<JobGridProps>( grid );
  }
  // static async fetchAllTermsGraphQL(): Promise<TaxonomyTermsResponse> {
  //   const data = await URQLClientManager.runQuery(GET_ALL_TERMS, {}, undefined, undefined);

  //   const taxonomyTerms = data.taxonomyTerms as TaxonomyTermsResponse;
  //   const keys = ["lokasiTerms", "genderTerms", "pendidikanTerms"] as const;

  //   const parsedTerms = Object.fromEntries(
  //     keys.map((key) => [
  //       key,
  //       APIService.parseGQLJSON<TaxonomyTermsResponse[typeof key]>(taxonomyTerms?.[key])
  //     ])
  //   );

  //   return typia.assertEquals<TaxonomyTermsResponse>(parsedTerms);
  // }
}