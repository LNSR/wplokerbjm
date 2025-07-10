import { jobsApi } from '../api/endpoints/jobs'
import { ApiError } from '../api/client'
import type { SearchFilters, LoadMoreFilters } from '@/types'

export class JobService {
  static async getAutoSuggestions(query: string) {
    try {
      return await jobsApi.getAutoSuggestions(query)
    } catch (error) {
      if (error instanceof ApiError) {
        throw new Error(`Auto suggestions failed: ${error.message}`)
      }
      throw new Error('Auto suggestions failed: Unknown error')
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
      if (error instanceof ApiError) {
        throw new Error(`Fetch carousel failed: ${error.message}`)
      }
      throw new Error('Fetch carousel failed: Unknown error')
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