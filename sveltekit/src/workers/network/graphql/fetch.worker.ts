/// <reference lib="webworker" />
import "../../worker-env";
import { BrowserFetch } from "@/services/graphql/fetch";
import { URQLBrowserManager } from "@/services/graphql/config/urql";
import { expose } from "comlink";

const browserFetchWorker = new BrowserFetch(new URQLBrowserManager());
expose(browserFetchWorker);
