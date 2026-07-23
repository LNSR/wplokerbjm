/**
 * In-memory LRU cache for theme data.
 *
 * `wpRestNonce` is excluded from the cache because it is dynamically
 * populated per-request by URQL's nonce extraction from the response
 * headers, not from the cached payload.
 */

import { LRUCache } from "lru-cache";
import type { WPLokerBJMThemedData } from "@/types";

/** Theme data without the request-scoped nonce. */

const DEFAULT_TTL_MS = 60 * 60 * 1000; // 1 hour

const lru = new LRUCache<string, WPLokerBJMThemedData>({
  max: 1,
  ttl: DEFAULT_TTL_MS,
  ttlAutopurge: true,
});

const CACHE_KEY = "theme-data";

export function getThemeCache(): WPLokerBJMThemedData | undefined {
  return lru.get(CACHE_KEY);
}

export function setThemeCache(data: WPLokerBJMThemedData, ttlMs?: number): void {
  lru.set(CACHE_KEY, data, { ttl: ttlMs ?? DEFAULT_TTL_MS });
}

export function invalidateThemeCache(): void {
  lru.delete(CACHE_KEY);
}