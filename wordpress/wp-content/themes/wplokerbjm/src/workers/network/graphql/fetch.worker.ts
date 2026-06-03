/// <reference lib="webworker" />
import { BrowserFetch } from "@/services/graphql/fetch";
import { URQLBrowserManager } from "@/services/graphql/config/urql";
import { expose } from "comlink";
expose(new BrowserFetch(new URQLBrowserManager()));