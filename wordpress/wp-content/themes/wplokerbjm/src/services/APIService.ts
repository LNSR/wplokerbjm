import type {
  SearchFilters,
  LoadMoreFilters,
  JobGridFilters,
  CarouselProps,
  LoadMoreResponse,
  JobGridProps,
  CardJob,
  JobDetailResponse,
  WPLokerBJMThemedData,
  SearchResponse,
  TaxonomyApiInterface,
} from "@/types";
import { LRUCache } from "lru-cache";
import { createClient, fetchExchange, type ClientOptions } from "urql";
import { persistedExchange } from "@urql/exchange-persisted";
import {
  GET_ALL_TERMS,
  GET_JWT,
  GET_LOKASI_TERMS,
  GET_GENDER_TERMS,
  GET_PENDIDIKAN_TERMS,
  GET_AUTO_SUGGESTIONS,
  GET_CAROUSEL,
  GET_LOAD_MORE,
  GET_JOB_GRID,
  GET_JOB_DETAIL,
  GET_JOB_SCHEMA,
  GET_THEME_DATA,
  GET_SEARCH_JOBS,
  SYNC_BOOKMARK,
  GET_RANK_MATH_HEAD,
  GET_THEME_NONCE,
} from "@/services/api/graphql/query";
import { getCmsOrigin } from "@/utils";
import { nonceManager } from "$lib/utils/Nonce.svelte";

type BookmarkedJobsResponse = CardJob[];
type JobSchemaResponse = Record<string, any>[];
type AutoSuggestResponse = string[];

type JWTResponse = string | null;

const cache = new LRUCache<string, any>({
  max: 5, // Maximum number of items
  ttl: 10000, // 10 seconds in milliseconds
  ttlAutopurge: true,
}); // prevent excessive API calls
class URQLClientManager {
  private static clients = new Map<string, ReturnType<typeof createClient>>();

  private static preferHTTPMethod(
    httpMethod?: ClientOptions["preferGetMethod"],
  ): ClientOptions["preferGetMethod"] {
    return httpMethod ?? "within-url-limit";
  }

  private static urqlOptions(
    preferHTTPMethodOption?: ClientOptions["preferGetMethod"],
  ): ClientOptions {
    return {
      url: `${getCmsOrigin()}/graphql`,
      exchanges: [
        persistedExchange({
          preferGetForPersistedQueries: this.preferHTTPMethod(
            preferHTTPMethodOption,
          ),
          enforcePersistedQueries: false,
        }),
        fetchExchange,
      ],
      preferGetMethod: this.preferHTTPMethod(preferHTTPMethodOption),
      fetchOptions: () => ({
        credentials: "include",
        mode: "cors",
        headers: {
          ...(nonceManager.getNonce
            ? { "X-WP-Nonce": nonceManager.getNonce }
            : {}),
        },
      }),
    };
  }

  static getClient(
    preferHTTPMethodOption?: ClientOptions["preferGetMethod"],
    fetchFn?: typeof fetch,
  ) {
    // If a custom fetch implementation is provided (e.g. SvelteKit's event.fetch),
    // create a non-cached client bound to that fetch so cookies and request
    // context are forwarded correctly for SSR. Otherwise reuse cached clients.
    if (fetchFn) {
      return createClient({ ...this.urqlOptions(preferHTTPMethodOption), fetch: fetchFn });
    }

    const key = String(preferHTTPMethodOption ?? "default");
    if (this.clients.has(key)) return this.clients.get(key)!;
    const client = createClient(this.urqlOptions(preferHTTPMethodOption));
    this.clients.set(key, client);
    return client;
  }

  static async runQuery<T = any>(
    query: any,
    variables?: any,
    context?: any,
    httpMethodPref?: ClientOptions["preferGetMethod"],
    fetchFn?: typeof fetch,
  ): Promise<T> {
    const graphqlClient = this.getClient(httpMethodPref, fetchFn);
    const result = await graphqlClient
      .query(query, variables, context)
      .toPromise();
    if (result.error) throw result.error;
    return result.data;
  }

  static async runMutation<T = any>(
    mutation: any,
    variables?: any,
    context?: any,
    fetchFn?: typeof fetch,
  ): Promise<T> {
    const graphqlClient = this.getClient(undefined, fetchFn);
    const result = await graphqlClient
      .mutation(mutation, variables, context)
      .toPromise();
    if (result.error) throw result.error;
    return result.data;
  }

  static mergedFetchOptionsContext(signal?: AbortSignal, serverToken?: string) {
    return {
      fetchOptions: (clientFetchOptions?: any) => {
        const defaultGetter = this.urqlOptions?.().fetchOptions;
        const baseDefaults =
          typeof defaultGetter === "function"
            ? defaultGetter()
            : undefined;
        const base =
          typeof clientFetchOptions === "function"
            ? clientFetchOptions()
            : clientFetchOptions || baseDefaults || {};

        const baseHeaders = base.headers instanceof Headers ? Object.fromEntries(base.headers.entries()) : base.headers || {};
        const authHeader = serverToken
          ? { Authorization: `Bearer ${serverToken}` }
          : {};
        return {
          ...base,
          ...(signal ? { signal } : {}),
          headers: {
            ...baseHeaders,
            ...authHeader,
          },
        };
      },
    };
  }
}

