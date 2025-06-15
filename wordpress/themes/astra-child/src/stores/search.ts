import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { debounce, validation } from '@/utils'
import type { SearchFilters, LoadMoreFilters } from '@/types'
import type { Job, LoadMoreResponse } from '@/types'
import { useAsyncState } from '@/composables/useAsyncState'
import { useApi } from '@/composables/useApi'

export const useSearchStore = defineStore('search', () => {
  // State
  const filters = ref<SearchFilters>({
    cari: '',
    lokasi: [],
    gender: [],
    pendidikan: [],
    sort: { value: 'desc', label: 'Terbaru' }
  })
  
  const searchHistory = ref<string[]>([])
  const suggestions = ref<string[]>([])
  const showSuggestions = ref(false)
  const jobs = ref<Job[]>([])
  const context = ref<string>('latest')
  const title = ref<string>('Hasil Pencarian')
  const totalJobs = ref<number>(0)
  const maxNumPages = ref<number>(1)
  const page = ref(1)

  // Use composable for loading/error
  const asyncState = useAsyncState()
  const suggestionsLoading = ref(false)
  const { fetchAutoSuggestions, searchJobs: apiSearchJobs, loadMore: apiLoadMore } = useApi()

  // Computed
  const hasFilters = computed(() => {
    return !!(filters.value.cari || 
              filters.value.lokasi || 
              filters.value.gender || 
              filters.value.pendidikan)
  })
  
  const recentSearches = computed(() => {
    return searchHistory.value.slice(0, 5)
  })
  
  const hasSuggestions = computed(() => {
    return suggestions.value.length > 0
  })
  
  // Actions
  function setFilters(newFilters: Partial<SearchFilters>) {
    filters.value = {
      ...filters.value,
      ...newFilters,
      lokasi: Array.isArray(newFilters.lokasi)
        ? newFilters.lokasi
        : (newFilters.lokasi ? [newFilters.lokasi] : filters.value.lokasi),
      gender: Array.isArray(newFilters.gender)
        ? newFilters.gender
        : (newFilters.gender ? [newFilters.gender] : filters.value.gender),
      pendidikan: Array.isArray(newFilters.pendidikan)
        ? newFilters.pendidikan
        : (newFilters.pendidikan ? [newFilters.pendidikan] : filters.value.pendidikan),
      sort: typeof newFilters.sort === 'object' && newFilters.sort !== null
        ? newFilters.sort
        : filters.value.sort
    }
  }
  
  function resetFilters() {
    filters.value = {
      cari: '',
      lokasi: [],
      gender: [],
      pendidikan: [],
      sort: { value: 'desc', label: 'Terbaru' }
    }
  }
  
  function addToHistory(query: string) {
    if (query && !searchHistory.value.includes(query)) {
      searchHistory.value.unshift(query)
      if (searchHistory.value.length > 10) {
        searchHistory.value = searchHistory.value.slice(0, 10)
      }
    }
  }
  
  function clearHistory() {
    searchHistory.value = []
  }
  
  const debouncedGetSuggestions = debounce(async (query: string) => {
    if (validation.isValidQuery(query)) {
      suggestionsLoading.value = true
      try {
        suggestions.value = await fetchAutoSuggestions(query)
        showSuggestions.value = suggestions.value.length > 0
      } catch (err) {
        suggestions.value = []
        showSuggestions.value = false
      } finally {
        suggestionsLoading.value = false
      }
    } else {
      suggestions.value = []
      showSuggestions.value = false
    }
  }, 300)
  
  function getSuggestions(query: string) {
    debouncedGetSuggestions(query)
  }
  
  function selectSuggestion(suggestion: string) {
    filters.value.cari = suggestion
    showSuggestions.value = false
    suggestions.value = []
  }
  
  function hideSuggestions() {
    setTimeout(() => {
      showSuggestions.value = false
    }, 150)
  }
  
  // Search functionality
  async function searchJobs() {
    asyncState.setLoading(true)
    asyncState.setError(null)
    try {
      const response = await apiSearchJobs(filters.value)
      jobs.value = [...response.jobs]
      context.value = response.context || 'search'
      title.value = response.title || 'Hasil Pencarian'
      totalJobs.value = response.totalJobs
      maxNumPages.value = response.maxNumPages
      page.value = 1 // Reset page on new search
      if (filters.value.cari) {
        addToHistory(filters.value.cari)
      }
      return response
    } catch (err) {
      asyncState.setError(err instanceof Error ? err.message : 'Search failed')
      throw err
    } finally {
      asyncState.setLoading(false)
    }
  }

  const hasMore = computed(() => page.value < maxNumPages.value)
  
  async function loadMore() {
    if (asyncState.loading.value || page.value >= maxNumPages.value) return
    asyncState.setLoading(true)
    asyncState.setError(null)
    try {
      const loadMoreFilters: LoadMoreFilters = {
        page: page.value + 1,
        context: context.value as 'search' | 'archive',
        searchFilters: filters.value
      }
      const response: LoadMoreResponse = await apiLoadMore(loadMoreFilters)
      if (Array.isArray(response.jobs) && response.jobs.length) {
        jobs.value.push(...response.jobs)
        page.value++
      } else {
        page.value = maxNumPages.value // No more pages
      }
      return response
    } catch (err) {
      asyncState.setError(err instanceof Error ? err.message : 'Load more failed')
      throw err
    } finally {
      asyncState.setLoading(false)
    }
  }

  return {
    // State
    filters,
    searchHistory,
    suggestions,
    showSuggestions,
    loading: asyncState.loading,
    suggestionsLoading,
    error: asyncState.error,
    jobs,
    context,
    title,
    totalJobs,
    maxNumPages,
    page,
    hasMore,
    
    // Computed
    hasFilters,
    recentSearches,
    hasSuggestions,
    
    // Actions
    setFilters,
    resetFilters,
    addToHistory,
    clearHistory,
    getSuggestions,
    selectSuggestion,
    hideSuggestions,
    searchJobs,
    loadMore,
    setError: asyncState.setError,
    resetError: asyncState.resetError,
  }
})