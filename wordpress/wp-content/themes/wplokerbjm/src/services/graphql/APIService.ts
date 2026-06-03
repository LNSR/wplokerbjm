import typia from "typia";
import type {
  JobSchemaResponse,
  JobDetailResponse,
  WPLokerBJMThemedData,
} from "@/types";
import { browser } from "$app/environment";
import { GET_JOB_DETAIL, GET_JOB_SCHEMA, GET_RANK_MATH_HEAD } from "@/services/graphql/query/job"
import { GET_THEME_DATA, } from "@/services/graphql/query/theme";
import { URQLServerManager } from "@/services/graphql/config/urql";
import { wrap } from "comlink";
import graphFetchWorker from "@/workers/network/graphql/fetch.worker?worker";
import type { BrowserFetch as fetchWorker } from "./fetch";
import { SharedFetch } from "./fetch";


const fetchWorkerBrowser = function()
{
  if (!browser) return;
  const workerInstance = wrap<fetchWorker>(new graphFetchWorker());
  return workerInstance;
}() as ReturnType<typeof wrap<fetchWorker>>;

class FetchServer extends SharedFetch
{

  constructor(serverURQLConfig: URQLServerManager)
  {
    super(serverURQLConfig);
  }
  public async fetchJobDetailGraphQL(
    slug: string,
    signal?: AbortSignal,
    fetchFn?: typeof fetch,
  ): Promise<JobDetailResponse>
  {
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
  ): Promise<string>
  {
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
    type?: JobSchemaResponse[ "type" ],
    fetchFn?: typeof fetch,
  ): Promise<JobSchemaResponse[ "schemas" ]>
  {

    const variables: { slug?: string; ids?: number[]; type?: string } = {};

    if (typia.is<string>(idsOrSlug))
    {
      variables.slug = idsOrSlug;
    } else if (typia.is<number[]>(idsOrSlug))
    {
      variables.ids = idsOrSlug;
    }

    if (type) variables.type = typia.assertEquals<JobSchemaResponse[ "type" ]>(type);

    const data = await this.URQLManager.runQuery(
      GET_JOB_SCHEMA,
      variables,
      this.URQLManager.mergedFetchOptionsContext(signal),
      undefined,
      fetchFn,
    );

    const schemas = data.jobSchema?.schemas;

    return typia.assertEquals<JobSchemaResponse[ "schemas" ]>(schemas ?? []);
  }

  //* Theme data related
  public async getThemeDataGraphQL(
    signal?: AbortSignal,
    fetchFn?: typeof fetch,
  ): Promise<WPLokerBJMThemedData>
  {
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


/**
 * ? Move to dedicated *.server file, maybe not
 */
export const APIServiceServer = new FetchServer(new URQLServerManager());

export const APIServiceBrowser = fetchWorkerBrowser;


//* IDE autocomplete for this should only show "intersection" anyway
export const APIServiceShared = browser ? APIServiceBrowser : APIServiceServer;