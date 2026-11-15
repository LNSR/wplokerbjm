/// <reference lib="webworker" />
import "../../worker-env";
import { BrowserFetch } from "#src/services/graphql/fetch.js";
import { URQLBrowserManager } from "#src/services/graphql/config/urql.js";
import { expose } from "comlink";

const browserFetchWorker = new BrowserFetch(new URQLBrowserManager());
expose(browserFetchWorker);
