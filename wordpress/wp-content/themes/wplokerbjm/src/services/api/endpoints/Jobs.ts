import { apiClient } from '@/services/api'

import type { 
  SearchFilters, 
  SearchResponse, 
  LoadMoreFilters,
  LoadMoreResponse,
  SingleOverlayResponse,
  SortOption,
  CarouselProps,
  JobGridProps,
  CardJob
} from '@/types'

// Local response types — kept next to the endpoint that consumes them so
// they are easy to evolve without affecting the global shared types.
type AutoSuggestResponse = string[]
type BookmarkedJobsResponse = CardJob[]

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
  fetchSingleOverlay(slug: string, options?: { signal?: AbortSignal }): Promise<SingleOverlayResponse>
  syncBookmark(ids: number[]): Promise<BookmarkedJobsResponse>
  fetchCarousel(): Promise<CarouselProps>
  fetchJobGrid(filters: Partial<SearchFilters & { paged?: number; context?: string; title?: string; total_jobs?: number }>): Promise<JobGridProps>
}

export const jobsApi: JobsApiInterface = {
  /**
   * Get auto suggestions for search input
   */
  async getAutoSuggestions(query: string): Promise<AutoSuggestResponse> {
    if (!query || query.length < 2) {
      return []
    }
    
  return (await apiClient.get<AutoSuggestResponse>('/auto-suggest/', { query })).data
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

  const res = await apiClient.get<SearchResponse>('/search/', params)
    return { ...res.data, meta: res.meta }
  },

  /**
   * Load more jobs with pagination
   */
  async loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    const params: Record<string, string | number> = { 
      paged: filters.paged
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

  const res = await apiClient.get<LoadMoreResponse>('/load-more/', params)
    return { ...res.data, meta: res.meta }
  },

  /**
   * Fetch single job overlay by slug
   */
  async fetchSingleOverlay(slug: string, options?: { signal?: AbortSignal }): Promise<SingleOverlayResponse> {
  return (await apiClient.get<SingleOverlayResponse>('/single-overlay/', { slug }, {}, options?.signal)).data
  },

  /**
   * Sync saved jobs by IDs, returning existing jobs with updated permalinks
   */
  async syncBookmark(ids: number[]): Promise<BookmarkedJobsResponse> {
    if (ids.length === 0) {
      return []
    }
  const idsParam = ids.join(',')
  return (await apiClient.get<BookmarkedJobsResponse>('/bookmarked-jobs/', { ids: idsParam })).data
  },

  /**
   * Fetch carousel jobs
   */
  async fetchCarousel(): Promise<CarouselProps> {
    return (await apiClient.get<CarouselProps>('/carousel/')).data
  },

  /**
   * Fetch job grid props
   */
  async fetchJobGrid(filters: Partial<SearchFilters & { paged?: number; context?: string; title?: string; total_jobs?: number }>): Promise<JobGridProps> {
    const params: Record<string, string | number> = {}

    if (filters.paged) params['paged'] = filters.paged
    if (filters.context) params['context'] = filters.context
    if (filters.title) params['title'] = filters.title
    if (filters.total_jobs) params['total_jobs'] = filters.total_jobs

    Object.entries(filters).forEach(([key, value]) => {
      if (['paged', 'context', 'title', 'total_jobs'].includes(key)) return
      
      if (key === 'sort' && value && isSortOption(value)) {
        params[key] = value.value
      } else if (Array.isArray(value) && value.length > 0) {
        params[key] = value.join(',')
      } else if (value && String(value).trim() !== '') {
        params[key] = String(value)
      }
    })

    return (await apiClient.get<JobGridProps>('/job-grid/', params)).data
  }
}