import { ref, watch, onMounted, inject, type Ref, type ComputedRef } from "vue";
import { useSuggestions } from "./useSearchForm/useSearchFormSuggestion";
import { useFilter, removeFilter } from "./useSearchForm/useFilter";
import { useSearchStore, useTaxonomyStore } from "@/stores";
import { validation } from "@/utils";
import type { SearchFormProps, SearchResponse, TaxonomyTerm } from "@/types";

export function useSearchForm(props: SearchFormProps, emit: ((event: "searchResults", response: SearchResponse) => void) & ((event: "searchError", error: string) => void)): {
  searchInput: Ref<HTMLInputElement | undefined>;
  searchStore: ReturnType<typeof useSearchStore>;
  taxonomyStore: ReturnType<typeof useTaxonomyStore>;
  selectedSuggestionIndex: Ref<number>;
  handleFocus: () => void;
  navigateSuggestions: (direction: number) => void;
  selectSuggestion: (suggestion: string) => void;
  hideSuggestionsImmediate: () => void;
  handleSubmit: () => Promise<void>;
  mapTerms: (terms: TaxonomyTerm[], placeholder: string) => { value: string; label: string; }[];
  removeFilter: typeof import('./useSearchForm/useFilter').removeFilter;
  selectedFiltersWithNames: ComputedRef<{ key: string; label: string; values: string[]; names: string[]; }[]>;
  resetFiltersAndSearch: () => Promise<void>;
} {
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
    if (props.currentSearch && searchInput.value) {
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

  async function handleSubmit(): Promise<void> {
    if (!validation.isValidFilters(searchStore.filters)) {
      return;
    }
    if (selectedSuggestionIndex.value >= 0 && searchStore.hasSuggestions) {
      const suggestion = searchStore.suggestions[selectedSuggestionIndex.value];
      if (suggestion) {
        selectSuggestion(suggestion);
        return;
      }
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

  async function resetFiltersAndSearch(): Promise<void> {
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