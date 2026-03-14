<script module lang="ts">
  import type { SearchResponse, SortOption } from "@/types";
  import { TaxonomyType } from "@/types";
  import { SearchTitle, SearchContext } from "@/types";
  import { searchStore, SearchUtils } from "$lib/stores/Search.svelte";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import {
    dynamicComponentStore,
    type CustomDropdownComponent,
  } from "$lib/stores/DynamicComponent.svelte";

  type LocalSearchFormProps = {
    currentSearch?: string;
    currentLokasi?: string | string[];
    currentGender?: string | string[];
    currentPendidikan?: string | string[];
    currentSort?: SortOption;
    archiveLink?: string;
    searchResults?: (payload: unknown) => unknown;
    searchError?: (msg: string) => unknown;
  };

  let selectedSuggestionIndex = -1;

  const sortOptions = [
    { value: "desc", label: "Terbaru" },
    { value: "asc", label: "Terlama" },
  ];

  export class SuggestionController {
    static handleFocus() {
      if (searchStore.hasSuggestions) {
        searchStore.showSuggestions = true;
        selectedSuggestionIndex = -1;
      }
    }

    static navigateSuggestions(direction: number) {
      if (!searchStore.showSuggestions || !searchStore.hasSuggestions) return;
      const maxIndex = searchStore.suggestions.length - 1;
      if (direction > 0) {
        selectedSuggestionIndex =
          selectedSuggestionIndex < maxIndex ? selectedSuggestionIndex + 1 : 0;
      } else {
        selectedSuggestionIndex =
          selectedSuggestionIndex > 0 ? selectedSuggestionIndex - 1 : maxIndex;
      }
    }

    static selectSuggestionUI(suggestion: string, onSubmit: () => void) {
      searchStore.selectSuggestion(suggestion);
      selectedSuggestionIndex = -1;
      onSubmit();
    }

    static hideSuggestionsImmediate() {
      searchStore.showSuggestions = false;
      selectedSuggestionIndex = -1;
    }
  }

  export class SearchFormController {
    static async performSearch(): Promise<SearchResponse> {
      if (!searchStore.hasFilters)
        throw new Error("Terjadi kesalahan pada filter");
      searchStore.filters.context = SearchContext.Search;
      if (selectedSuggestionIndex >= 0 && searchStore.hasSuggestions) {
        const suggestion = searchStore.suggestions[selectedSuggestionIndex];
        if (suggestion) {
          searchStore.selectSuggestion(suggestion);
          return await searchStore.searchJobs();
        }
      }
      return await searchStore.searchJobs();
    }

    static async performReset(): Promise<SearchResponse> {
      searchStore.resetFilters();
      const response = await searchStore.searchJobs();
      searchStore.title = SearchTitle.Latest;
      searchStore.context = SearchContext.Latest;
      response.title = SearchTitle.Latest;
      response.context = SearchContext.Latest;
      return response;
    }

    static callSearchResults(
      payload: any,
      searchResults?: (payload: any) => any,
    ) {
      try {
        if (typeof searchResults === "function") {
          searchResults(payload);
          return;
        }
      } catch (err) {
        console.error("SearchForm searchResults handler error", err);
      }
    }

    static callSearchError(payload: any, searchError?: (msg: string) => any) {
      try {
        if (typeof searchError === "function") {
          searchError(payload);
          return;
        }
      } catch (err) {
        console.error("SearchForm searchError handler error", err);
      }
    }

    static async handleSubmit(
      e?: Event,
      searchResults?: (payload: any) => any,
      searchError?: (msg: string) => any,
    ) {
      e?.preventDefault?.();
      try {
        const response = await SearchFormController.performSearch();
        SuggestionController.hideSuggestionsImmediate();
        SearchFormController.callSearchResults(
          {
            ...response,
            shouldScroll: true,
            filters: { ...searchStore.filters },
          },
          searchResults,
        );
        setTimeout(() => {
          const grid = isJobGridEl();
          if (grid) grid.scrollIntoView({ behavior: "smooth", block: "start" });
        }, 100);
      } catch (err) {
        const errorMessage =
          err instanceof Error ? err.message : "Search failed";
        searchStore.error = errorMessage;
        SearchFormController.callSearchError(errorMessage, searchError);
      }
    }

    static async resetFiltersAndSearch(
      searchResults?: (payload: any) => any,
      searchError?: (msg: string) => any,
    ) {
      try {
        const response = await SearchFormController.performReset();
        SuggestionController.hideSuggestionsImmediate();
        SearchFormController.callSearchResults(
          {
            ...response,
            shouldScroll: false,
            filters: { ...searchStore.filters },
          },
          searchResults,
        );
      } catch (err) {
        const errorMessage =
          err instanceof Error ? err.message : "Search failed";
        searchStore.error = errorMessage;
        SearchFormController.callSearchError(errorMessage, searchError);
      }
    }
  }

  let CustomDropdown: CustomDropdownComponent | null = $derived(
    dynamicComponentStore.CustomDropdown,
  );
