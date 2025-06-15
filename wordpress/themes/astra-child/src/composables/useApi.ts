import { ref } from 'vue'
import { JobService } from '@/services/JobService'
import type { SearchFilters, LoadMoreFilters, SearchResponse, AutoSuggestResponse, LoadMoreResponse } from '@/types'

export function useApi() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchAutoSuggestions(query: string): Promise<AutoSuggestResponse> {
    loading.value = true
    error.value = null
    
    try {
      return await JobService.getAutoSuggestions(query)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch suggestions'
      return []
    } finally {
      loading.value = false
    }
  }

  async function searchJobs(filters: SearchFilters): Promise<SearchResponse> {
    loading.value = true
    error.value = null
    
    try {
      return await JobService.searchJobs(filters)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Search failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadMore(filters: LoadMoreFilters): Promise<LoadMoreResponse> {
    loading.value = true
    error.value = null
    
    try {
      return await JobService.loadMoreJobs(filters)
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Load more failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchCarousel() {
    loading.value = true
    error.value = null

    try {
      return await JobService.fetchCarousel()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to fetch carousel data'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    fetchAutoSuggestions,
    searchJobs,
    loadMore,
    fetchCarousel
  }
}