export class APIService {
  private static cacheKey(prefix: string, payload?: any): string {
    if (payload === undefined) return prefix;
    if (
      typeof payload === "string" ||
      typeof payload === "number" ||
      typeof payload === "boolean"
    ) {
      return `${prefix}:${String(payload)}`;
    }
    return `${prefix}:${APIService._stableStringify(payload)}`;
  }

  private static _stableStringify(value: any): string {
    if (value === null) return "null";
    if (typeof value !== "object") return JSON.stringify(value);
    if (Array.isArray(value))
      return `[${value.map(APIService._stableStringify).join(",")}]`;
    const keys = Object.keys(value).sort();
    return `{${keys.map((k) => JSON.stringify(k) + ":" + APIService._stableStringify(value[k])).join(",")}}`;
  }

  private static normalizeJob(job: any): any {
    if (!job || typeof job !== "object") return job;
    if (typeof job.permalink === "string") {
      // drop trailing slash first then strip origin/domain
      let p = job.permalink.replace(/\/+$/g, "");
      try {
        const u = new URL(p);
        p = u.pathname;
      } catch {
        // not a full URL, leave as-is
      }
      job.permalink = p;
    }
    if (typeof job.slug === "string") {
      job.slug = job.slug.replace(/\/+$/g, "");
    }
    return job;
  }

  private static async cached<T>(
    key: string,
    fn: () => Promise<T>,
  ): Promise<T> {
    const hit = cache.get(key);
    if (hit !== undefined) return hit;
    const value = await fn();
    cache.set(key, value);
    return value;
  }