</script>

<script lang="ts">
  import { onMount } from "svelte";
  import { taxonomyStore } from "$lib/stores/Taxonomy.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import {
    MagnifyingGlassSolid,
    MapMarkerAltSolid,
    VenusMarsSolid,
    GraduationCapSolid,
    FilterSolid,
    XmarkSolid,
    RotateLeftSolid,
    TriangleExclamationSolid,
    SortAmountUpSolid,
    SortAmountDownSolid,
  } from "svelte-awesome-icons";

  const props = $props();
  const {
    currentSearch,
    currentLokasi,
    currentGender,
    currentPendidikan,
    currentSort = { value: "desc", label: "Terbaru" },
    archiveLink = "/",
    searchResults = undefined,
    searchError = undefined,
  } = $derived<LocalSearchFormProps>(props);

  let isLokasiOpen = $state(false);
  let isGenderOpen = $state(false);
  let isPendidikanOpen = $state(false);
  let isSortOpen = $state(false);

  // UI function to remove a filter by key and value
  function removeFilter(key: TaxonomyType | string, value: string) {
    if (key === TaxonomyType.lokasi) {
      const arr = Array.isArray(searchStore.filters[TaxonomyType.lokasi])
        ? [...searchStore.filters[TaxonomyType.lokasi]]
        : [];
      const idx = arr.indexOf(value);
      if (idx !== -1)
        searchStore.filters[TaxonomyType.lokasi] = arr.filter(
          (_, i) => i !== idx,
        );
      return;
    }

    if (key === TaxonomyType.gender) {
      const arr = Array.isArray(searchStore.filters[TaxonomyType.gender])
        ? [...searchStore.filters[TaxonomyType.gender]]
        : [];
      const idx = arr.indexOf(value);
      if (idx !== -1)
        searchStore.filters[TaxonomyType.gender] = arr.filter(
          (_, i) => i !== idx,
        );
      return;
    }

    if (key === TaxonomyType.pendidikan) {
      const arr = Array.isArray(searchStore.filters[TaxonomyType.pendidikan])
        ? [...searchStore.filters[TaxonomyType.pendidikan]]
        : [];
      const idx = arr.indexOf(value);
      if (idx !== -1)
        searchStore.filters[TaxonomyType.pendidikan] = arr.filter(
          (_, i) => i !== idx,
        );
      return;
    }
  }

  // Function to update taxonomy filters
  function updateTaxonomyFilter(taxonomyType: TaxonomyType, payload: unknown) {
    if (!Array.isArray(payload) || payload.length === 0) {
      searchStore.filters[taxonomyType] = [];
      return;
    }
    searchStore.filters[taxonomyType] = SearchUtils.sanitizeArr(payload) ?? [];
  }

  // handle keyboard navigation for the main search input
  function handleInputKeyDown(e: KeyboardEvent) {
    const key = e.key;
    if (key === "Enter") {
      e.preventDefault();
      SearchFormController.handleSubmit(undefined, searchResults, searchError);
      return;
    }
    if (key === "ArrowDown") {
      e.preventDefault();
      SuggestionController.navigateSuggestions(1);
      return;
    }
    if (key === "ArrowUp") {
      e.preventDefault();
      SuggestionController.navigateSuggestions(-1);
      return;
    }
    if (key === "Escape") {
      SuggestionController.hideSuggestionsImmediate();
      return;
    }
  }

  const lokasiLabel = $derived.by(() => {
    const arr = Array.isArray(searchStore.filters[TaxonomyType.lokasi])
      ? searchStore.filters[TaxonomyType.lokasi].filter(
          (s) => typeof s === "string" && String(s).trim() !== "",
        )
      : [];
    if (!arr || arr.length === 0) return "Lokasi Belum Dipilih";
    if (arr.length === 1)
      return taxonomyStore.getTermNameBySlug(TaxonomyType.lokasi, arr[0]);
    return `${arr.length} filter dipilih`;
  });

  const genderLabel = $derived.by(() => {
    const arr = Array.isArray(searchStore.filters[TaxonomyType.gender])
      ? searchStore.filters[TaxonomyType.gender].filter(
          (s) => typeof s === "string" && String(s).trim() !== "",
        )
      : [];
    if (!arr || arr.length === 0) return "Gender Belum Dipilih";
    if (arr.length === 1)
      return taxonomyStore.getTermNameBySlug(TaxonomyType.gender, arr[0]);
    return `${arr.length} filter dipilih`;
  });

  const pendidikanLabel = $derived.by(() => {
    const arr = Array.isArray(searchStore.filters[TaxonomyType.pendidikan])
      ? searchStore.filters[TaxonomyType.pendidikan].filter(
          (s) => typeof s === "string" && String(s).trim() !== "",
        )
      : [];
    if (!arr || arr.length === 0) return "Pendidikan Belum Dipilih";
    if (arr.length === 1)
      return taxonomyStore.getTermNameBySlug(TaxonomyType.pendidikan, arr[0]);
    return `${arr.length} filter dipilih`;
  });

  const sortIsAsc = $derived.by(
    () => (searchStore.filters.sort?.value ?? "") === "asc",
  );

  const updateSortFilter = (payload: unknown) => {
    const defaultSort: SortOption = {
      value: "desc",
      label: "Terbaru",
    };
    if (!payload || Array.isArray(payload)) {
      searchStore.filters.sort = defaultSort;
      return;
    }
    // payload is the underlying value (string)
    const valStr = String(payload);
    if (valStr === "desc" || valStr === "asc") {
      searchStore.filters.sort = {
        value: valStr as "desc" | "asc",
        label: valStr === "desc" ? "Terbaru" : "Terlama",
      };
    } else {
      searchStore.filters.sort = defaultSort;
    }
  };

  $effect.pre(() => {
    if (
      isGenderOpen ||
      isLokasiOpen ||
      isPendidikanOpen ||
      (isSortOpen && !CustomDropdown)
    ) {
      if (!dynamicComponentStore.CustomDropdown) {
        void dynamicComponentStore.loadCustomDropdown();
      }
    }
  });

  onMount(() => {
    searchStore.setFilters({
      cari: currentSearch ?? "",
      [TaxonomyType.lokasi]: Array.isArray(currentLokasi)
        ? currentLokasi
        : currentLokasi
          ? [currentLokasi]
          : [],
      [TaxonomyType.gender]: Array.isArray(currentGender)
        ? currentGender
        : currentGender
          ? [currentGender]
          : [],
      [TaxonomyType.pendidikan]: Array.isArray(currentPendidikan)
        ? currentPendidikan
        : currentPendidikan
          ? [currentPendidikan]
          : [],
      sort: currentSort,
    });
  });
