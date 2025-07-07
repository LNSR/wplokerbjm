import { apiClient } from '../client'
import type { 
  SearchFilters, 
  SearchResponse, 
  AutoSuggestResponse,
  LoadMoreFilters,
  LoadMoreResponse,
  Job,
  SingleOverlayResponse
} from '@/types'

export interface JobsApiInterface {
  getAutoSuggestions(query: string): Promise<AutoSuggestResponse>
  searchJobs(filters: SearchFilters): Promise<SearchResponse>
  loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse>
  fetchCarousel(): Promise<{ jobs: Job[] }>
  fetchSingleOverlay(id: number): Promise<SingleOverlayResponse>
}

export const jobsApi: JobsApiInterface = {
  
  /**
   * Get auto suggestions for search input
   */
  async getAutoSuggestions(query: string): Promise<AutoSuggestResponse> {
    if (!query || query.length < 2) {
      return []
    }
    
    return await apiClient.get<AutoSuggestResponse>('/auto-suggest/', { query })
  },

  /**
   * Search jobs with filters
   */
  async searchJobs(filters: SearchFilters): Promise<SearchResponse> {
    const params: Record<string, string> = {}

    Object.entries(filters).forEach(([key, value]) => {
      if (key === 'sort' && value && typeof value === 'object' && 'value' in value) {
        params[key] = value.value
      } else if (Array.isArray(value) && value.length > 0) {
        params[key] = value.join(',')
      } else if (value && String(value).trim() !== '') {
        params[key] = String(value)
      }
    })

    return await apiClient.get<SearchResponse>('/search/', params)
  },

  /**
   * Load more jobs with pagination
   */
  async loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    const params: Record<string, string | number> = { 
      paged: filters.page 
    }

    if (filters.context) {
      params.context = filters.context
    }

    if (filters.searchFilters) {
      Object.entries(filters.searchFilters).forEach(([key, value]) => {
        if (key === 'sort' && value && typeof value === 'object' && 'value' in value) {
          params[key] = value.value // Only send 'desc' or 'asc'
        } else if (Array.isArray(value) && value.length > 0) {
          params[key] = value.join(',')
        } else if (value && String(value).trim() !== '') {
          params[key] = String(value)
        }
      })
    }

    return await apiClient.get<LoadMoreResponse>('/load-more/', params)
  },

  /**
   * Fetch carousel jobs
   */
  async fetchCarousel(): Promise<{ jobs: Job[] }> {
    return await apiClient.get<{ jobs: Job[] }>('/carousel/')
  },

  /**
   * Fetch single job overlay by ID
   */
  async fetchSingleOverlay(id: number): Promise<SingleOverlayResponse> {
    return await apiClient.get<SingleOverlayResponse>('/single-overlay/', { id })
  }
}