import type { CardJob } from "@/types";
export interface SyncResult { success: boolean; type: "partial" | "full" };
export interface SyncToServer
{
    syncToServer(idsToSync?: CardJob[ 'id' ][]): Promise<void | SyncResult>;
}