import type { SearchFilters, LoadMoreFilters, JobGridFilters, CarouselProps, LoadMoreResponse, JobGridProps, JobDetailResponse, CardJob, WPLokerBJMThemedData, SearchResponse, TaxonomyApiInterface } from '@/types';
import { LRUCache } from 'lru-cache';
import { createClient, fetchExchange } from 'urql';
import { persistedExchange } from '@urql/exchange-persisted';
import { GET_ALL_TERMS, GET_LOKASI_TERMS, GET_GENDER_TERMS, GET_PENDIDIKAN_TERMS, GET_AUTO_SUGGESTIONS, GET_CAROUSEL, GET_LOAD_MORE, GET_JOB_GRID, GET_JOB_DETAIL, GET_JOB_SCHEMA, GET_THEME_DATA, GET_SEARCH_JOBS, SYNC_BOOKMARK, GET_RANK_MATH_HEAD, GET_THEME_NONCE } from '@/services/api/graphql/query';
import { NonceManager } from '@/utils';

const cache = new LRUCache<string, any>({
  max: 5, // Maximum number of items
  ttl: 10000, // 10 seconds in milliseconds
  ttlAutopurge: true,
}); // prevent excessive API calls

const graphqlClient = createClient({
  url: '/graphql',
  exchanges: [
    persistedExchange({
      preferGetForPersistedQueries: true,
      enforcePersistedQueries: false,
    }),
    fetchExchange,
  ],
  preferGetMethod: 'within-url-limit',
  fetchOptions: () => ({
    credentials: 'include',
    headers: {
      ...(NonceManager.getNonce ? { 'X-WP-Nonce': NonceManager.getNonce } : {}),
    },
  }),
});

/**
 * Build a request context that MERGES the client's default fetchOptions with
 * per-request options (signal, etc.) and ensures the X-WP-Nonce header is
 * preserved/added. urql will call this function with the client's fetchOptions
 * when provided as a function, allowing safe merging instead of replacement.
 */
const mergedFetchOptionsContext = (signal?: AbortSignal) => ({
  fetchOptions: (clientFetchOptions?: any) => {
    const base = typeof clientFetchOptions === 'function' ? clientFetchOptions() : (clientFetchOptions || {});
    const baseHeaders = base?.headers || {};
    const nonceHeader = NonceManager.getNonce ? { 'X-WP-Nonce': NonceManager.getNonce } : {};
    return {
      ...base,
      ...(signal ? { signal } : {}),
      headers: {
        ...baseHeaders,
        ...nonceHeader,
      },
    };
  }
});

type BookmarkedJobsResponse = CardJob[]
type JobSchemaResponse = Record<string, any>[]
type AutoSuggestResponse = string[]


export class APIService {
  private static cacheKey(prefix: string, payload?: any): string {
    if (payload === undefined) return prefix;
    if (typeof payload === 'string' || typeof payload === 'number' || typeof payload === 'boolean') {
      return `${prefix}:${String(payload)}`;
    }
    return `${prefix}:${APIService._stableStringify(payload)}`;
  }

  private static _stableStringify(value: any): string {
    if (value === null) return 'null';
    if (typeof value !== 'object') return JSON.stringify(value);
    if (Array.isArray(value)) return `[${value.map(APIService._stableStringify).join(',')}]`;
    const keys = Object.keys(value).sort();
    return `{${keys.map(k => JSON.stringify(k) + ':' + APIService._stableStringify(value[k])).join(',')}}`;
  }

  private static async cached<T>(key: string, fn: () => Promise<T>): Promise<T> {
    const hit = cache.get(key);
    if (hit !== undefined) return hit;
    const value = await fn();
    cache.set(key, value);
    return value;
  }

  private static async runQuery<T = any>(query: any, variables?: any, context?: any): Promise<T> {
    const result = await graphqlClient.query(query, variables, context).toPromise();
    if (result.error) throw result.error;
    return result.data;
  }