</script>

<section class="mx-auto p-4 text-center mb-16">
  <div
    class="lg:mx-[calc(50vw-50%)] border-2 border-[var(--wpl-global-color-1)] rounded-xl p-5 min-h-[220px] sm:min-h-[306px] md:min-h-[204px]"
  >
    <form
      class="space-y-4"
      action={archiveLink}
      method="get"
      onsubmit={(e) =>
        SearchFormController.handleSubmit(e, searchResults, searchError)}
    >
      <input type="hidden" name="post_type" value="lowongan" />

      <div class="flex gap-2 relative">
        <input
          type="text"
          placeholder="Masukkan Pekerjaan atau Perusahaan"
          class="input w-full search-input bg-[var(--wpl-global-color-5)]"
          name="cari"
          bind:value={searchStore.filters.cari}
          oninput={() => searchStore.getSuggestions(searchStore.filters.cari)}
          onfocus={SuggestionController.handleFocus}
          onblur={() => searchStore.hideSuggestions()}
          onkeydown={handleInputKeyDown}
          disabled={searchStore.loading || taxonomyStore.loading}
          autocomplete="off"
        />
        <button
          type="submit"
          class="rounded-full border px-4 hover:border"
          class:opacity-75={searchStore.loading || taxonomyStore.loading}
          disabled={searchStore.loading || taxonomyStore.loading}
        >
          {#if searchStore.loading || taxonomyStore.loading}
            <LoadingSpinner size="sm" srLabel="Memuat..." />
          {:else}
            <MagnifyingGlassSolid class="text-base" aria-hidden="true" />
          {/if}
          <span class="sr-only">Cari</span>
        </button>

        {#if searchStore.showSuggestions && searchStore.hasSuggestions}
          <div
            class="absolute left-0 sm:left-1/2 sm:-translate-x-1/2 top-full mt-2 min-w-[12rem] w-full sm:w-auto max-w-full sm:max-w-xs md:max-w-md z-20"
          >
            <div class="bg-[var(--wpl-global-color-5)] rounded-lg">
              <ul class="max-h-52 overflow-y-auto">
                {#each searchStore.suggestions as suggestion, idx}
                  <li>
                    <button
                      type="button"
                      class="w-full text-justify text-sm px-4 py-2 transition-colors cursor-pointer whitespace-nowrap"
                      onclick={() =>
                        SuggestionController.selectSuggestionUI(
                          suggestion,
                          () =>
                            SearchFormController.handleSubmit(
                              undefined,
                              searchResults,
                              searchError,
                            ),
                        )}
                      onmouseenter={() => (selectedSuggestionIndex = idx)}
                      aria-label={`Pilih saran ${suggestion}`}
                    >
                      {suggestion}
                    </button>
                  </li>
                {/each}
              </ul>
            </div>
          </div>
        {/if}
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="relative">
          <MapMarkerAltSolid
            class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10"
            aria-hidden="true"
          />
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isLokasiOpen}
            aria-controls="lokasi-listbox"
            onclick={() => {
              isLokasiOpen = !isLokasiOpen;
              if (isLokasiOpen) {
                isGenderOpen = false;
                isPendidikanOpen = false;
                isSortOpen = false;
                taxonomyStore.fetchLokasiTerms();
              }
            }}><span class="pl-6">{lokasiLabel}</span></button
          >
          {#if isLokasiOpen && CustomDropdown}
            <CustomDropdown
              id="lokasi"
              value={searchStore.filters[TaxonomyType.lokasi]}
              update={(payload) =>
                updateTaxonomyFilter(TaxonomyType.lokasi, payload)}
              options={SearchUtils.mapTerms(
                taxonomyStore.lokasiTerms,
                "Semua lokasi",
              )}
              placeholder="Semua Lokasi"
              multiple={true}
              disabled={searchStore.loading || taxonomyStore.lokasiLoading}
              open={isLokasiOpen}
              close={() => (isLokasiOpen = false)}
            />
          {/if}
        </div>

        <div class="relative">
          <VenusMarsSolid
            class="text-[var(--wpl-global-color-1)] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10"
            aria-hidden="true"
          />
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isGenderOpen}
            aria-controls="gender-listbox"
            onclick={() => {
              isGenderOpen = !isGenderOpen;
              if (isGenderOpen) {
                isLokasiOpen = false;
                isPendidikanOpen = false;
                isSortOpen = false;
                taxonomyStore.fetchGenderTerms();
              }
            }}><span class="pl-6">{genderLabel}</span></button
          >
          {#if isGenderOpen && CustomDropdown}
            <CustomDropdown
              id="gender"
              value={searchStore.filters[TaxonomyType.gender]}
              update={(payload) =>
                updateTaxonomyFilter(TaxonomyType.gender, payload)}
              options={SearchUtils.mapTerms(
                taxonomyStore.genderTerms,
                "Semua gender",
              )}
              placeholder="Semua Gender"
              multiple={true}
              disabled={searchStore.loading || taxonomyStore.genderLoading}
              open={isGenderOpen}
              close={() => (isGenderOpen = false)}
            />
          {/if}
        </div>

        <div class="relative">
          <GraduationCapSolid
            class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10"
            aria-hidden="true"
          />
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isPendidikanOpen}
            aria-controls="pendidikan-listbox"
            onclick={() => {
              isPendidikanOpen = !isPendidikanOpen;
              if (isPendidikanOpen) {
                isLokasiOpen = false;
                isGenderOpen = false;
                isSortOpen = false;
                taxonomyStore.fetchPendidikanTerms();
              }
            }}><span class="pl-6">{pendidikanLabel}</span></button
          >
          {#if isPendidikanOpen && CustomDropdown}
            <CustomDropdown
              id="pendidikan"
              value={searchStore.filters[TaxonomyType.pendidikan]}
              update={(payload) =>
                updateTaxonomyFilter(TaxonomyType.pendidikan, payload)}
              options={SearchUtils.mapTerms(
                taxonomyStore.pendidikanTerms,
                "Semua pendidikan",
              )}
              placeholder="Semua Pendidikan"
              multiple={true}
              disabled={searchStore.loading || taxonomyStore.pendidikanLoading}
              open={isPendidikanOpen}
              close={() => (isPendidikanOpen = false)}
            />
          {/if}
        </div>

        <div class="relative">
          {#if sortIsAsc}
            <SortAmountUpSolid
              class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10 w-5 h-5 transform transition-transform duration-150"
              aria-hidden="true"
            />
          {:else}
            <SortAmountDownSolid
              class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10 w-5 h-5 transform transition-transform duration-150"
              aria-hidden="true"
            />
          {/if}
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isSortOpen}
            aria-controls="sort-listbox"
            onclick={() => {
              isSortOpen = !isSortOpen;
              if (isSortOpen) {
                isLokasiOpen = false;
                isGenderOpen = false;
                isPendidikanOpen = false;
              }
            }}
            ><span class="pl-6"
              >{searchStore.filters.sort?.label ?? "Urutkan"}</span
            ></button
          >
          {#if isSortOpen && CustomDropdown}
            <CustomDropdown
              id="sort"
              value={searchStore.filters.sort}
              update={(payload) => {
                updateSortFilter(payload);
              }}
              options={sortOptions}
              placeholder="Urutkan"
              multiple={false}
              disabled={searchStore.loading || taxonomyStore.loading}
              open={isSortOpen}
              close={() => (isSortOpen = false)}
            />
          {/if}
        </div>
      </div>

      {#if searchStore.selectedFiltersWithNames && searchStore.selectedFiltersWithNames.length}
        <div class="mb-4 flex flex-wrap items-center gap-2 animate-fade-in">
          <span
            class="font-semibold text-[var(--wpl-global-color-1)] flex items-center justify-center w-full mr-2"
            ><FilterSolid class="mr-1 inline-block" aria-hidden="true" />Filter
            aktif:</span
          >
          {#each searchStore.selectedFiltersWithNames as filter}
            {#each filter.values as val, idx}
              <span
                class="inline-flex items-center bg-gradient-to-r bg-[var(--wpl-global-color-5)] text-sm font-medium mr-2 px-3 py-1 rounded-full shadow-sm transition-all duration-150"
              >
                {#if filter.key === TaxonomyType.lokasi}
                  <MapMarkerAltSolid
                    class="mr-1 text-[var(--wpl-global-color-1)] inline-block"
                    aria-hidden="true"
                  />
                {:else if filter.key === TaxonomyType.gender}
                  <VenusMarsSolid
                    class="mr-1 text-pink-500 inline-block"
                    aria-hidden="true"
                  />
                {:else}
                  <GraduationCapSolid
                    class="mr-1 text-green-500 inline-block"
                    aria-hidden="true"
                  />
                {/if}
                {filter.label}: {filter.names[idx]}
                <button
                  type="button"
                  class="ml-2 text-[var(--wpl-global-color-1)] hover:text-red-600 transition-colors duration-150"
                  onclick={() => removeFilter(filter.key, val)}
                  aria-label="Hapus filter"
                >
                  <XmarkSolid class="text-xs inline-block" aria-hidden="true" />
                </button>
              </span>
            {/each}
          {/each}
        </div>
      {/if}

      {#if searchStore.context === "search"}
        <div class="flex justify-end mt-2">
          <button
            type="button"
            class="p-3 border rounded-full"
            disabled={searchStore.loading || taxonomyStore.loading}
            onclick={() =>
              SearchFormController.resetFiltersAndSearch(
                searchResults,
                searchError,
              )}
          >
            <RotateLeftSolid
              class="mr-2 inline-block"
              aria-hidden="true"
            />Reset Filter
          </button>
        </div>
      {/if}

      {#if searchStore.error || taxonomyStore.lokasiError || taxonomyStore.genderError || taxonomyStore.pendidikanError}
        <div class="alert alert-error">
          <TriangleExclamationSolid
            class="mr-2 inline-block text-red-600"
            aria-hidden="true"
          />
          <span
            >{searchStore.error ||
              taxonomyStore.lokasiError ||
              taxonomyStore.genderError ||
              taxonomyStore.pendidikanError}</span
          >
        </div>
      {/if}

      {#if searchStore.loading || searchStore.suggestionsLoading}
        <div class="text-center py-4 flex flex-col items-center justify-center">
          <LoadingSpinner srLabel="Memuat..." size="md" />
          <span class="mt-2"
            >{searchStore.suggestionsLoading
              ? "Mencari saran..."
              : "Mencari..."}</span
          >
        </div>
      {/if}

      {#if searchStore.recentSearches && searchStore.recentSearches.length}
        <div class="mt-4">
          <span class="text-lg font-semibold"> Pencarian Terakhir: </span>
          <div class="flex flex-wrap font-semibold gap-2 mt-4">
            {#each searchStore.recentSearches as item}
              <button
                type="button"
                class="px-3 py-1 text-xs bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-7)] rounded-full"
                onclick={() => searchStore.setFilters({ cari: item })}
                >{item}</button
              >
            {/each}
          </div>
        </div>
      {/if}
    </form>
  </div>
</section>

<style lang="postcss">
  @reference "@css/app.css";
  button {
    @apply text-[var(--wpl-global-color-1)] hover:border-3;
  }
</style>
