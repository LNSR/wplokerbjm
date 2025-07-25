import { ref, watch, onMounted, inject } from "vue";
import { useSuggestions } from "./useSearchForm/useSearchFormSuggestion";
import { useFilter, removeFilter } from "./useSearchForm/useFilter";
import { useSearchStore, useTaxonomyStore } from "@/stores";
import { validation } from "@/utils";
import type { SearchFormProps, SearchResponse, TaxonomyTerm } from "@/types";

export function useSearchForm(props: SearchFormProps, emit: any) {
  const searchInput = ref<HTMLInputElement>();
  const searchStore = useSearchStore();
  const taxonomyStore = useTaxonomyStore();
  const onSearchResults = inject<((searchData: SearchResponse) => void) | null>(
    "onSearchResults",
    null
  );

  onMounted(() => {
    searchStore.setFilters({
      cari: props.currentSearch ?? "",
      lokasi: Array.isArray(props.currentLokasi)
        ? props.currentLokasi
        : props.currentLokasi
        ? [props.currentLokasi]
        : [],
      gender: Array.isArray(props.currentGender)
        ? props.currentGender
        : props.currentGender
        ? [props.currentGender]
        : [],
      pendidikan: Array.isArray(props.currentPendidikan)
        ? props.currentPendidikan
        : props.currentPendidikan
        ? [props.currentPendidikan]
        : [],
      sort: props.currentSort,
    });
    if (!props.currentSearch && searchInput.value) {
      searchInput.value.focus();
    }
  });

  watch(
    () => searchStore.filters.cari,
    (newQuery) => {
      if (newQuery) {
        searchStore.getSuggestions(newQuery);
      }
    }
  );

  const {
    selectedSuggestionIndex,
    handleFocus,
    navigateSuggestions,
    selectSuggestion,
    hideSuggestionsImmediate,
  } = useSuggestions(searchStore, handleSubmit);

  async function handleSubmit() {
    if (!validation.isValidFilters(searchStore.filters)) {
      return;
    }
    if (selectedSuggestionIndex.value >= 0 && searchStore.hasSuggestions) {
      const suggestion = searchStore.suggestions[selectedSuggestionIndex.value];
      selectSuggestion(suggestion);
      return;
    }
    try {
      const response = await searchStore.searchJobs();
      hideSuggestionsImmediate();
      emit("searchResults", {
        ...response,
        shouldScroll: true,
        filters: { ...searchStore.filters },
      });
      setTimeout(() => {
        const grid = document.getElementById("job-grid");
        if (grid) grid.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 100);
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : "Search failed";
      emit("searchError", errorMessage);
      console.error("Search failed:", err);
    }
  }

  async function resetFiltersAndSearch() {
    searchStore.resetFilters();
    try {
      const response = await searchStore.searchJobs();
      searchStore.title = "Lowongan Terbaru"; // JobGrid Title
      searchStore.context = "latest";
      response.title = "Lowongan Terbaru";
      response.context = "latest";
      hideSuggestionsImmediate();
      emit("searchResults", {
        ...response,
        shouldScroll: false,
        filters: { ...searchStore.filters },
      });
      if (onSearchResults)
        onSearchResults({
          ...response,
          shouldScroll: false,
          filters: { ...searchStore.filters },
        });
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : "Search failed";
      emit("searchError", errorMessage);
      console.error("Search failed:", err);
    }
  }

  interface MappedTerm {
    value: string;
    label: string;
    children?: MappedTerm[];
  }

  function mapTerms(
    terms: TaxonomyTerm[],
    placeholder: string = "Semua"
  ): MappedTerm[] {
    const mapped: MappedTerm[] = terms.map((t) => ({
      value: t.slug,
      label: t.name,
      children: t.children ? mapTerms(t.children, placeholder) : undefined,
    }));
    return mapped;
  }

  const selectedFiltersWithNames = useFilter(searchStore, taxonomyStore);

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
    removeFilter,
    mapTerms,
    selectedFiltersWithNames,
  };
}
