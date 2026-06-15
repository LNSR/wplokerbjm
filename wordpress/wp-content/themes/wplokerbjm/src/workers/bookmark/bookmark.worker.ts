/// <reference lib="WebWorker" />
import { BookmarkIDB } from "@/services/IndexedDB";
import { BookmarkTaskController } from "@/workers/bookmark/taskController";
import { expose } from "comlink";
import { BookmarkSyncQueueTask } from "./taskController";

const sharedworker = self as unknown as SharedWorkerGlobalScope;

export interface BookmarkWorkerInstance
{
    bookmarkIDB: BookmarkIDB;
    bookmarkTaskController: BookmarkTaskController;
    bookmarkSyncQueueTask: BookmarkSyncQueueTask;
}

const bookmarkIDB = new BookmarkIDB('JobBookmarks', 1, 'bookmarks');
const bookmarkTaskController = new BookmarkTaskController();
const bookmarkSyncQueueTask = new BookmarkSyncQueueTask();

sharedworker.onconnect = (event: MessageEvent) =>
{
    const port = event.ports[ 0 ];
    expose({
        bookmarkIDB,
        bookmarkTaskController,
        bookmarkSyncQueueTask,
    } satisfies BookmarkWorkerInstance, port);
};