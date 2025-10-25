import { taxonomyApi, jobsApi, rankMathApi, wpThemeDataApi } from '@/services/api';
import type { SearchFilters, LoadMoreFilters, CarouselProps, JobGridProps } from '@/types';
import { TaxonomyType } from '@/types';

export class APIService {
  //* Jobs related
  static async getAutoSuggestions(query: string): Promise<ReturnType<typeof jobsApi.getAutoSuggestions>> {
    return await jobsApi.getAutoSuggestions(query)
  }

  static async searchJobs(filters: SearchFilters): Promise<ReturnType<typeof jobsApi.searchJobs>> {
    return await jobsApi.searchJobs(filters)
  }

  static async loadMoreJobs(filters: LoadMoreFilters): Promise<ReturnType<typeof jobsApi.loadMore>> {
    return await jobsApi.loadMore(filters)
  }

  static async fetchSingleOverlay(slug: string, options?: { signal?: AbortSignal }): Promise<ReturnType<typeof jobsApi.fetchSingleOverlay>> {
    return await jobsApi.fetchSingleOverlay(slug, options)
  }

  static async syncBookmark(ids: number[]): Promise<ReturnType<typeof jobsApi.syncBookmark>> {
    return await jobsApi.syncBookmark(ids)
  }

  static async fetchCarousel(): Promise<CarouselProps> {
    return await jobsApi.fetchCarousel()
  }

  static async fetchJobGrid(filters: Partial<SearchFilters & { paged?: number; context?: string; title?: string; total_jobs?: number }>): Promise<JobGridProps> {
    return await jobsApi.fetchJobGrid(filters)
  }

  //* Theme data related
  static async getThemeData(): Promise<ReturnType<typeof wpThemeDataApi.getThemeData>> {
    return await wpThemeDataApi.getThemeData()
  }

  //* Taxonomy related
  static async fetchAllTerms(): Promise<ReturnType<typeof taxonomyApi.getAllTerms>> {
    return await taxonomyApi.getAllTerms()
  }
  static async fetchLokasiTerms(): Promise<ReturnType<typeof taxonomyApi.getTermsByType>> {
    return await taxonomyApi.getTermsByType(TaxonomyType.lokasi)
  }

  static async fetchGenderTerms(): Promise<ReturnType<typeof taxonomyApi.getTermsByType>> {
    return await taxonomyApi.getTermsByType(TaxonomyType.gender)
  }

  static async fetchPendidikanTerms(): Promise<ReturnType<typeof taxonomyApi.getTermsByType>> {
    return await taxonomyApi.getTermsByType(TaxonomyType.pendidikan)
  }

  //* SEO related
  static async getRankMathHead(url: string): Promise<ReturnType<typeof rankMathApi.getHead>> {
    return await rankMathApi.getHead(url)
  }
}