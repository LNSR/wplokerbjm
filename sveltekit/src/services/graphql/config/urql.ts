import { persistedExchange } from "@urql/exchange-persisted";
import { createClient, fetchExchange } from "@urql/core";
import type { AnyVariables, Client, ClientOptions, DocumentInput } from "@urql/core";
import typia from "typia";
import { getCmsOrigin } from "@/utils/environment";
import type { WPLokerBJMThemedData } from "@/types";

abstract class URQLBaseManager {
    private readonly clients = new Map<string, Client>();

    protected nonce: WPLokerBJMThemedData["wpRestNonce"];

    public setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]): void {
        this.nonce = nonce;
    }

    protected preferHTTPMethod(
        httpMethod?: ClientOptions["preferGetMethod"],
    ): ClientOptions["preferGetMethod"] {
        return typia.assertEquals<ClientOptions["preferGetMethod"]>(httpMethod ?? "within-url-limit");
    }

    protected getBaseFetchOptions(): RequestInit {
        return {
            credentials: "include",
            mode: "cors",
            headers: {
                ...(this.nonce
                    ? { "X-WP-Nonce": this.nonce }
                    : {}),
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
        fetchFn?: typeof fetch,
    ): Client {
        const baseOptions = this.getClientOptions(preferHTTPMethodOption);

        if (fetchFn) {
            return createClient({ ...baseOptions, fetch: fetchFn });
        }

        if (!this.shouldCacheClient()) {
            return createClient({ ...baseOptions });
        }

        const key = this.getClientCacheKey(preferHTTPMethodOption);
        const cached = this.clients.get(key);
        if (cached) return cached;

        const client = createClient({ ...baseOptions });
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
        query: DocumentInput<T, V>,
        variables: V,
        context?: any,
        httpMethodPref?: ClientOptions["preferGetMethod"],
        fetchFn?: typeof fetch,
    ): Promise<T> {
        const graphqlClient = this.getClient(httpMethodPref, fetchFn);
        const result = await graphqlClient
            .query<T, V>(query, variables, context)
            .toPromise();

        if (result.error) throw result.error;
        return result.data as T;
    }

    public async runMutation<T, V extends AnyVariables = AnyVariables>(
        mutation: DocumentInput<T, V>,
        variables: V,
        context?: any,
        fetchFn?: typeof fetch,
    ): Promise<T> {
        const graphqlClient = this.getClient(undefined, fetchFn);
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
            fetchOptions: (clientFetchOptions?: RequestInit | (() => RequestInit)) => {
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
