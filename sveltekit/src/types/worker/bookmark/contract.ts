import type { CardJob } from "#src/types/index.js";
export interface SyncResult { success: boolean; type: "partial" | "full" };
export interface SyncToServer
{
    syncToServer(idsToSync?: CardJob[ 'id' ][]): Promise<void | SyncResult>;
}