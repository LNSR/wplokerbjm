import { taxonomyApi } from '@/api/endpoints/Taxonomy'
import type { TaxonomyTerm } from '@/types'
import { jobsApi } from '@/api/endpoints/Jobs'
import type { SearchFilters, LoadMoreFilters } from '@/types'

export class JobService {
  static async getAutoSuggestions(query: string) {
    return await jobsApi.getAutoSuggestions(query)
  }

  static async searchJobs(filters: SearchFilters) {
    return await jobsApi.searchJobs(filters)
  }

  static async loadMoreJobs(filters: LoadMoreFilters) {
    return await jobsApi.loadMore(filters)
  }

  static async fetchSingleOverlay(slug: string) {
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