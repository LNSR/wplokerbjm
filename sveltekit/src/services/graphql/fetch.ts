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
import { GET_JOB_DETAIL, GET_JOB_DETAIL_PREVIEW, GET_JOB_SCHEMA, GET_RANK_MATH_HEAD } from "@/services/graphql/query/server/job";
import { GET_THEME_DATA } from "@/services/graphql/query/server/theme";
import { URQLServerManager } from "@/services/graphql/config/urql";
type BookmarkResponse = CardJob[];
class SharedFetch {

    protected URQLManager: URQLBrowserManager | URQLServerManager;

    constructor(env: URQLBrowserManager | URQLServerManager) {
        this.URQLManager = env;
    }

    public setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]): void {
        this.URQLManager.setNonce(nonce);
    }

    /**
     * @param fetchFn SvelteKit its SSR fetch
     */
    public setFetchFn(fetchFn: typeof fetch): void {
        this.URQLManager.setFetchFn(fetchFn);
    }

    public get getNonce(): WPLokerBJMThemedData["wpRestNonce"] {
        return this.URQLManager.getNonce;
    }

    //* Jobs related
    public async fetchCarouselGraphQL(): Promise<CarouselProps> {
        const data = await this.URQLManager!.runQuery({ query: GET_CAROUSEL, variables: {} });
        const carousel = data.carousel;
        if (carousel?.jobs && Array.isArray(carousel.jobs)) {
            carousel.jobs = carousel.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }
        return typia.assertEquals<CarouselProps>(carousel);
    }
    public async fetchJobGridGraphQL(
        filters: JobGridFilters,
    ): Promise<JobGridProps> {
        const { sort, paged, context, title, total_jobs, ...filterFields } = filters;
        const data = await this.URQLManager!.runQuery({
            query: GET_JOB_GRID,
            variables: { sort, paged, context, title, total_jobs, filters: filterFields },
        });
        const grid = data.jobGrid;
        if (grid?.jobs && Array.isArray(grid.jobs)) {
            grid.jobs = grid.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }

        return typia.assertEquals<JobGridProps>(grid);
    }
    //  async fetchAllTermsGraphQL(): Promise<TaxonomyTermsResponse> {
    //   const data = await URQLClientManager.runQuery({ query: GET_ALL_TERMS, variables: {} });

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
    ): Promise<string | null> {
        const assert = typia.createAssertEquals<typeof options>();
        const validated = assert(options);
        const data = await this.URQLManager.runMutation({
            mutation: GET_JWT,
            variables: {
                username: validated.username,
                password: validated.password,
                token: validated.token,
            },
            context: await this.URQLManager.mergedFetchOptionsContext(),
        });
        return typia.assertEquals<string | null>(data.jwt);
    }

    //* Jobs related (GraphQL versions)
    public async getAutoSuggestionsGraphQL(
        query: string,
    ): Promise<string[]> {
        const data = await this.URQLManager.runQuery({
            query: GET_AUTO_SUGGESTIONS,
            variables: { query },
        });
        return (data.autoSuggestions ?? []).filter((s): s is string => s !== null);
    }
    public async fetchTaxonomyTermsByTypeGraphQL(
        type: keyof TaxonomyTermsResponse,
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

        const data = await this.URQLManager.runQuery({ query, variables: {} });
        const terms = data[type];
        return typia.assertEquals<TaxonomyTermsResponse[typeof type]>(APIServiceHelper.parseGQLJSON(terms));
    }
    //* Jobs related
    public async searchJobsGraphQL(
        filters: SearchFilters,
    ): Promise<SearchResponse> {
        const { context, ...filterFields } = filters;
        const data = await this.URQLManager.runQuery({
            query: GET_SEARCH_JOBS,
            variables: { context: context ?? 'search', filters: filterFields },
        });
        const resp = data.searchJobs;
        if (resp?.jobs && Array.isArray(resp.jobs)) {
            resp.jobs = resp.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }
        return typia.assertEquals<SearchResponse>(resp);
    }
    public async loadMoreJobsGraphQL(
        filters: LoadMoreFilters,
    ): Promise<LoadMoreResponse> {
        const { paged, context, ...filterFields } = filters;
        const data = await this.URQLManager.runQuery({
            query: GET_LOAD_MORE,
            variables: { paged, context, filters: filterFields },
        });
        const result = data.loadMore;
        if (result?.jobs && Array.isArray(result.jobs)) {
            result.jobs = result.jobs.map((j) => APIServiceHelper.normalizeJob(j));
        }

        return typia.assertEquals<LoadMoreResponse>(result);
    }

    public async getThemeNonceGraphQL(): Promise<WPLokerBJMThemedData["wpRestNonce"]> {
        const data = await this.URQLManager.runQuery({ query: GET_THEME_NONCE, variables: {}, httpMethodPref: false });
        return typia.assertEquals<string | null>(data.themeData?.wpRestNonce);
    }

    public async syncBookmarkGraphQL(
        ids: number[]
    ): Promise<BookmarkResponse> {
        const data = await this.URQLManager.runQuery({ query: SYNC_BOOKMARK, variables: { ids } });
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
    ): Promise<JobDetailResponse> {
        slug = slug.replace(/\/+$/g, ""); // ensure no trailing slash
        const data = await this.URQLManager.runQuery({
            query: GET_JOB_DETAIL,
            variables: { slug },
            context: this.URQLManager.mergedFetchOptionsContext(signal),
        });
        const job = data.jobDetail;

        return typia.assertEquals<JobDetailResponse>(job);
    }
    public async fetchJobDetailPreviewGraphQL(
        id: number,
        signal?: AbortSignal,
    ): Promise<JobDetailResponse> {
        const data = await this.URQLManager.runQuery({
            query: GET_JOB_DETAIL_PREVIEW,
            variables: { id, preview: true },
            context: this.URQLManager.mergedFetchOptionsContext(signal),
        });
        const job = data.jobDetail;

        return typia.assertEquals<JobDetailResponse>(job);
    }
    //* SEO related (GraphQL proxied version)
    public async getRankMathHeadGraphQL(
        url: string,
        signal?: AbortSignal,
    ): Promise<string> {
        const data = await this.URQLManager.runQuery({
            query: GET_RANK_MATH_HEAD,
            variables: { url },
            context: this.URQLManager.mergedFetchOptionsContext(signal),
        });

        return typia.assertEquals<string>(data.rankMathHead ?? "");
    }

    public async fetchJobSchemasGraphQL(
        idsOrSlug?: number[] | string,
        type?: JobSchemaResponse["type"],
        signal?: AbortSignal,
    ): Promise<JobSchemaResponse["schemas"]> {

        const variables: { slug?: string; ids?: number[]; type?: string } = {};

        if (typia.is<string>(idsOrSlug)) {
            variables.slug = idsOrSlug;
        } else if (typia.is<number[]>(idsOrSlug)) {
            variables.ids = idsOrSlug;
        }

        if (type) variables.type = typia.assertEquals<JobSchemaResponse["type"]>(type);

        const data = await this.URQLManager.runQuery({
            query: GET_JOB_SCHEMA,
            variables,
            context: this.URQLManager.mergedFetchOptionsContext(signal),
        });

        const schemas = data.jobSchema?.schemas;

        return typia.assertEquals<JobSchemaResponse["schemas"]>(schemas ?? []);
    }

    //* Theme data related
    public async getThemeDataGraphQL(
        signal?: AbortSignal,
    ): Promise<WPLokerBJMThemedData> {
        const data = await this.URQLManager.runQuery({
            query: GET_THEME_DATA,
            variables: {},
            context: this.URQLManager.mergedFetchOptionsContext(signal),
        });
        return typia.assertEquals<WPLokerBJMThemedData>(data.themeData);
    }
};

