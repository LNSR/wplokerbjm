import { persistedExchange } from "@urql/exchange-persisted";
import { createClient, fetchExchange } from "@urql/core";
import type {
  AnyVariables,
  Client,
  ClientOptions,
  DocumentInput,
} from "@urql/core";
import typia from "typia";
import { getCmsOrigin } from "@/utils/environment";
import type { WPLokerBJMThemedData } from "@/types";

abstract class URQLBaseManager {
  private readonly clients = new Map<string, Client>();

  #nonce: WPLokerBJMThemedData["wpRestNonce"];
  #fetchFn?: typeof fetch;

  public setFetchFn(fetchFn: typeof fetch): void {
    this.#fetchFn = fetchFn;
  }

  public setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]): void {
    if (this.#nonce === nonce) return;
    this.#nonce = nonce;
  }

  public get getNonce(): WPLokerBJMThemedData["wpRestNonce"] {
    return this.#nonce;
  }

  protected preferHTTPMethod(
    httpMethod?: ClientOptions["preferGetMethod"],
  ): ClientOptions["preferGetMethod"] {
    return typia.assertEquals<ClientOptions["preferGetMethod"]>(
      httpMethod ?? "within-url-limit",
    );
  }

  protected getBaseFetchOptions(): RequestInit {
    return {
      credentials: "include",
      mode: "cors",
      headers: {
        ...(this.#nonce ? { "X-WP-Nonce": this.#nonce } : {}),
      },
    };
  }

  protected abstract shouldCacheClient(): boolean;

  protected getClientCacheKey(
    preferHTTPMethodOption?: ClientOptions["preferGetMethod"],
  ): string {
    return String(preferHTTPMethodOption ?? "default");
  }

  protected getFetchOptions(): RequestInit {
    return this.getBaseFetchOptions();
  }

  protected getClient(
    preferHTTPMethodOption?: ClientOptions["preferGetMethod"],
  ): Client {
    const baseOptions = this.getClientOptions(preferHTTPMethodOption);
    // Capture the fetch at call time to avoid race conditions on the singleton
    const activeFetch = this.#fetchFn ?? fetch;
    const wrappedFetch: typeof fetch = async (
      input: RequestInfo | URL,
      init?: RequestInit,
    ) => {
      const response = await activeFetch(input, init);
      const nonce = response.headers.get("X-WP-Nonce");
      nonce && this.setNonce(nonce);
      return response;
    };

    if (!this.shouldCacheClient()) {
      return createClient({ ...baseOptions, fetch: wrappedFetch });
    }

    const key = this.getClientCacheKey(preferHTTPMethodOption);
    const cached = this.clients.get(key);
    if (cached) return cached;

    const client = createClient({ ...baseOptions, fetch: wrappedFetch });
    this.clients.set(key, client);
    return client;
  }

  protected getClientOptions(
    preferHTTPMethodOption?: ClientOptions["preferGetMethod"],
  ): ClientOptions {
    const preferGetMethod = this.preferHTTPMethod(preferHTTPMethodOption);

    return {
      url: `${getCmsOrigin()}/graphql`,
      exchanges: [
        persistedExchange({
          preferGetForPersistedQueries: preferGetMethod,
          enforcePersistedQueries: false,
          enableForMutation: true,
          enableForSubscriptions: true,
        }),
        fetchExchange,
      ],
      preferGetMethod,
      fetchOptions: () => this.getFetchOptions(),
    };
  }

  protected headersToObject(headers?: HeadersInit): Record<string, string> {
    if (!headers) return {};
    return Object.fromEntries(new Headers(headers).entries());
  }

  public async runQuery<T, V extends AnyVariables = AnyVariables>(
    params: {
      query: DocumentInput<T, V>;
      variables: V;
      context?: any;
      httpMethodPref?: ClientOptions["preferGetMethod"];
    },
  ): Promise<T> {
    const { query, variables, context, httpMethodPref } = params;
    const graphqlClient = this.getClient(httpMethodPref);
    const result = await graphqlClient
      .query<T, V>(query, variables, context)
      .toPromise();

    if (result.error) throw result.error;
    return result.data as T;
  }

  public async runMutation<T, V extends AnyVariables = AnyVariables>(
    params: {
      mutation: DocumentInput<T, V>;
      variables: V;
      context?: any;
    },
  ): Promise<T> {
    const { mutation, variables, context } = params;
    const graphqlClient = this.getClient();
    const result = await graphqlClient
      .mutation<T, V>(mutation, variables, context)
      .toPromise();

    if (result.error) throw result.error;
    return result.data as T;
  }

  public mergedFetchOptionsContext(signal?: AbortSignal): {
    fetchOptions: (
      clientFetchOptions?: RequestInit | (() => RequestInit) | undefined,
    ) => RequestInit;
  } {
    return {
      fetchOptions: (
        clientFetchOptions?: RequestInit | (() => RequestInit),
      ) => {
        const defaultOptions = this.getFetchOptions();
        const resolvedClientOptions =
          typeof clientFetchOptions === "function"
            ? clientFetchOptions()
            : clientFetchOptions;

        const base = {
          ...defaultOptions,
          ...resolvedClientOptions,
        };

        return {
          ...base,
          ...(signal ? { signal } : {}),
          headers: {
            ...this.headersToObject(defaultOptions.headers),
            ...this.headersToObject(resolvedClientOptions?.headers),
          },
        };
      },
    };
  }
}

export class URQLServerManager extends URQLBaseManager {
  // protected override getClientOptions(preferHTTPMethodOption?: ClientOptions["preferGetMethod"]): ClientOptions {
  //     const baseOptions = super.getClientOptions(preferHTTPMethodOption);
  //     return {
  //         ...baseOptions,
  //         exchanges: [
  //             fetchExchange,
  //         ],
  //     };
  // }

  protected shouldCacheClient(): boolean {
    return false;
  }
}

export class URQLBrowserManager extends URQLBaseManager {
  protected shouldCacheClient(): boolean {
    return true;
  }
}
