import { jobsApi } from '../api/endpoints/jobs'
import { ApiError } from '../api/client'
import type { SearchFilters, LoadMoreFilters } from '@/types'

export class JobService {
  static async getAutoSuggestions(query: string) {
    try {
      return await jobsApi.getAutoSuggestions(query)
    } catch (error) {
      console.error('Auto suggestions failed:', error)
      return []
    }
  }

  static async searchJobs(filters: SearchFilters) {
    try {
      return await jobsApi.searchJobs(filters)
    } catch (error) {
      if (error instanceof ApiError) {
        throw new Error(`Search failed: ${error.message}`)
      }
      throw new Error('Search failed: Unknown error')
    }
  }

  static async loadMoreJobs(filters: LoadMoreFilters) {
    try {
      return await jobsApi.loadMore(filters)
    } catch (error) {
      if (error instanceof ApiError) {
        throw new Error(`Load more failed: ${error.message}`)
      }
      throw new Error('Load more failed: Unknown error')
    }
  }

  static async fetchCarousel() {
    try {
      return await jobsApi.fetchCarousel()
    } catch (error) {
      console.error('Fetch carousel failed:', error)
      return { jobs: [] }
    }
  }

  static async fetchSingleOverlay(id: number) {
    try {
      return await jobsApi.fetchSingleOverlay(id)
    } catch (error) {
      if (error instanceof ApiError) {
        throw new Error(`Fetch single overlay failed: ${error.message}`)
      }
      throw new Error('Fetch single overlay failed: Unknown error')
    }
  }
}