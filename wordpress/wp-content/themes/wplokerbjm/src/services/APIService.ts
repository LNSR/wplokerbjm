import type { SearchFilters, LoadMoreFilters, JobGridFilters, CarouselProps, LoadMoreResponse, JobGridProps, JobDetailResponse, CardJob, WPLokerBJMThemedData, SearchResponse, TaxonomyApiInterface } from '@/types';
import { LRUCache } from 'lru-cache';
import { createClient, cacheExchange, fetchExchange } from 'urql';
import { GET_ALL_TERMS, GET_LOKASI_TERMS, GET_GENDER_TERMS, GET_PENDIDIKAN_TERMS, GET_AUTO_SUGGESTIONS, GET_CAROUSEL, GET_LOAD_MORE, GET_JOB_GRID, GET_JOB_DETAIL, GET_JOB_SCHEMA, GET_THEME_DATA, GET_SEARCH_JOBS, SYNC_BOOKMARK, GET_RANK_MATH_HEAD, GET_THEME_NONCE } from '@/services/api/graphql/query';
import { NonceManager } from '@/utils';

const cache = new LRUCache<string, any>({
  max: 500, // Maximum number of items
  ttl: 10000, // 10 seconds in milliseconds
}); // prevent excessive API calls

// Create urql client for GraphQL queries
const graphqlClient = createClient({
  url: '/graphql',
  exchanges: [cacheExchange, fetchExchange],
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
  //* Jobs related (GraphQL versions)
  static async getAutoSuggestionsGraphQL(query: string): Promise<AutoSuggestResponse> {
    const key = `getAutoSuggestionsGraphQL:${query}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_AUTO_SUGGESTIONS, { query }).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.autoSuggestions;
    cache.set(key, data);
    return data;
  }

  //* Taxonomy related (GraphQL versions)
  static async fetchAllTermsGraphQL(): Promise<TaxonomyApiInterface['getAllTerms']> {
    const key = 'fetchAllTermsGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_ALL_TERMS, {}).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = JSON.parse(result.data.taxonomyTerms);
    cache.set(key, data);
    return data;
  }

  static async fetchLokasiTermsGraphQL(): Promise<ReturnType<TaxonomyApiInterface['getTermsByType']>> {
    const key = 'fetchLokasiTermsGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_LOKASI_TERMS, {}).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = JSON.parse(result.data.lokasiTerms);
    cache.set(key, data);
    return data;
  }

  static async fetchGenderTermsGraphQL(): Promise<ReturnType<TaxonomyApiInterface['getTermsByType']>> {
    const key = 'fetchGenderTermsGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_GENDER_TERMS, {}).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = JSON.parse(result.data.genderTerms);
    cache.set(key, data);
    return data;
  }

  static async fetchPendidikanTermsGraphQL(): Promise<ReturnType<TaxonomyApiInterface['getTermsByType']>> {
    const key = 'fetchPendidikanTermsGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_PENDIDIKAN_TERMS, {}).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = JSON.parse(result.data.pendidikanTerms);
    cache.set(key, data);
    return data;
  }

  //* Jobs related
  static async fetchCarouselGraphQL(): Promise<CarouselProps> {
    const key = 'fetchCarouselGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_CAROUSEL, {}).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.carousel;
    cache.set(key, data);
    return data;
  }

  static async loadMoreJobsGraphQL(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    const key = `loadMoreJobsGraphQL:${JSON.stringify(filters)}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const { paged, context, ...filterFields } = filters;
    const result = await graphqlClient.query(GET_LOAD_MORE, { paged, context, filters: filterFields }).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.loadMore;
    cache.set(key, data);
    return data;
  }

  static async fetchJobGridGraphQL(filters: JobGridFilters): Promise<JobGridProps> {
    const key = `fetchJobGridGraphQL:${JSON.stringify(filters)}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const { paged, context, title, total_jobs, ...filterFields } = filters;
    const result = await graphqlClient.query(GET_JOB_GRID, { paged, context, title, total_jobs, filters: filterFields }).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.jobGrid;
    cache.set(key, data);
    return data;
  }

  static async fetchJobDetailGraphQL(slug: string, signal?: AbortSignal): Promise<JobDetailResponse> {
    const key = `fetchJobDetailGraphQL:${slug}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_JOB_DETAIL, { slug }, mergedFetchOptionsContext(signal)).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.jobDetail.job;
    cache.set(key, data);
    return data;
  }

  static async fetchJobSchemasGraphQL(ids: number[], signal?: AbortSignal, type?: string): Promise<JobSchemaResponse> {
    const typeKey = type ? String(type) : 'auto';
    const key = `fetchJobSchemasGraphQL:${ids.join(',')}:${typeKey}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const variables: any = { ids };
    if (type) variables.type = type;
    const result = await graphqlClient.query(GET_JOB_SCHEMA, variables, mergedFetchOptionsContext(signal)).toPromise();
    if (result.error) {
      throw result.error;
    }
    const schemas = result.data.jobSchema.schemas.map((s: string) => JSON.parse(s));
    cache.set(key, schemas);
    return schemas;
  }

  //* Theme data related
  static async getThemeDataGraphQL(): Promise<WPLokerBJMThemedData> {
    const key = 'getThemeDataGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_THEME_DATA, {}).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.themeData.data;
    cache.set(key, data);
    return data;
  }

  static async getThemeNonceGraphQL(signal?: AbortSignal): Promise<string | null> {
    const key = 'getThemeNonceGraphQL';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_THEME_NONCE, {}, mergedFetchOptionsContext(signal)).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.themeData.data?.wpRestNonce ?? null;
    cache.set(key, data);
    return data;
  }

  //* Jobs related
  static async searchJobsGraphQL(filters: SearchFilters): Promise<SearchResponse> {
    const key = `searchJobsGraphQL:${JSON.stringify(filters)}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_SEARCH_JOBS, { filters }).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.searchJobs;
    cache.set(key, data);
    return data;
  }

  static async syncBookmarkGraphQL(ids: number[]): Promise<BookmarkedJobsResponse> {
    const key = `syncBookmarkGraphQL:${ids.join(',')}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(SYNC_BOOKMARK, { ids }).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.syncBookmark;
    cache.set(key, data);
    return data;
  }

  //* SEO related (GraphQL proxied version)
  static async getRankMathHeadGraphQL(url: string, signal?: AbortSignal): Promise<string> {
    const key = `getRankMathHeadGraphQL:${url}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await graphqlClient.query(GET_RANK_MATH_HEAD, { url }, mergedFetchOptionsContext(signal)).toPromise();
    if (result.error) {
      throw result.error;
    }
    const data = result.data.rankMathHead;
    cache.set(key, data);
    return data;
  }
}