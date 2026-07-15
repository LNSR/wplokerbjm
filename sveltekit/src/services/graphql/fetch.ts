import typia from "typia";
import type {
    JobGridFilters,
    CarouselProps,
    JobGridProps,
    SearchFilters,
    LoadMoreFilters,
    LoadMoreResponse,
    JobSchemaResponse,
    JobDetailResponse,
    WPLokerBJMThemedData,
    SearchResponse,
    TaxonomyTermsResponse,
    CardJob,
} from "@/types";
import { GET_CAROUSEL, GET_JOB_GRID } from "@/services/graphql/query/shared/job";
import type { DocumentInput } from "urql";
import { GET_AUTO_SUGGESTIONS, GET_LOAD_MORE, GET_SEARCH_JOBS, SYNC_BOOKMARK } from "@/services/graphql/query/browser/job";
import { GET_THEME_NONCE } from "@/services/graphql/query/browser/theme";
import { GET_JWT } from "@/services/graphql/query/browser/auth";
import { GET_LOKASI_TERMS, GET_GENDER_TERMS, GET_PENDIDIKAN_TERMS } from "@/services/graphql/query/browser/taxonomy";
import type { URQLBrowserManager } from "@/services/graphql/config/urql";
import { APIServiceHelper } from "@/utils/apiservice-helper";
import { GET_JOB_DETAIL, GET_JOB_SCHEMA, GET_RANK_MATH_HEAD } from "@/services/graphql/query/server/job";
import { GET_THEME_DATA } from "@/services/graphql/query/server/theme";
import { URQLServerManager } from "@/services/graphql/config/urql";
type BookmarkResponse = CardJob[];
export class SharedFetch {

    protected URQLManager: URQLBrowserManager | URQLServerManager;

    constructor(env: URQLBrowserManager | URQLServerManager) {
        this.URQLManager = env;
    }

    public setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]): void {
        this.URQLManager.setNonce(nonce);
    }

    //* Jobs related
    public async fetchCarouselGraphQL(fetchFn?: typeof fetch): Promise<CarouselProps> {
        const data = await this.URQLManager!.runQuery(GET_CAROUSEL, {}, undefined, undefined, fetchFn);
        const carousel = data.carousel;
        if (carousel?.jobs && Array.isArray(carousel.jobs)) {
            carousel.jobs = carousel.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }
        return typia.assertEquals<CarouselProps>(carousel);
    }
    public async fetchJobGridGraphQL(
        filters: JobGridFilters,
        fetchFn?: typeof fetch,
    ): Promise<JobGridProps> {
        const { sort, paged, context, title, total_jobs, ...filterFields } = filters;
        const data = await this.URQLManager!.runQuery(
            GET_JOB_GRID,
            { sort, paged, context, title, total_jobs, filters: filterFields },
            undefined,
            undefined,
            fetchFn,
        );
        const grid = data.jobGrid;
        if (grid?.jobs && Array.isArray(grid.jobs)) {
            grid.jobs = grid.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }

        return typia.assertEquals<JobGridProps>(grid);
    }
    //  async fetchAllTermsGraphQL(): Promise<TaxonomyTermsResponse> {
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

export class BrowserFetch extends SharedFetch {

    constructor(URQLConfig: URQLBrowserManager) {
        super(URQLConfig);
    }

    public async getJWTGraphQL(
        options: {
            username?: string;
            password?: string;
            token?: string;
        },
        fetchFn?: typeof fetch,
    ): Promise<string | null> {
        const assert = typia.createAssertEquals<typeof options>();
        const validated = assert(options);
        const data = await this.URQLManager.runMutation(
            GET_JWT,
            {
                username: validated.username,
                password: validated.password,
                token: validated.token,
            },
            await this.URQLManager.mergedFetchOptionsContext(),
            fetchFn,
        );
        return typia.assertEquals<string | null>(data.jwt);
    }

