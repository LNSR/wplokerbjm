/**
 * ETag-aware response cache for server-to-server GraphQL calls.
 *
 * Uses Cloudflare's Cache API (caches.default) in production for
 * cross-instance persistence. 
 * LRU-cache is always active as primary
 * in-memory cache (dev AND production within the same Worker lifecycle).
 */

import { dev } from '$app/environment';
import { LRUCache } from 'lru-cache';

interface CacheEntry {
    etag: string;
    data: unknown;
}

const CACHE_ORIGIN = 'https://lokerbanjarmasin.my.id';
const DEFAULT_TTL_SECONDS = 3600;

/** CF Cache API is only functional in production Workers (wrangler dev has a null stub). */
const isCFCacheAvailable = !dev && typeof caches !== 'undefined' && Boolean(caches.default);

/** LRU cache: 500 entries, auto-TTL purge — always active. */
const lru = new LRUCache<string, CacheEntry>({
    max: 500,
    ttl: DEFAULT_TTL_SECONDS * 1000,
    ttlAutopurge: true,
});

function cfCacheUrl(hash: string): string {
    return `${CACHE_ORIGIN}/graphql/${hash}`;
}

export async function getEtag(hash: string): Promise<CacheEntry | null> {
    const local = lru.get(hash);
    if (local) return local;

    if (!isCFCacheAvailable) return null;

    try {
        const response = await caches.default.match(cfCacheUrl(hash));
        if (!response) return null;

        const entry = (await response.json()) as CacheEntry;
        
        lru.set(hash, entry, { ttl: DEFAULT_TTL_SECONDS * 1000 });
        return entry;
    } catch (error) {
        console.warn(`[Cache API Get Error] Failed to read hash ${hash}:`, error);
        return null;
    }
}

export async function setEtag(
    hash: string,
    etag: string,
    data: unknown,
    ttl: number = DEFAULT_TTL_SECONDS,
): Promise<void> {
    const entry: CacheEntry = { etag, data };
    const ttlMs = ttl * 1000;

    lru.set(hash, entry, { ttl: ttlMs });

    if (!isCFCacheAvailable) return;

    try {
        const body = JSON.stringify(entry);
        const response = new Response(body, {
            headers: { 
                'Content-Type': 'application/json',
                'Cache-Control': `public, max-age=${ttl}` 
            },
        });
		//@ts-expect-error
        await caches.default.put(cfCacheUrl(hash), response);
    } catch (error) {
        console.error(`[Cache API Set Error] Failed to write hash ${hash}:`, error);
    }
}