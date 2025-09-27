import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { debounce, validation } from "@/utils";
import type { SearchFilters, LoadMoreFilters, SearchContext, Job, LoadMoreResponse, SearchResponse } from "@/types";
import { useApi } from "@/composables/useAPI";

export const useSearchStore = defineStore("search", () => {
  // State
  const filters = ref<SearchFilters>({
    cari: "",
    lokasi: [],
    gender: [],
    pendidikan: [],
    sort: { value: "desc", label: "Terbaru" },
  });

  const searchHistory = ref<string[]>([]);
  const suggestions = ref<string[]>([]);
  const showSuggestions = ref<boolean>(false);
  const jobs = ref<Job[]>([]);
  const context = ref<SearchContext>("latest");
  const title = ref<string>("Hasil Pencarian");
  const totalJobs = ref<number>(0);
  const maxNumPages = ref<number>(1);
  const page = ref<number>(1);

  const loading = ref(false);
  const error = ref<string | null>(null);
  const suggestionsLoading = ref(false);
  const {
    fetchAutoSuggestions,
    searchJobs: apiSearchJobs,
    loadMore: apiLoadMore,
  } = useApi();

  // Computed
  const hasFilters = computed(() => {
    return !!(
      filters.value.cari ||
      filters.value.lokasi ||
      filters.value.gender ||
      filters.value.pendidikan
    );
  });

  const recentSearches = computed(() => {
    return searchHistory.value.slice(0, 5);
  });

  const hasSuggestions = computed(() => {
    return suggestions.value.length > 0;
  });

  // Actions
  function setFilters(newFilters: Partial<SearchFilters>): void {
    filters.value = {
      ...filters.value,
      ...newFilters,
      lokasi: Array.isArray(newFilters.lokasi)
        ? newFilters.lokasi
        : newFilters.lokasi
          ? [newFilters.lokasi]
          : filters.value.lokasi,
      gender: Array.isArray(newFilters.gender)
        ? newFilters.gender
        : newFilters.gender
          ? [newFilters.gender]
          : filters.value.gender,
      pendidikan: Array.isArray(newFilters.pendidikan)
        ? newFilters.pendidikan
        : newFilters.pendidikan
          ? [newFilters.pendidikan]
          : filters.value.pendidikan,
      sort:
        typeof newFilters.sort === "object" && newFilters.sort !== null
          ? newFilters.sort
          : filters.value.sort,
    };
  }

  function resetFilters(): void {
    filters.value = {
      cari: "",
      lokasi: [],
      gender: [],
      pendidikan: [],
      sort: { value: "desc", label: "Terbaru" },
    };
  }

  function addToHistory(query: string): void {
    if (query && !searchHistory.value.includes(query)) {
      searchHistory.value.unshift(query);
      if (searchHistory.value.length > 10) {
        searchHistory.value = searchHistory.value.slice(0, 10);
      }
    }
  }

  function clearHistory(): void {
    searchHistory.value = [];
  }

  const debouncedGetSuggestions = debounce(async (query: string) => {
    if (validation.isValidQuery(query)) {
      suggestionsLoading.value = true;
      try {
        suggestions.value = await fetchAutoSuggestions(query);
        showSuggestions.value = suggestions.value.length > 0;
      } catch (err) {
        suggestions.value = [];
        showSuggestions.value = false;
        console.error("Error fetching auto suggestions:", err);
      } finally {
        suggestionsLoading.value = false;
      }
    } else {
      suggestions.value = [];
      showSuggestions.value = false;
    }
  }, 500);

  function getSuggestions(query: string): void {
    debouncedGetSuggestions(query);
  }

  function selectSuggestion(suggestion: string): void {
    filters.value.cari = suggestion;
    showSuggestions.value = false;
    suggestions.value = [];
  }

  function hideSuggestions(): void {
    setTimeout(() => {
      showSuggestions.value = false;
    }, 150);
  }

  // Search functionality
  async function searchJobs(): Promise<SearchResponse> {
    loading.value = true;
    error.value = null;
    try {
      const response = await apiSearchJobs(filters.value);
      jobs.value = [...response.jobs];
      context.value = response.context || "search";
      title.value = response.title || "Hasil Pencarian";
      totalJobs.value = response.meta?.total || 0;
      maxNumPages.value = response.meta?.totalPages || 1;
      page.value = 1; // Reset page on new search
      if (filters.value.cari) {
        addToHistory(filters.value.cari);
      }
      return response;
    } catch (err) {
      error.value = err instanceof Error ? err.message : "Search failed";
      throw err;
    } finally {
      loading.value = false;
    }
  }

  const hasMore = computed(() => page.value < maxNumPages.value);

  async function loadMore(): Promise<LoadMoreResponse> {
    if (loading.value || page.value >= maxNumPages.value) {
      throw new Error("Cannot load more: already loading or no more pages");
    }

    loading.value = true;
    error.value = null;
    try {
      const loadMoreFilters: LoadMoreFilters = {
        page: page.value + 1,
        context: context.value,
        ...filters.value,
      };
      const response: LoadMoreResponse = await apiLoadMore(loadMoreFilters);
      if (Array.isArray(response.jobs) && response.jobs.length) {
        jobs.value.push(...response.jobs);
        page.value = loadMoreFilters.page;
        maxNumPages.value = response.meta?.totalPages || maxNumPages.value;
      } else {
        // dont increment page
        page.value = maxNumPages.value;
      }
      return response;
    } catch (err) {
      error.value = err instanceof Error ? err.message : "Load more failed";
      throw err;
    } finally {
      loading.value = false;
    }
  }

  return {
    // State
    filters,
    searchHistory,
    suggestions,
    showSuggestions,
    loading,
    suggestionsLoading,
    error,
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
  };
});
