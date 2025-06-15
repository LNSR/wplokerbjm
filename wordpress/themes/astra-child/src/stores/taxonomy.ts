import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { TaxonomyService } from '@/services/TaxonomyService'
import type { TaxonomyTerm } from '@/types/api'
import { useAsyncState } from '@/composables/useAsyncState'

export const useTaxonomyStore = defineStore('taxonomy', () => {
  // State
  const lokasiTerms = ref<TaxonomyTerm[]>([])
  const genderTerms = ref<TaxonomyTerm[]>([])
  const pendidikanTerms = ref<TaxonomyTerm[]>([])

  const lokasiLoaded = ref(false)
  const genderLoaded = ref(false)
  const pendidikanLoaded = ref(false)

  // Use composable for loading/error
  const lokasiAsync = useAsyncState()
  const genderAsync = useAsyncState()
  const pendidikanAsync = useAsyncState()

  // Computed
  const loading = computed(() =>
    lokasiAsync.loading.value || genderAsync.loading.value || pendidikanAsync.loading.value
  )
  const isLoaded = computed(() =>
    lokasiLoaded.value && genderLoaded.value && pendidikanLoaded.value
  )
  const hasTerms = computed(() =>
    lokasiTerms.value.length > 0 ||
    genderTerms.value.length > 0 ||
    pendidikanTerms.value.length > 0
  )

  // Actions
  async function fetchLokasiTerms() {
    if (lokasiLoaded.value && !lokasiAsync.error.value) return
    lokasiAsync.setLoading(true)
    lokasiAsync.setError(null)
    try {
      const data = await TaxonomyService.fetchLokasiTerms()
      lokasiTerms.value = data
      lokasiLoaded.value = true
    } catch (err) {
      lokasiAsync.setError(err instanceof Error ? err.message : 'Failed to fetch lokasi terms')
      lokasiLoaded.value = false
    } finally {
      lokasiAsync.setLoading(false)
    }
  }

  async function fetchGenderTerms() {
    if (genderLoaded.value && !genderAsync.error.value) return
    genderAsync.setLoading(true)
    genderAsync.setError(null)
    try {
      const data = await TaxonomyService.fetchGenderTerms()
      genderTerms.value = data
      genderLoaded.value = true
    } catch (err) {
      genderAsync.setError(err instanceof Error ? err.message : 'Failed to fetch gender terms')
      genderLoaded.value = false
    } finally {
      genderAsync.setLoading(false)
    }
  }

  async function fetchPendidikanTerms() {
    if (pendidikanLoaded.value && !pendidikanAsync.error.value) return
    pendidikanAsync.setLoading(true)
    pendidikanAsync.setError(null)
    try {
      const data = await TaxonomyService.fetchPendidikanTerms()
      pendidikanTerms.value = data
      pendidikanLoaded.value = true
    } catch (err) {
      pendidikanAsync.setError(err instanceof Error ? err.message : 'Failed to fetch pendidikan terms')
      pendidikanLoaded.value = false
    } finally {
      pendidikanAsync.setLoading(false)
    }
  }

  function clearTerms() {
    lokasiTerms.value = []
    genderTerms.value = []
    pendidikanTerms.value = []
    lokasiLoaded.value = false
    genderLoaded.value = false
    pendidikanLoaded.value = false
    lokasiAsync.setError(null)
    genderAsync.setError(null)
    pendidikanAsync.setError(null)
  }

  function getTermNameBySlug(type: 'lokasi' | 'gender' | 'pendidikan', slug: string): string {
    let terms: TaxonomyTerm[] = []
    if (type === 'lokasi') terms = lokasiTerms.value
    if (type === 'gender') terms = genderTerms.value
    if (type === 'pendidikan') terms = pendidikanTerms.value

    function findInTree(terms: TaxonomyTerm[]): string | undefined {
      for (const t of terms) {
        if (t.slug === slug) return t.name
        if (t.children && t.children.length) {
          const found = findInTree(t.children)
          if (found) return found
        }
      }
      return undefined
    }

    return findInTree(terms) || slug
  }

  return {
    // State
    lokasiTerms,
    genderTerms,
    pendidikanTerms,
    lokasiLoaded,
    genderLoaded,
    pendidikanLoaded,
    // Loading/Error
    lokasiLoading: lokasiAsync.loading,
    genderLoading: genderAsync.loading,
    pendidikanLoading: pendidikanAsync.loading,
    lokasiError: lokasiAsync.error,
    genderError: genderAsync.error,
    pendidikanError: pendidikanAsync.error,

    // Computed
    loading,
    isLoaded,
    hasTerms,

    // Actions
    fetchLokasiTerms,
    fetchGenderTerms,
    fetchPendidikanTerms,
    clearTerms,
    resetLokasiError: lokasiAsync.resetError,
    resetGenderError: genderAsync.resetError,
    resetPendidikanError: pendidikanAsync.resetError,

    // Getters
    getTermNameBySlug,
  }
})