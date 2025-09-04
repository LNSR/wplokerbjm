import { container } from '@/inversify.config'
import { ApiClient } from '@/api'
import type { 
  SearchFilters, 
  SearchResponse, 
  AutoSuggestResponse,
  LoadMoreFilters,
  LoadMoreResponse,
  SingleOverlayResponse,
  SortOption
} from '@/types'

// Type guard to check if a value is a SortOption
function isSortOption(value: unknown): value is SortOption {
  return (
    typeof value === 'object' &&
    value !== null &&
    'value' in value &&
    'label' in value &&
    (value.value === 'desc' || value.value === 'asc') &&
    typeof value.label === 'string'
  )
}

export interface JobsApiInterface {
  getAutoSuggestions(query: string): Promise<AutoSuggestResponse>
  searchJobs(filters: SearchFilters): Promise<SearchResponse>
  loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse>
  fetchSingleOverlay(slug: string): Promise<SingleOverlayResponse>
}

export const jobsApi: JobsApiInterface = {
  /**
   * Get auto suggestions for search input
   */
  async getAutoSuggestions(query: string): Promise<AutoSuggestResponse> {
    if (!query || query.length < 2) {
      return []
    }
    
    return await container.get<ApiClient>("ApiClient").get<AutoSuggestResponse>('/auto-suggest/', { query })
  },

  /**
   * Search jobs with filters
   */
  async searchJobs(filters: SearchFilters): Promise<SearchResponse> {
    const params: Record<string, string> = {}

    Object.entries(filters).forEach(([key, value]) => {
      if (key === 'sort' && value && isSortOption(value)) {
        params[key] = value.value
      } else if (Array.isArray(value) && value.length > 0) {
        params[key] = value.join(',')
      } else if (value && String(value).trim() !== '') {
        params[key] = String(value)
      }
    })

    return await container.get<ApiClient>("ApiClient").get<SearchResponse>('/search/', params)
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
      
      if (key === 'sort' && value && isSortOption(value)) {
        params[key] = value.value // Only send 'desc' or 'asc'
      } else if (Array.isArray(value) && value.length > 0) {
        params[key] = value.join(',')
      } else if (value && String(value).trim() !== '') {
        params[key] = String(value)
      }
    })

    return await container.get<ApiClient>("ApiClient").get<LoadMoreResponse>('/load-more/', params)
  },

  /**
   * Fetch single job overlay by slug
   */
  async fetchSingleOverlay(slug: string): Promise<SingleOverlayResponse> {
    return await container.get<ApiClient>("ApiClient").get<SingleOverlayResponse>('/single-overlay/', { slug })
  }
}