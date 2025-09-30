import { taxonomyApi } from '@/api/endpoints/Taxonomy'
import { jobsApi } from '@/api/endpoints/Jobs'
import type { SearchFilters, LoadMoreFilters } from '@/types'
import { TaxonomyType } from '@/types'

export class JobService {
  static async getAutoSuggestions(query: string): Promise<ReturnType<typeof jobsApi.getAutoSuggestions>> {
    return await jobsApi.getAutoSuggestions(query)
  }

  static async searchJobs(filters: SearchFilters): Promise<ReturnType<typeof jobsApi.searchJobs>> {
    return await jobsApi.searchJobs(filters)
  }

  static async loadMoreJobs(filters: LoadMoreFilters): Promise<ReturnType<typeof jobsApi.loadMore>> {
    return await jobsApi.loadMore(filters)
  }

  static async fetchSingleOverlay(slug: string): Promise<ReturnType<typeof jobsApi.fetchSingleOverlay>> {
    return await jobsApi.fetchSingleOverlay(slug)
  }

  static async syncBookmark(ids: number[]): Promise<ReturnType<typeof jobsApi.syncBookmark>> {
    return await jobsApi.syncBookmark(ids)
  }
}

export class TaxonomyService {
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
}