  //* Jobs related (GraphQL versions)
  static async getAutoSuggestionsGraphQL(query: string): Promise<AutoSuggestResponse> {
    const key = APIService.cacheKey('getAutoSuggestionsGraphQL', query);
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_AUTO_SUGGESTIONS, { query });
      return data.autoSuggestions;
    });
  }

  //* Taxonomy related (GraphQL versions)
  static async fetchAllTermsGraphQL(): Promise<TaxonomyApiInterface['getAllTerms']> {
    const key = APIService.cacheKey('fetchAllTermsGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_ALL_TERMS, {});
      return JSON.parse(data.taxonomyTerms);
    });
  }

  static async fetchLokasiTermsGraphQL(): Promise<ReturnType<TaxonomyApiInterface['getTermsByType']>> {
    const key = APIService.cacheKey('fetchLokasiTermsGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_LOKASI_TERMS, {});
      return JSON.parse(data.lokasiTerms);
    });
  }

  static async fetchGenderTermsGraphQL(): Promise<ReturnType<TaxonomyApiInterface['getTermsByType']>> {
    const key = APIService.cacheKey('fetchGenderTermsGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_GENDER_TERMS, {});
      return JSON.parse(data.genderTerms);
    });
  }

  static async fetchPendidikanTermsGraphQL(): Promise<ReturnType<TaxonomyApiInterface['getTermsByType']>> {
    const key = APIService.cacheKey('fetchPendidikanTermsGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_PENDIDIKAN_TERMS, {});
      return JSON.parse(data.pendidikanTerms);
    });
  }

  //* Jobs related
  static async fetchCarouselGraphQL(): Promise<CarouselProps> {
    const key = APIService.cacheKey('fetchCarouselGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_CAROUSEL, {});
      return data.carousel;
    });
  }

  static async loadMoreJobsGraphQL(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    const key = APIService.cacheKey('loadMoreJobsGraphQL', filters);
    return APIService.cached(key, async () => {
      const { paged, context, ...filterFields } = filters;
      const data = await APIService.runQuery(GET_LOAD_MORE, { paged, context, filters: filterFields });
      return data.loadMore;
    });
  }

  static async fetchJobGridGraphQL(filters: JobGridFilters): Promise<JobGridProps> {
    const key = APIService.cacheKey('fetchJobGridGraphQL', filters);
    return APIService.cached(key, async () => {
      const { paged, context, title, total_jobs, ...filterFields } = filters;
      const data = await APIService.runQuery(GET_JOB_GRID, { paged, context, title, total_jobs, filters: filterFields });
      return data.jobGrid;
    });
  }

  static async fetchJobDetailGraphQL(slug: string, signal?: AbortSignal): Promise<JobDetailResponse> {
    const key = APIService.cacheKey('fetchJobDetailGraphQL', slug);
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_JOB_DETAIL, { slug }, mergedFetchOptionsContext(signal));
      return data.jobDetail.job;
    });
  }

  static async fetchJobSchemasGraphQL(ids: number[], signal?: AbortSignal, type?: string): Promise<JobSchemaResponse> {
    const key = APIService.cacheKey('fetchJobSchemasGraphQL', { ids, type });
    return APIService.cached(key, async () => {
      const variables: any = { ids };
      if (type) variables.type = type;
      const data = await APIService.runQuery(GET_JOB_SCHEMA, variables, mergedFetchOptionsContext(signal));
      return data.jobSchema.schemas.map((s: string) => JSON.parse(s));
    });
  }

  //* Theme data related
  static async getThemeDataGraphQL(): Promise<WPLokerBJMThemedData> {
    const key = APIService.cacheKey('getThemeDataGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_THEME_DATA, {});
      return data.themeData.data;
    });
  }

  static async getThemeNonceGraphQL(signal?: AbortSignal): Promise<string | null> {
    const key = APIService.cacheKey('getThemeNonceGraphQL');
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_THEME_NONCE, {}, mergedFetchOptionsContext(signal));
      return data.themeData.data?.wpRestNonce ?? null;
    });
  }

  //* Jobs related
  static async searchJobsGraphQL(filters: SearchFilters): Promise<SearchResponse> {
    const key = APIService.cacheKey('searchJobsGraphQL', filters);
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_SEARCH_JOBS, { filters });
      return data.searchJobs;
    });
  }

  static async syncBookmarkGraphQL(ids: number[]): Promise<BookmarkedJobsResponse> {
    const key = APIService.cacheKey('syncBookmarkGraphQL', ids);
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(SYNC_BOOKMARK, { ids });
      return data.syncBookmark;
    });
  }

  //* SEO related (GraphQL proxied version)
  static async getRankMathHeadGraphQL(url: string, signal?: AbortSignal): Promise<string> {
    const key = APIService.cacheKey('getRankMathHeadGraphQL', url);
    return APIService.cached(key, async () => {
      const data = await APIService.runQuery(GET_RANK_MATH_HEAD, { url }, mergedFetchOptionsContext(signal));
      return data.rankMathHead;
    });
  }
}