  //* Jobs related (GraphQL versions)
  static async getAutoSuggestionsGraphQL(
    query: string,
    fetchFn?: typeof fetch,
  ): Promise<AutoSuggestResponse> {
    const key = APIService.cacheKey("getAutoSuggestionsGraphQL", query);
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(
        GET_AUTO_SUGGESTIONS,
        { query },
        undefined,
        undefined,
        fetchFn,
      );
      return data.autoSuggestions;
    });
  }

  //* Taxonomy related (GraphQL versions)
  static async fetchAllTermsGraphQL(): Promise<
    TaxonomyApiInterface["getAllTerms"]
  > {
    const key = APIService.cacheKey("fetchAllTermsGraphQL");
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(GET_ALL_TERMS, {}, undefined, undefined);
      return JSON.parse(data.taxonomyTerms);
    });
  }

  static async fetchLokasiTermsGraphQL(): Promise<
    ReturnType<TaxonomyApiInterface["getTermsByType"]>
  > {
    const key = APIService.cacheKey("fetchLokasiTermsGraphQL");
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(GET_LOKASI_TERMS, {}, undefined, undefined);
      return JSON.parse(data.lokasiTerms);
    });
  }

  static async fetchGenderTermsGraphQL(): Promise<
    ReturnType<TaxonomyApiInterface["getTermsByType"]>
  > {
    const key = APIService.cacheKey("fetchGenderTermsGraphQL");
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(GET_GENDER_TERMS, {}, undefined, undefined);
      return JSON.parse(data.genderTerms);
    });
  }

  static async fetchPendidikanTermsGraphQL(): Promise<
    ReturnType<TaxonomyApiInterface["getTermsByType"]>
  > {
    const key = APIService.cacheKey("fetchPendidikanTermsGraphQL");
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(GET_PENDIDIKAN_TERMS, {}, undefined, undefined);
      return JSON.parse(data.pendidikanTerms);
    });
  }

  //* Jobs related
  static async fetchCarouselGraphQL(fetchFn?: typeof fetch): Promise<CarouselProps> {
    const key = APIService.cacheKey("fetchCarouselGraphQL");
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(GET_CAROUSEL, {}, undefined, undefined, fetchFn);
      const carousel: CarouselProps = data.carousel;
      if (carousel?.jobs && Array.isArray(carousel.jobs)) {
        carousel.jobs = carousel.jobs.map(APIService.normalizeJob);
      }
      return carousel;
    });
  }

  static async loadMoreJobsGraphQL(
    filters: LoadMoreFilters,
    fetchFn?: typeof fetch,
  ): Promise<LoadMoreResponse> {
    const key = APIService.cacheKey("loadMoreJobsGraphQL", filters);
    return APIService.cached(key, async () => {
      const { paged, context, ...filterFields } = filters;
      const data = await URQLClientManager.runQuery(
        GET_LOAD_MORE,
        { paged, context, filters: filterFields },
        undefined,
        undefined,
        fetchFn,
      );
      const result: LoadMoreResponse = data.loadMore;
      if (result?.jobs && Array.isArray(result.jobs)) {
        result.jobs = result.jobs.map(APIService.normalizeJob);
      }
      return result;
    });
  }

  static async fetchJobGridGraphQL(
    filters: JobGridFilters,
    fetchFn?: typeof fetch,
  ): Promise<JobGridProps> {
    const key = APIService.cacheKey("fetchJobGridGraphQL", filters);
    return APIService.cached(key, async () => {
      const { paged, context, title, total_jobs, ...filterFields } = filters;
      const data = await URQLClientManager.runQuery(
        GET_JOB_GRID,
        { paged, context, title, total_jobs, filters: filterFields },
        undefined,
        undefined,
        fetchFn,
      );
      const grid: JobGridProps = data.jobGrid;
      if (grid?.jobs && Array.isArray(grid.jobs)) {
        grid.jobs = grid.jobs.map(APIService.normalizeJob);
      }
      return grid;
    });
  }

  static async fetchJobDetailGraphQL(
    slug: string,
    signal?: AbortSignal,
    serverToken?: string,
    fetchFn?: typeof fetch,
  ): Promise<JobDetailResponse> {
    slug = slug.replace(/\/+$/g, ""); // ensure no trailing slash
    const key = APIService.cacheKey("fetchJobDetailGraphQL", slug);
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(
        GET_JOB_DETAIL,
        { slug },
        URQLClientManager.mergedFetchOptionsContext(signal, serverToken),
        undefined,
        fetchFn,
      );
      const job: JobDetailResponse = data.jobDetail.job;
      return APIService.normalizeJob(job);
    });
  }

  static async fetchJobSchemasGraphQL(
    ids: number[],
    signal?: AbortSignal,
    type?: string,
    serverToken?: string,
    fetchFn?: typeof fetch,
  ): Promise<JobSchemaResponse> {
    const key = APIService.cacheKey("fetchJobSchemasGraphQL", { ids, type });
    return APIService.cached(key, async () => {
      const variables: any = { ids };
      if (type) variables.type = type;
      const data = await URQLClientManager.runQuery(
        GET_JOB_SCHEMA,
        variables,
        URQLClientManager.mergedFetchOptionsContext(signal, serverToken),
        undefined,
        fetchFn,
      );
      return data.jobSchema.schemas.map((s: string) => JSON.parse(s));
    });
  }

  //* Theme data related
  static async getThemeDataGraphQL(
    signal?: AbortSignal,
    serverToken?: string,
    fetchFn?: typeof fetch,
  ): Promise<WPLokerBJMThemedData> {
    // When a serverToken is provided the cache key must be token-aware so that
    // authenticated and unauthenticated responses do not collide.
    const key = APIService.cacheKey(
      "getThemeDataGraphQL",
      serverToken ? `auth` : undefined,
    );
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(
        GET_THEME_DATA,
        {},
        URQLClientManager.mergedFetchOptionsContext(signal, serverToken),
        undefined,
        fetchFn,
      );
      return data.themeData.data;
    });
  }

  static async getThemeNonceGraphQL(): Promise<string | null> {
    const key = APIService.cacheKey("getThemeNonceGraphQL");
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(GET_THEME_NONCE, {}, undefined, undefined);
      return data.themeData.data?.wpRestNonce ?? null;
    });
  }

  //* Jobs related
  static async searchJobsGraphQL(
    filters: SearchFilters,
    fetchFn?: typeof fetch,
  ): Promise<SearchResponse> {
    const key = APIService.cacheKey("searchJobsGraphQL", filters);
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(
        GET_SEARCH_JOBS,
        { filters },
        undefined,
        undefined,
        fetchFn,
      );
      const resp: SearchResponse = data.searchJobs;
      if (resp?.jobs && Array.isArray(resp.jobs)) {
        resp.jobs = resp.jobs.map(APIService.normalizeJob);
      }
      return resp;
    });
  }

  static async syncBookmarkGraphQL(
    ids: number[],
    fetchFn?: typeof fetch,
  ): Promise<BookmarkedJobsResponse> {
    const key = APIService.cacheKey("syncBookmarkGraphQL", ids);
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(SYNC_BOOKMARK, { ids }, undefined, undefined, fetchFn);
      return data.syncBookmark;
    });
  }

  //* SEO related (GraphQL proxied version)
  static async getRankMathHeadGraphQL(
    url: string,
    signal?: AbortSignal,
    fetchFn?: typeof fetch,
  ): Promise<string> {
    const key = APIService.cacheKey("getRankMathHeadGraphQL", url);
    return APIService.cached(key, async () => {
      const data = await URQLClientManager.runQuery(
        GET_RANK_MATH_HEAD,
        { url },
        URQLClientManager.mergedFetchOptionsContext(signal),
        "force",
        fetchFn,
      );
      return data.rankMathHead;
    });
  }

  static async getJWTGraphQL(
    options: {
      username?: string;
      password?: string;
      token?: string;
    },
    fetchFn?: typeof fetch,
  ): Promise<JWTResponse> {
    const data = await URQLClientManager.runMutation(
      GET_JWT,
      {
        username: options.username,
        password: options.password,
        token: options.token,
      },
      URQLClientManager.mergedFetchOptionsContext(),
      fetchFn,
    );
    return data.jwt ?? null;
  }
}
