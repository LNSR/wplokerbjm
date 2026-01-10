import { taxonomyApi, jobsApi, rankMathApi, wpThemeDataApi } from '@/services/api';
import type { SearchFilters, LoadMoreFilters, CarouselProps, JobGridProps, JobGridFilters } from '@/types';
import { TaxonomyType } from '@/types';
import { LRUCache } from 'lru-cache';

const cache = new LRUCache<string, any>({
  max: 500, // Maximum number of items
  ttl: 15000, // 15 seconds in milliseconds
}); // prevent excessive API calls

export class APIService {
  //* Jobs related
  static async getAutoSuggestions(query: string): Promise<ReturnType<typeof jobsApi.getAutoSuggestions>> {
    const key = `getAutoSuggestions:${query}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.getAutoSuggestions(query);
    cache.set(key, result);
    return result;
  }

  static async searchJobs(filters: SearchFilters): Promise<ReturnType<typeof jobsApi.searchJobs>> {
    const key = `searchJobs:${JSON.stringify(filters)}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.searchJobs(filters);
    cache.set(key, result);
    return result;
  }

  static async loadMoreJobs(filters: LoadMoreFilters): Promise<ReturnType<typeof jobsApi.loadMore>> {
    const key = `loadMoreJobs:${JSON.stringify(filters)}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.loadMore(filters);
    cache.set(key, result);
    return result;
  }

  static async fetchJobDetail(slug: string, options?: { signal?: AbortSignal }): Promise<ReturnType<typeof jobsApi.fetchJobDetail>> {
    const key = `fetchJobDetail:${slug}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.fetchJobDetail(slug, options);
    cache.set(key, result);
    return result;
  }

  static async syncBookmark(ids: number[]): Promise<ReturnType<typeof jobsApi.syncBookmark>> {
    const key = `syncBookmark:${ids.join(',')}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.syncBookmark(ids);
    cache.set(key, result);
    return result;
  }

  static async fetchCarousel(): Promise<CarouselProps> {
    const key = 'fetchCarousel';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.fetchCarousel();
    cache.set(key, result);
    return result;
  }

  static async fetchJobGrid(filters: JobGridFilters): Promise<JobGridProps> {
    const key = `fetchJobGrid:${JSON.stringify(filters)}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.fetchJobGrid(filters);
    cache.set(key, result);
    return result;
  }

  static async fetchJobSchemas(ids: number[]): Promise<ReturnType<typeof jobsApi.fetchJobSchemas>> {
    const key = `fetchJobSchemas:${ids.join(',')}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await jobsApi.fetchJobSchemas(ids);
    cache.set(key, result);
    return result;
  }

  //* Theme data related
  static async getThemeData(): Promise<ReturnType<typeof wpThemeDataApi.getThemeData>> {
    const key = 'getThemeData';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await wpThemeDataApi.getThemeData();
    cache.set(key, result);
    return result;
  }

  //* Taxonomy related
  static async fetchAllTerms(): Promise<ReturnType<typeof taxonomyApi.getAllTerms>> {
    const key = 'fetchAllTerms';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await taxonomyApi.getAllTerms();
    cache.set(key, result);
    return result;
  }
  static async fetchLokasiTerms(): Promise<ReturnType<typeof taxonomyApi.getTermsByType>> {
    const key = 'fetchLokasiTerms';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await taxonomyApi.getTermsByType(TaxonomyType.lokasi);
    cache.set(key, result);
    return result;
  }

  static async fetchGenderTerms(): Promise<ReturnType<typeof taxonomyApi.getTermsByType>> {
    const key = 'fetchGenderTerms';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await taxonomyApi.getTermsByType(TaxonomyType.gender);
    cache.set(key, result);
    return result;
  }

  static async fetchPendidikanTerms(): Promise<ReturnType<typeof taxonomyApi.getTermsByType>> {
    const key = 'fetchPendidikanTerms';
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await taxonomyApi.getTermsByType(TaxonomyType.pendidikan);
    cache.set(key, result);
    return result;
  }

  //* SEO related
  static async getRankMathHead(url: string): Promise<ReturnType<typeof rankMathApi.getHead>> {
    const key = `getRankMathHead:${url}`;
    if (cache.has(key)) {
      return cache.get(key);
    }
    const result = await rankMathApi.getHead(url);
    cache.set(key, result);
    return result;
  }
}