    //* Jobs related (GraphQL versions)
    public async getAutoSuggestionsGraphQL(
        query: string,
        fetchFn?: typeof fetch,
    ): Promise<string[]> {
        const data = await this.URQLManager.runQuery(
            GET_AUTO_SUGGESTIONS,
            { query },
            undefined,
            undefined,
            fetchFn,
        );
        return (data.autoSuggestions ?? []).filter((s): s is string => s !== null);
    }
    public async fetchTaxonomyTermsByTypeGraphQL(
        type: keyof TaxonomyTermsResponse,
        fetchFn?: typeof fetch,
    ): Promise<TaxonomyTermsResponse[typeof type]> {
        const queryMap: Record<keyof TaxonomyTermsResponse, DocumentInput> = {
            lokasiTerms: GET_LOKASI_TERMS,
            genderTerms: GET_GENDER_TERMS,
            pendidikanTerms: GET_PENDIDIKAN_TERMS,
        };

        const query = queryMap[type];
        if (!query) {
            throw new Error(`Unsupported taxonomy type: ${type}`);
        }

        const data = await this.URQLManager.runQuery(query, {}, undefined, undefined, fetchFn);
        const terms = data[type];
        return typia.assertEquals<TaxonomyTermsResponse[typeof type]>(APIServiceHelper.parseGQLJSON(terms));
    }
    //* Jobs related
    public async searchJobsGraphQL(
        filters: SearchFilters,
        fetchFn?: typeof fetch,
    ): Promise<SearchResponse> {
        const { context, ...filterFields } = filters;
        const data = await this.URQLManager.runQuery(
            GET_SEARCH_JOBS,
            { context: context ?? 'search', filters: filterFields },
            undefined,
            undefined,
            fetchFn,
        );
        const resp = data.searchJobs;
        if (resp?.jobs && Array.isArray(resp.jobs)) {
            resp.jobs = resp.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }
        return typia.assertEquals<SearchResponse>(resp);
    }
    public async loadMoreJobsGraphQL(
        filters: LoadMoreFilters,
        fetchFn?: typeof fetch,
    ): Promise<LoadMoreResponse> {
        const { paged, context, ...filterFields } = filters;
        const data = await this.URQLManager.runQuery(
            GET_LOAD_MORE,
            { paged, context, filters: filterFields },
            undefined,
            undefined,
            fetchFn,
        );
        const result = data.loadMore;
        if (result?.jobs && Array.isArray(result.jobs)) {
            result.jobs = result.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }

        return typia.assertEquals<LoadMoreResponse>(result);
    }

    public async getThemeNonceGraphQL(): Promise<WPLokerBJMThemedData["wpRestNonce"]> {
        const data = await this.URQLManager.runQuery(GET_THEME_NONCE, {}, undefined, false);
        return typia.assertEquals<string | null>(data.themeData?.wpRestNonce);
    }

    public async syncBookmarkGraphQL(
        ids: number[]
    ): Promise<BookmarkResponse> {
        const data = await this.URQLManager.runQuery(SYNC_BOOKMARK, { ids }, undefined, undefined);
        const normalized = (data.syncBookmark)?.map((j) => APIServiceHelper.normalizeJob(j));

        return typia.assertEquals<BookmarkResponse>(normalized);
    }

}

export class ServerFetch extends SharedFetch {

    constructor(serverURQLConfig: URQLServerManager) {
        super(serverURQLConfig);
    }
    public async fetchJobDetailGraphQL(
        slug: string,
        signal?: AbortSignal,
        fetchFn?: typeof fetch,
    ): Promise<JobDetailResponse> {
        slug = slug.replace(/\/+$/g, ""); // ensure no trailing slash
        const data = await this.URQLManager.runQuery(
            GET_JOB_DETAIL,
            { slug },
            this.URQLManager.mergedFetchOptionsContext(signal),
            undefined,
            fetchFn,
        );
        const job = data.jobDetail;

        return typia.assertEquals<JobDetailResponse>(job);
    }
    //* SEO related (GraphQL proxied version)
    public async getRankMathHeadGraphQL(
        url: string,
        signal?: AbortSignal,
        fetchFn?: typeof fetch,
    ): Promise<string> {
        const data = await this.URQLManager.runQuery(
            GET_RANK_MATH_HEAD,
            { url },
            this.URQLManager.mergedFetchOptionsContext(signal),
            undefined,
            fetchFn,
        );

        return typia.assertEquals<string>(data.rankMathHead ?? "");
    }

    public async fetchJobSchemasGraphQL(
        idsOrSlug?: number[] | string,
        signal?: AbortSignal,
        type?: JobSchemaResponse["type"],
        fetchFn?: typeof fetch,
    ): Promise<JobSchemaResponse["schemas"]> {

        const variables: { slug?: string; ids?: number[]; type?: string } = {};

        if (typia.is<string>(idsOrSlug)) {
            variables.slug = idsOrSlug;
        } else if (typia.is<number[]>(idsOrSlug)) {
            variables.ids = idsOrSlug;
        }

        if (type) variables.type = typia.assertEquals<JobSchemaResponse["type"]>(type);

        const data = await this.URQLManager.runQuery(
            GET_JOB_SCHEMA,
            variables,
            this.URQLManager.mergedFetchOptionsContext(signal),
            undefined,
            fetchFn,
        );

        const schemas = data.jobSchema?.schemas;

        return typia.assertEquals<JobSchemaResponse["schemas"]>(schemas ?? []);
    }

    //* Theme data related
    public async getThemeDataGraphQL(
        signal?: AbortSignal,
        fetchFn?: typeof fetch,
    ): Promise<WPLokerBJMThemedData> {
        const data = await this.URQLManager.runQuery(
            GET_THEME_DATA,
            {},
            this.URQLManager.mergedFetchOptionsContext(signal),
            undefined,
            fetchFn,
        );
        return typia.assertEquals<WPLokerBJMThemedData>(data.themeData);
    }
};

