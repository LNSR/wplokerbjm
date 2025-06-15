import { computed, ref, watch, onMounted, inject } from 'vue'
import { useSearchStore, useTaxonomyStore } from '@/stores'
import { validation } from '@/utils'
import type { SearchFormProps, SearchResponse, TaxonomyTerm } from '@/types'
import { useSuggestions } from '@/composables/useSuggestions'

export function useSearchForm(props: SearchFormProps, emit: any) {
  const searchInput = ref<HTMLInputElement>()
  const searchStore = useSearchStore()
  const taxonomyStore = useTaxonomyStore()
  const onSearchResults = inject<((searchData: SearchResponse) => void) | null>('onSearchResults', null)

  onMounted(() => {
    searchStore.setFilters({
      cari: props.currentSearch ?? '',
      lokasi: Array.isArray(props.currentLokasi) ? props.currentLokasi : (props.currentLokasi ? [props.currentLokasi] : []),
      gender: Array.isArray(props.currentGender) ? props.currentGender : (props.currentGender ? [props.currentGender] : []),
      pendidikan: Array.isArray(props.currentPendidikan) ? props.currentPendidikan : (props.currentPendidikan ? [props.currentPendidikan] : []),
      sort: props.currentSort
    })
    if (!props.currentSearch && searchInput.value) {
      searchInput.value.focus()
    }
  })

  watch(() => searchStore.filters.cari, (newQuery) => {
    if (newQuery) {
      searchStore.getSuggestions(newQuery)
    }
  })

  async function handleSubmit() {
    if (!validation.isValidFilters(searchStore.filters)) {
      return
    }
    if (selectedSuggestionIndex.value >= 0 && searchStore.hasSuggestions) {
      const suggestion = searchStore.suggestions[selectedSuggestionIndex.value]
      selectSuggestion(suggestion)
      return
    }
    try {
      const response = await searchStore.searchJobs()
      hideSuggestionsImmediate()
      emit('searchResults', { ...response, shouldScroll: true, filters: { ...searchStore.filters } })
      setTimeout(() => {
        const grid = document.getElementById('job-grid')
        if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' })
      }, 100)
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Search failed'
      emit('searchError', errorMessage)
      console.error('Search failed:', err)
    }
  }

  async function resetFiltersAndSearch() {
    searchStore.resetFilters()
    try {
      const response = await searchStore.searchJobs()
      searchStore.title = 'Lowongan Terbaru'
      searchStore.context = 'latest'
      response.title = 'Lowongan Terbaru'
      response.context = 'latest'
      hideSuggestionsImmediate()
      emit('searchResults', { ...response, shouldScroll: false, filters: { ...searchStore.filters } })
      if (onSearchResults) onSearchResults({ ...response, shouldScroll: false, filters: { ...searchStore.filters } })
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Search failed'
      emit('searchError', errorMessage)
      console.error('Search failed:', err)
    }
  }

  const {
    selectedSuggestionIndex,
    handleFocus,
    navigateSuggestions,
    selectSuggestion,
    hideSuggestionsImmediate
  } = useSuggestions(searchStore, handleSubmit)

  interface MappedTerm {
    value: string
    label: string
    children?: MappedTerm[]
  }

  function mapTerms(
    terms: TaxonomyTerm[],
    placeholder: string = 'Semua'
  ): MappedTerm[] {
    const mapped: MappedTerm[] = terms.map(t => ({
      value: t.slug,
      label: t.name,
      children: t.children ? mapTerms(t.children, placeholder) : undefined
    }))
    return mapped
  }

  
  const selectedFiltersWithNames = computed(() => {
    const SEMUA_VALUE = ''
    const filters: { key: 'lokasi' | 'gender' | 'pendidikan'; label: string; values: string[]; names: string[] }[] = []
    if (searchStore.filters.lokasi && searchStore.filters.lokasi.length) {
      const filtered = searchStore.filters.lokasi.filter(slug => slug !== SEMUA_VALUE)
      if (filtered.length) {
        filters.push({
          key: 'lokasi',
          label: 'Lokasi',
          values: filtered,
          names: filtered.map(slug => taxonomyStore.getTermNameBySlug('lokasi', slug))
        })
      }
    }
    if (searchStore.filters.gender && searchStore.filters.gender.length) {
      const filtered = searchStore.filters.gender.filter(slug => slug !== SEMUA_VALUE)
      if (filtered.length) {
        filters.push({
          key: 'gender',
          label: 'Gender',
          values: filtered,
          names: filtered.map(slug => taxonomyStore.getTermNameBySlug('gender', slug))
        })
      }
    }
    if (searchStore.filters.pendidikan && searchStore.filters.pendidikan.length) {
      const filtered = searchStore.filters.pendidikan.filter(slug => slug !== SEMUA_VALUE)
      if (filtered.length) {
        filters.push({
          key: 'pendidikan',
          label: 'Pendidikan',
          values: filtered,
          names: filtered.map(slug => taxonomyStore.getTermNameBySlug('pendidikan', slug))
        })
      }
    }
    return filters
  })

  return {
    searchInput,
    searchStore,
    taxonomyStore,
    selectedSuggestionIndex,
    handleFocus,
    navigateSuggestions,
    selectSuggestion,
    hideSuggestionsImmediate,
    handleSubmit,
    resetFiltersAndSearch,
    mapTerms,
    selectedFiltersWithNames,
  }
}