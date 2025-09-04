<script setup lang="ts">
import type { SearchFormProps, SearchResponse, SortOption } from '@/types'
import { useSearchForm } from '@/composables/useSearchForm'
import { defineAsyncComponent, provide } from 'vue';
import { useDropdownController, DROPDOWN_CONTROLLER } from '@/composables/useSearchForm/useDropdown'
const CustomDropdown = defineAsyncComponent(() => import('./SearchForm/CustomDropdown.vue'));

const props = defineProps<SearchFormProps>()

const emit = defineEmits<{
  searchResults: [response: SearchResponse]
  searchError: [error: string]
}>()

const {
  searchInput,
  searchStore,
  taxonomyStore,
  selectedSuggestionIndex,
  handleFocus,
  navigateSuggestions,
  selectSuggestion,
  hideSuggestionsImmediate,
  handleSubmit,
  mapTerms,
  removeFilter,
  selectedFiltersWithNames,
  resetFiltersAndSearch
} = useSearchForm(props, emit)


const {
  toggleGender,
  togglePendidikan,
  toggleSort,
  toggleLokasi,
  lokasiLabel,
  genderLabel,
  pendidikanLabel,
  sortLabel,
  isLokasiOpen,
  isGenderOpen,
  isPendidikanOpen,
  isSortOpen,
  genderLoaded,
  lokasiLoaded,
  pendidikanLoaded,
  sortLoaded,
  controller
} = useDropdownController()

provide(DROPDOWN_CONTROLLER, controller);

const sortOptions: SortOption[] = [
  { value: 'desc', label: 'Terbaru' },
  { value: 'asc', label: 'Terlama' }
]
</script>

