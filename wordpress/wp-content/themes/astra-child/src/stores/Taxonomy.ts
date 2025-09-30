import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { TaxonomyService } from '@/services/APIService'
import type { TaxonomyTerm } from '@/types'
import { TaxonomyType } from '@/types'

export const useTaxonomyStore = defineStore('taxonomy', () => {
  // State
  const lokasiTerms = ref<TaxonomyTerm[]>([])
  const genderTerms = ref<TaxonomyTerm[]>([])
  const pendidikanTerms = ref<TaxonomyTerm[]>([])

  const lokasiLoaded = ref(false)
  const genderLoaded = ref(false)
  const pendidikanLoaded = ref(false)

  const lokasiLoading = ref(false)
  const genderLoading = ref(false)
  const pendidikanLoading = ref(false)
  const lokasiError = ref<string | null>(null)
  const genderError = ref<string | null>(null)
  const pendidikanError = ref<string | null>(null)

  // Computed
  const loading = computed(() =>
    lokasiLoading.value || genderLoading.value || pendidikanLoading.value
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
  async function fetchLokasiTerms(): Promise<void> {
    if (lokasiLoaded.value && !lokasiError.value) return
    lokasiLoading.value = true
    lokasiError.value = null
    try {
      const data = await TaxonomyService.fetchLokasiTerms()
      lokasiTerms.value = data
      lokasiLoaded.value = true
    } catch (err) {
      lokasiError.value = err instanceof Error ? err.message : 'Failed to fetch lokasi terms'
      lokasiLoaded.value = false
    } finally {
      lokasiLoading.value = false
    }
  }

  async function fetchGenderTerms(): Promise<void> {
    if (genderLoaded.value && !genderError.value) return
    genderLoading.value = true
    genderError.value = null
    try {
      const data = await TaxonomyService.fetchGenderTerms()
      genderTerms.value = data
      genderLoaded.value = true
    } catch (err) {
      genderError.value = err instanceof Error ? err.message : 'Failed to fetch gender terms'
      genderLoaded.value = false
    } finally {
      genderLoading.value = false
    }
  }

  async function fetchPendidikanTerms(): Promise<void> {
    if (pendidikanLoaded.value && !pendidikanError.value) return
    pendidikanLoading.value = true
    pendidikanError.value = null
    try {
      const data = await TaxonomyService.fetchPendidikanTerms()
      pendidikanTerms.value = data
      pendidikanLoaded.value = true
    } catch (err) {
      pendidikanError.value = err instanceof Error ? err.message : 'Failed to fetch pendidikan terms'
      pendidikanLoaded.value = false
    } finally {
      pendidikanLoading.value = false
    }
  }

  function clearTerms(): void {
    lokasiTerms.value = []
    genderTerms.value = []
    pendidikanTerms.value = []
    lokasiLoaded.value = false
    genderLoaded.value = false
    pendidikanLoaded.value = false
    lokasiError.value = null
    genderError.value = null
    pendidikanError.value = null
  }

  function resetLokasiError(): void {
    lokasiError.value = null
  }
  function resetGenderError(): void {
    genderError.value = null
  }
  function resetPendidikanError(): void {
    pendidikanError.value = null
  }

  function getTermNameBySlug(type: TaxonomyType, slug: string): string {
    let terms: TaxonomyTerm[] = []
    if (type === TaxonomyType.lokasi) terms = lokasiTerms.value
    if (type === TaxonomyType.gender) terms = genderTerms.value
    if (type === TaxonomyType.pendidikan) terms = pendidikanTerms.value

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
    lokasiLoading,
    genderLoading,
    pendidikanLoading,
    lokasiError,
    genderError,
    pendidikanError,

    // Computed
    loading,
    isLoaded,
    hasTerms,

    // Actions
    fetchLokasiTerms,
    fetchGenderTerms,
    fetchPendidikanTerms,
    clearTerms,
    resetLokasiError,
    resetGenderError,
    resetPendidikanError,

    // Getters
    getTermNameBySlug,
  }
})