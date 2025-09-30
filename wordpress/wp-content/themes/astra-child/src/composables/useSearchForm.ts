import { ref, watch, onMounted, computed, type Ref, type ComputedRef } from "vue";
import { useSearchStore, useTaxonomyStore } from "@/stores";
import { TaxonomyType } from "@/types";
import type { SearchFormProps, TaxonomyTerm } from "@/types";

export function useSearchForm(props: SearchFormProps): {
  searchInput: Ref<HTMLInputElement | undefined>;
  searchStore: ReturnType<typeof useSearchStore>;
  taxonomyStore: ReturnType<typeof useTaxonomyStore>;
  selectedSuggestionIndex: Ref<number>;
  selectSuggestion: (suggestion: string) => void;
  mapTerms: (terms: TaxonomyTerm[], placeholder: string) => { value: string; label: string; }[];
  selectedFiltersWithNames: ComputedRef<{ key: string; label: string; values: string[]; names: string[]; }[]>;
} {
  const searchInput = ref<HTMLInputElement>();
  const searchStore = useSearchStore();
  const taxonomyStore = useTaxonomyStore();

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

  // Suggestion state (moved from useSearchFormSuggestion)
  const selectedSuggestionIndex = ref(-1);

  function selectSuggestion(suggestion: string): void {
    searchStore.selectSuggestion(suggestion);
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

  // Filter logic (moved from useFilter)
  const selectedFiltersWithNames = computed(() => {
    const SEMUA_VALUE = "";
    const filters: {
      key: TaxonomyType;
      label: string;
      values: string[];
      names: string[];
    }[] = [];
    if (searchStore.filters.lokasi && searchStore.filters.lokasi.length) {
      const filtered = searchStore.filters.lokasi.filter(
        (slug: string) => slug !== SEMUA_VALUE
      );
      if (filtered.length) {
        filters.push({
          key: TaxonomyType.lokasi,
          label: "Lokasi",
          values: filtered,
          names: filtered.map((slug: string) =>
            taxonomyStore.getTermNameBySlug(TaxonomyType.lokasi, slug)
          ),
        });
      }
    }
    if (searchStore.filters.gender && searchStore.filters.gender.length) {
      const filtered = searchStore.filters.gender.filter(
        (slug: string) => slug !== SEMUA_VALUE
      );
      if (filtered.length) {
        filters.push({
          key: TaxonomyType.gender,
          label: "Gender",
          values: filtered,
          names: filtered.map((slug: string) =>
            taxonomyStore.getTermNameBySlug(TaxonomyType.gender, slug)
          ),
        });
      }
    }
    if (
      searchStore.filters.pendidikan &&
      searchStore.filters.pendidikan.length
    ) {
      const filtered = searchStore.filters.pendidikan.filter(
        (slug: string) => slug !== SEMUA_VALUE
      );
      if (filtered.length) {
        filters.push({
          key: TaxonomyType.pendidikan,
          label: "Pendidikan",
          values: filtered,
          names: filtered.map((slug: string) =>
            taxonomyStore.getTermNameBySlug(TaxonomyType.pendidikan, slug)
          ),
        });
      }
    }
    return filters;
  });

  return {
    searchInput,
    searchStore,
    taxonomyStore,
    selectedSuggestionIndex,
    selectSuggestion,
    mapTerms,
    selectedFiltersWithNames,
  };
}