<template>
  <section class="mx-auto px-4 py-8 text-center">
    <h1 class="text-3xl md:text-5xl !font-bold !mb-2">Temukan Lowongan Kerja Terbaru di Banjarmasin</h1>
    <p class="mb-8 text-lg !text-semibold">Update setiap hari, mudah diakses, dan gratis!</p>
    <div class="border-2 border-blue-500 rounded-xl p-4 md:p-6 min-h-[220px] sm:min-h-[306px] md:min-h-[204px]">
      <form class="space-y-4" :action="archiveLink" method="get" @submit.prevent="handleSubmit">
        <input type="hidden" name="post_type" value="lowongan" />

        <!-- Search Input with Auto Suggestions -->
        <div class="flex gap-2 relative">
          <input ref="searchInput" type="text" placeholder="Masukkan Pekerjaan atau Perusahaan"
            class="input input-bordered w-full rounded-r-none" name="cari" v-model="searchStore.filters.cari"
            @focus="handleFocus" @blur="searchStore.hideSuggestions" @keydown.enter.prevent="handleSubmit"
            @keydown.down.prevent="navigateSuggestions(1)" @keydown.up.prevent="navigateSuggestions(-1)"
            @keydown.escape="hideSuggestionsImmediate" :disabled="searchStore.loading || taxonomyStore.loading"
            autocomplete="off" />

          <button type="submit" class="btn btn-primary rounded-l-none px-4"
            :class="{ 'opacity-75': searchStore.loading || taxonomyStore.loading }"
            :disabled="searchStore.loading || taxonomyStore.loading">
            <i class="fas"
              :class="(searchStore.loading || taxonomyStore.loading) ? 'fa-spinner fa-spin' : 'fa-search'"></i>
            <span class="sr-only">Cari</span>
          </button>

          <!-- Auto Suggestions Dropdown -->
          <Transition enter-active-class="transition transform ease-out duration-150"
            enter-from-class="opacity-0 scale-90 -translate-y-2" enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition transform ease-in duration-100"
            leave-from-class="opacity-100 scale-100 translate-y-0" leave-to-class="opacity-0 scale-90 -translate-y-2">
            <div v-show="searchStore.showSuggestions && searchStore.hasSuggestions"
              class="auto-suggest-dropdown absolute left-0 sm:left-1/2 sm:-translate-x-1/2 top-full mt-2 min-w-[12rem] w-full sm:w-auto max-w-full sm:max-w-xs md:max-w-md z-20 shadow-lg shadow-blue-200/50">
              <div
                class="bg-blue-100 dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded-xl ring-1 ring-blue-100 dark:ring-blue-900">
                <ul class="divide-y divide-blue-200 dark:divide-blue-800 max-h-52 overflow-y-auto">
                  <li v-for="(suggestion, idx) in searchStore.suggestions" :key="`${suggestion}-${idx}`">
                    <a class="block px-4 py-2 text-center text-gray-800 dark:text-white hover:bg-blue-200 dark:hover:bg-blue-900 hover:text-blue-700 dark:hover:text-blue-300 transition-colors cursor-pointer whitespace-nowrap"
                      @click="selectSuggestion(suggestion)" @mouseenter="selectedSuggestionIndex = idx">
                      {{ suggestion }}
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Filter Selects -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Lokasi -->
          <div class="relative">
            <i
              class="fas fa-map-marker-alt absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
            <button type="button" @mousedown="lokasiLoaded = true" @click="toggleLokasi"
              class="w-full text-left !px-4 !py-3 border rounded h-12 !bg-[var(--ast-global-color-5)] hover:!ring-2 hover:!ring-color[var(--ast-global-color-1) !text-[var(--ast-global-color-1)]">
              <span class="!pl-6">{{ lokasiLabel }}</span>
              <i class="fas fa-chevron-right float-right !mt-1 transition-transform"
                :class="{ 'rotate-90': isLokasiOpen }"></i>
            </button>
            <CustomDropdown id="lokasi" v-if="lokasiLoaded" v-model="searchStore.filters.lokasi"
              :options="mapTerms(taxonomyStore.lokasiTerms, 'Semua lokasi')" placeholder="Semua Lokasi" :multiple="true"
              :disabled="searchStore.loading || taxonomyStore.lokasiLoading" @open="taxonomyStore.fetchLokasiTerms()" />
          </div>
          <!-- Gender -->
          <div class="relative">
            <i
              class="fas fa-venus-mars absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
            <button type="button" @mousedown="genderLoaded = true" @click="toggleGender"
              class="w-full text-left !px-4 !py-3 border rounded h-12 !bg-[var(--ast-global-color-5)] hover:!ring-2 hover:!ring-color[var(--ast-global-color-1) !text-[var(--ast-global-color-1)]">
              <span class="!pl-6">{{ genderLabel }}</span>
              <i class="fas fa-chevron-right float-right !mt-1 transition-transform"
                :class="{ 'rotate-90': isGenderOpen }"></i>
            </button>
            <CustomDropdown id="gender" v-if="genderLoaded" v-model="searchStore.filters.gender"
              :options="mapTerms(taxonomyStore.genderTerms, 'Semua gender')" placeholder="Semua Gender" :multiple="true"
              :disabled="searchStore.loading || taxonomyStore.genderLoading" @open="taxonomyStore.fetchGenderTerms()" />
          </div>
          <!-- Pendidikan -->
          <div class="relative">
            <i
              class="fas fa-graduation-cap absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
            <button type="button" @mousedown="pendidikanLoaded = true" @click="togglePendidikan"
              class="w-full text-left !px-4 !py-3 border rounded h-12 !bg-[var(--ast-global-color-5)] hover:!ring-2 hover:!ring-color[var(--ast-global-color-1) !text-[var(--ast-global-color-1)]">
              <span class="!pl-6">{{ pendidikanLabel }}</span>
              <i class="fas fa-chevron-right float-right !mt-1 transition-transform"
                :class="{ 'rotate-90': isPendidikanOpen }"></i>
            </button>
            <CustomDropdown id="pendidikan" v-if="pendidikanLoaded" v-model="searchStore.filters.pendidikan"
              :options="mapTerms(taxonomyStore.pendidikanTerms, 'Semua pendidikan')" placeholder="Semua Pendidikan"
              :multiple="true" :disabled="searchStore.loading || taxonomyStore.pendidikanLoading"
              @open="taxonomyStore.fetchPendidikanTerms()" />
          </div>
          <!-- Sort -->
          <div class="relative">
            <i
              class="fas fa-sort-amount-down absolute left-3 top-1/2 -translate-y-1/2 text-blue-500 pointer-events-none z-10"></i>
            <button type="button" @mousedown="sortLoaded = true" @click="toggleSort"
              class="w-full text-left !px-4 !py-3 border rounded h-12 !bg-[var(--ast-global-color-5)] hover:!ring-2 hover:!ring-color[var(--ast-global-color-1) !text-[var(--ast-global-color-1)]">
              <span class="!pl-6">{{ sortLabel }}</span>
              <i class="fas fa-chevron-right float-right !mt-1 transition-transform"
                :class="{ 'rotate-90': isSortOpen }"></i>
            </button>
            <CustomDropdown id="sort" v-if="sortLoaded" v-model="searchStore.filters.sort" :options="sortOptions"
              placeholder="Urutkan" :multiple="false" :disabled="searchStore.loading || taxonomyStore.loading" />
          </div>
        </div>

        <!-- Active Filters Display -->
        <div v-if="selectedFiltersWithNames.length" class="!mb-4 flex flex-wrap items-center !gap-2 animate-fade-in">
          <span class="font-semibold text-[var(--ast-global-color-1)] flex items-center justify-center w-full !mr-2">
            <i class="fas fa-filter !mr-1"></i>
            Filter aktif:
          </span>
          <template v-for="filter in selectedFiltersWithNames" :key="filter.key">
            <template v-for="(val, idx) in filter.values" :key="val">
              <span
                class="inline-flex items-center bg-gradient-to-r bg-[var(--ast-global-color-5)] text-sm font-medium !mr-2 !px-3 !py-1 rounded-full shadow-sm transition-all duration-150 hover:shadow-md">
                <i v-if="filter.key === 'lokasi'" class="fas fa-map-marker-alt !mr-1 text-blue-500"></i>
                <i v-else-if="filter.key === 'gender'" class="fas fa-venus-mars !mr-1 text-pink-500"></i>
                <i v-else-if="filter.key === 'pendidikan'" class="fas fa-graduation-cap !mr-1 text-green-500"></i>
                {{ filter.label }}: {{ filter.names[idx] }}
                <button type="button" class="!ml-2 text-blue-500 hover:text-red-600 transition-colors duration-150"
                  @click="removeFilter(searchStore, filter.key as 'lokasi' | 'gender' | 'pendidikan', val)" aria-label="Hapus filter">
                  <i class="fas fa-times !text-xs"></i>
                </button>
              </span>
            </template>
          </template>
        </div>

        <!-- Reset Filter Button -->
        <div v-if="searchStore.context === 'search'" class="flex justify-end mt-2">
          <button type="button" class="btn btn-outline btn-secondary"
            :disabled="searchStore.loading || taxonomyStore.loading" @click="resetFiltersAndSearch">
            <i class="fas fa-undo mr-2"></i>
            Reset Filter
          </button>
        </div>

        <!-- Error Display -->
        <div
          v-if="searchStore.error || taxonomyStore.lokasiError || taxonomyStore.genderError || taxonomyStore.pendidikanError"
          class="alert alert-error">
          <i class="fas fa-exclamation-triangle"></i>
          <span>
            {{ searchStore.error
              || taxonomyStore.lokasiError
              || taxonomyStore.genderError
              || taxonomyStore.pendidikanError }}
          </span>
        </div>

        <!-- Loading State (search-related only) -->
        <div v-if="searchStore.loading || searchStore.suggestionsLoading"
          class="text-center py-4 flex flex-col items-center justify-center">
          <svg class="animate-spin h-8 w-8 text-blue-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
          <span v-if="searchStore.suggestionsLoading" class="!pl-2">Mencari saran...</span>
          <span v-else class="!pl-2">Mencari...</span>
        </div>

        <!-- Recent Searches (Optional) -->
        <div v-if="searchStore.recentSearches.length" class="mt-4">
          <h4 class="text-sm font-medium text-gray-700 !mb-2">Pencarian Terakhir:</h4>
          <div class="flex flex-wrap !gap-2">
            <button v-for="search in searchStore.recentSearches" :key="search" type="button"
              class="!px-3 !py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full"
              @click="searchStore.setFilters({ cari: search })">
              {{ search }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </section>
</template>