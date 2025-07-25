import { ApiClient } from '../Client'
import { container } from '@inversify/inversify/inversify.config'
import type { 
  SearchFilters, 
  SearchResponse, 
  AutoSuggestResponse,
  LoadMoreFilters,
  LoadMoreResponse,
  SingleOverlayResponse
} from '@/types'

export interface JobsApiInterface {
  getAutoSuggestions(query: string): Promise<AutoSuggestResponse>
  searchJobs(filters: SearchFilters): Promise<SearchResponse>
  loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse>
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
    
    return await container.get(ApiClient).get<AutoSuggestResponse>('/auto-suggest/', { query })
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

    return await container.get(ApiClient).get<SearchResponse>('/search/', params)
  },

  /**
   * Load more jobs with pagination
   */
  async loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    const params: Record<string, string | number> = { 
      paged: filters.page 
    }

    if (filters.context) {
      params['context'] = filters.context
    }

    // Handle flattened filters structure
    Object.entries(filters).forEach(([key, value]) => {
      if (key === 'page' || key === 'context') {
        // Skip these as they're handled above
        return
      }
      
      if (key === 'sort' && value && typeof value === 'object' && 'value' in value) {
        params[key] = value.value // Only send 'desc' or 'asc'
      } else if (Array.isArray(value) && value.length > 0) {
        params[key] = value.join(',')
      } else if (value && String(value).trim() !== '') {
        params[key] = String(value)
      }
    })

    return await container.get(ApiClient).get<LoadMoreResponse>('/load-more/', params)
  },

  /**
   * Fetch single job overlay by ID
   */
  async fetchSingleOverlay(id: number): Promise<SingleOverlayResponse> {
    return await container.get(ApiClient).get<SingleOverlayResponse>('/single-overlay/', { id })
  }
}