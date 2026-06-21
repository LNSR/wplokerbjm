import { browser } from "$app/environment";
import { URQLServerManager } from "@/services/graphql/config/urql";
import { wrap } from "comlink";
import graphFetchWorker from "@/workers/network/graphql/fetch.worker?worker";
import type { BrowserFetch as fetchWorker } from "./fetch";
import { ServerFetch } from "./fetch";

const fetchWorkerBrowser = function() {
  if (!browser) return;
  const workerInstance = wrap<fetchWorker>(new graphFetchWorker());
  return workerInstance;
}() as ReturnType<typeof wrap<fetchWorker>>;

/**
 * ? Move to dedicated *.server file, maybe not
 */
export const APIServiceServer = new ServerFetch(new URQLServerManager());

export const APIServiceBrowser = fetchWorkerBrowser;


//* IDE autocomplete for this should only show "intersection" anyway
export const APIServiceShared = browser ? APIServiceBrowser : APIServiceServer;