import { taxonomyApi } from '@/api/endpoints/Taxonomy'
import { jobsApi } from '@/api/endpoints/Jobs'
import type { TaxonomyTerm, SearchFilters, LoadMoreFilters } from '@/types'

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
}

export class TaxonomyService {
  static async fetchLokasiTerms(): Promise<TaxonomyTerm[]> {
    return await taxonomyApi.getTermsByType('lokasi')
  }

  static async fetchGenderTerms(): Promise<TaxonomyTerm[]> {
    return await taxonomyApi.getTermsByType('gender')
  }

  static async fetchPendidikanTerms(): Promise<TaxonomyTerm[]> {
    return await taxonomyApi.getTermsByType('pendidikan')
  }
}