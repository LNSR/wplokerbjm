<script module lang="ts">
  import type {
    SearchFilters,
    TaxonomyType,
    SearchResponse,
    SortOption,
    TaxonomyGroup,
    KeyboardKeysEvent,
  } from "@/types";
  import { WPPostType } from "@/types";
  import { searchStore } from "$lib/stores/Search.svelte";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import { SearchUtils } from "@/utils/search";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";
  import { APIServiceBrowser } from "@/services/graphql/APIService";
  import { debounce } from "es-toolkit";

  type SearchFormResultsPayload = SearchResponse & {
    shouldScroll: boolean;
    filters: SearchFilters;
  };

  type SearchResultsHandler = (payload: SearchFormResultsPayload) => void;
  type SearchErrorHandler = (message: string) => void;
  type DropdownUpdatePayload = string | string[] | undefined | null;

  type LocalSearchFormProps = {
    currentSearch?: string;
    currentLokasi?: string | string[];
    currentGender?: string | string[];
    currentPendidikan?: string | string[];
    currentSort?: SortOption;
    archiveLink?: string;
    searchResults?: SearchResultsHandler;
    searchError?: SearchErrorHandler;
  };

  let isLokasiOpen = $state(false);
  let isGenderOpen = $state(false);
  let isPendidikanOpen = $state(false);
  let isSortOpen = $state(false);

  let selectedSuggestionIndex = -1;
  let suggestionsLoading = $state(false);
  let showSuggestions = $state(false);
  let suggestions = $state<string[]>([]);

  const sortOptions: SortOption[] = [
    { value: "desc", label: "Terbaru" },
    { value: "asc", label: "Terlama" },
  ];

  class SuggestionController {
    static get hasSuggestions(): boolean {
      return suggestions.length > 0;
    }
    static debouncedGetSuggestions = debounce(
      async (query: SearchFilters["cari"]) => {
        const cleanQuery = SearchUtils.sanitizeString(String(query));
        if (SearchUtils.isValidQuery(cleanQuery)) {
          suggestionsLoading = true;
          try {
            const data =
              await APIServiceBrowser.getAutoSuggestionsGraphQL(cleanQuery);
            suggestions = data || [];
            showSuggestions = suggestions.length > 0;
          } catch {
            suggestions = [];
            showSuggestions = false;
          } finally {
            suggestionsLoading = false;
          }
        } else {
          suggestions = [];
          showSuggestions = false;
        }
      },
      500,
    );

    static handleFocus() {
      if (this.hasSuggestions) {
        showSuggestions = true;
        selectedSuggestionIndex = -1;
      }
    }

    static navigateSuggestions(direction: number) {
      if (!showSuggestions || !this.hasSuggestions) return;
      const maxIndex = this.hasSuggestions ? suggestions.length - 1 : 0;
      if (direction > 0) {
        selectedSuggestionIndex =
          selectedSuggestionIndex < maxIndex ? selectedSuggestionIndex + 1 : 0;
      } else {
        selectedSuggestionIndex =
          selectedSuggestionIndex > 0 ? selectedSuggestionIndex - 1 : maxIndex;
      }
    }

    static selectSuggestion(suggestion: string): void {
      searchStore.filters.cari = SearchUtils.sanitizeString(suggestion);
      showSuggestions = false;
      suggestions = [];
    }

    static selectSuggestionUI(suggestion: string, onSubmit: () => void) {
      SuggestionController.selectSuggestion(suggestion);
      selectedSuggestionIndex = -1;
      onSubmit();
    }

    static hideSuggestionsImmediate() {
      showSuggestions = false;
      selectedSuggestionIndex = -1;
    }
    static getSuggestions(query: SearchFilters["cari"]): void {
      SuggestionController.debouncedGetSuggestions(query);
    }

    static hideSuggestions(): void {
      setTimeout(() => {
        showSuggestions = false;
      }, 150);
    }
  }

  class SearchFormController {
    private static async performSearch(): Promise<SearchResponse> {
      if (!searchStore.hasFilters)
        throw new Error("Terjadi kesalahan pada filter");
      searchStore.filters.context = "search";
      if (selectedSuggestionIndex >= 0 && SuggestionController.hasSuggestions) {
        const suggestion = suggestions[selectedSuggestionIndex];
        if (suggestion) {
          SuggestionController.selectSuggestion(suggestion);
          return await searchStore.searchJobs();
        }
      }
      return await searchStore.searchJobs();
    }

    private static async performReset(): Promise<SearchResponse> {
      searchStore.clearJobGridCardHeights();
      searchStore.resetFilters();
      const response = await searchStore.searchJobs();
      searchStore.title = "Lowongan Terbaru";
      searchStore.context = "latest";
      response.title = "Lowongan Terbaru";
      response.context = "latest";
      return response;
    }

    private static callSearchResults(
      payload: SearchFormResultsPayload,
      searchResults?: SearchResultsHandler,
    ): void {
      try {
        if (typeof searchResults === "function") {
          searchResults(payload);
          return;
        }
      } catch (err) {
        console.error("SearchForm searchResults handler error", err);
      }
    }

    private static callSearchError(
      payload: string,
      searchError?: SearchErrorHandler,
    ): void {
      try {
        if (typeof searchError === "function") {
          searchError(payload);
          return;
        }
      } catch (err) {
        console.error("SearchForm searchError handler error", err);
      }
    }

    public static async handleSubmit(
      e?: Event,
      searchResults?: SearchResultsHandler,
      searchError?: SearchErrorHandler,
    ): Promise<void> {
      e?.preventDefault?.();
      searchStore.clearJobGridCardHeights();
      try {
        const response = await this.performSearch();
        SuggestionController.hideSuggestionsImmediate();
        this.callSearchResults(
          {
            ...response,
            shouldScroll: true,
            filters: { ...searchStore.filters },
          },
          searchResults,
        );
        const grid = isJobGridEl;
        useRIC(
          () => {
            if (grid)
              grid.scrollIntoView({ behavior: "smooth", block: "start" });
          },
          { fallbackDelay: 300, fallback: "animationFrame" },
        );
      } catch (err) {
        const errorMessage =
          err instanceof Error ? err.message : "Search failed";
        searchStore.error = errorMessage;
        this.callSearchError(errorMessage, searchError);
      }
    }

    public static get selectedFiltersWithNames() {
      const filters: {
        key: TaxonomyType;
        label: string;
        values: string[];
        names: string[];
      }[] = [];

      if (
        searchStore.filters["lokasi_pekerjaan"] &&
        searchStore.filters["lokasi_pekerjaan"].length
      ) {
        const filtered = searchStore.filters["lokasi_pekerjaan"].filter(
          (slug) => typeof slug === "string" && String(slug).trim() !== "",
        );
        if (filtered.length) {
          filters.push({
            key: "lokasi_pekerjaan",
            label: "Lokasi",
            values: filtered,
            names: filtered.map((slug) =>
              taxonomyStore.getTermNameBySlug("lokasi_pekerjaan", slug),
            ),
          });
        }
      }

      if (
        searchStore.filters["gender"] &&
        searchStore.filters["gender"].length
      ) {
        const filtered = searchStore.filters["gender"].filter(
          (slug) => typeof slug === "string" && String(slug).trim() !== "",
        );
        if (filtered.length) {
          filters.push({
            key: "gender",
            label: "Gender",
            values: filtered,
            names: filtered.map((slug) =>
              taxonomyStore.getTermNameBySlug("gender", slug),
            ),
          });
        }
      }

      if (
        searchStore.filters["pendidikan"] &&
        searchStore.filters["pendidikan"].length
      ) {
        const filtered = searchStore.filters["pendidikan"].filter(
          (slug) => typeof slug === "string" && String(slug).trim() !== "",
        );
        if (filtered.length) {
          filters.push({
            key: "pendidikan",
            label: "Pendidikan",
            values: filtered,
            names: filtered.map((slug) =>
              taxonomyStore.getTermNameBySlug("pendidikan", slug),
            ),
          });
        }
      }

      return filters;
    }

    public static async resetFiltersAndSearch(
      searchResults?: SearchResultsHandler,
      searchError?: SearchErrorHandler,
    ): Promise<void> {
      try {
        const response = await this.performReset();
        SuggestionController.hideSuggestionsImmediate();
        this.callSearchResults(
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
        this.callSearchError(errorMessage, searchError);
      }
    }
  }
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
  import typia from "typia";
  import { useRIC } from "@/lib/utils/window.svelte";

  const {
    currentSearch,
    currentLokasi,
    currentGender,
    currentPendidikan,
    currentSort = { value: "desc", label: "Terbaru" },
    archiveLink = "/",
    searchResults = undefined,
    searchError = undefined,
  }: LocalSearchFormProps = $props();

  function removeFilter(key: TaxonomyType, value: string): void {
    const current = SearchUtils.sanitizeTaxonomyValue(searchStore.filters[key]);
    const idx = current.indexOf(value);
    if (idx !== -1) {
      searchStore.filters[key] = current.filter((_, i) => i !== idx);
    }
  }

  function taxonomyLabel(key: TaxonomyType, emptyLabel: string): string {
    return SearchUtils.getTaxonomyLabel(
      key,
      searchStore.filters[key],
      taxonomyStore,
      emptyLabel,
    );
  }

  /**
   *
   * @param type
   * @param fetchFnApi type from APIService to fetch terms for the dropdown
   */
  function toggleDropdown(
    type: TaxonomyGroup | "sort",
    fetchFnApi?: () => void,
  ): void {
    if (!typia.is<typeof type>(type)) throw new Error("Invalid dropdown type");
    dynamicComponentStore.loadComponentByName('CustomDropdown');
    const closeAllDropdowns = (): void => {
      isLokasiOpen = false;
      isGenderOpen = false;
      isPendidikanOpen = false;
      isSortOpen = false;
    };

    const currentOpenState =
      type === "lokasi"
        ? isLokasiOpen
        : type === "gender"
          ? isGenderOpen
          : type === "pendidikan"
            ? isPendidikanOpen
            : isSortOpen;

    if (currentOpenState) {
      closeAllDropdowns();
      return;
    }

    closeAllDropdowns();

    if (type === "lokasi") isLokasiOpen = true;
    if (type === "gender") isGenderOpen = true;
    if (type === "pendidikan") isPendidikanOpen = true;
    if (type === "sort") isSortOpen = true;

    fetchFnApi?.();
  }

  // Function to update taxonomy filters
  function updateTaxonomyFilter(
    taxonomyType: TaxonomyType,
    payload: DropdownUpdatePayload,
  ): void {
    if (!Array.isArray(payload) || payload.length === 0) {
      searchStore.filters[taxonomyType] = [];
      return;
    }
    searchStore.filters[taxonomyType] = SearchUtils.sanitizeArr(payload) ?? [];
  }

  const handleInputKeyDown = (event: KeyboardEvent): void => {
    const keyHandlers: Partial<Record<KeyboardKeysEvent, () => void>> = {
      Enter: () => {
        event.preventDefault();
        SearchFormController.handleSubmit(
          undefined,
          searchResults,
          searchError,
        );
      },
      ArrowDown: () => {
        event.preventDefault();
        SuggestionController.navigateSuggestions(1);
      },
      ArrowUp: () => {
        event.preventDefault();
        SuggestionController.navigateSuggestions(-1);
      },
      Escape: () => {
        event.preventDefault();
        SuggestionController.hideSuggestionsImmediate();
      },
    };
    keyHandlers[event.key as KeyboardKeysEvent]?.();
  };

  const lokasiLabel = $derived(
    taxonomyLabel("lokasi_pekerjaan", "Lokasi Belum Dipilih"),
  );

  const genderLabel = $derived(taxonomyLabel("gender", "Gender Belum Dipilih"));

  const pendidikanLabel = $derived(
    taxonomyLabel("pendidikan", "Pendidikan Belum Dipilih"),
  );

  const sortIsAsc = $derived(searchStore.filters.sort?.value === "asc");

  const CustomDropdown = $derived(dynamicComponentStore.getComponentByName("CustomDropdown"));

  const updateSortFilter = (payload: DropdownUpdatePayload): void => {
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
        value: valStr as SortOption["value"],
        label: valStr === "desc" ? "Terbaru" : "Terlama",
      };
    } else {
      searchStore.filters.sort = defaultSort;
    }
  };

  onMount(() => {
    searchStore.setFilters({
      cari: currentSearch ?? "",
      ["lokasi_pekerjaan"]: SearchUtils.normalizeStringOrArray(currentLokasi),
      ["gender"]: SearchUtils.normalizeStringOrArray(currentGender),
      ["pendidikan"]: SearchUtils.normalizeStringOrArray(currentPendidikan),
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
      <input type="hidden" name="post_type" value={WPPostType.Lowongan} />

      <div class="flex gap-2 relative">
        <input
          type="text"
          placeholder="Masukkan Pekerjaan atau Perusahaan"
          class="input input-bordered w-full search-input bg-[var(--wpl-global-color-5)] sm:rounded-full"
          name="cari"
          bind:value={searchStore.filters.cari}
          oninput={() =>
            SuggestionController.getSuggestions(searchStore.filters.cari)}
          onfocus={SuggestionController.handleFocus}
          onblur={() => SuggestionController.hideSuggestions()}
          onkeydown={handleInputKeyDown}
          disabled={searchStore.loading || taxonomyStore.getLoadingStatus}
          autocomplete="off"
        />
        <button
          type="submit"
          class="rounded-full btn-circle border hover:border px-4"
          class:opacity-75={searchStore.loading ||
            taxonomyStore.getLoadingStatus}
          disabled={searchStore.loading || taxonomyStore.getLoadingStatus}
        >
          {#if searchStore.loading || taxonomyStore.getLoadingStatus}
            <LoadingSpinner size="sm" srLabel="Memuat..." />
          {:else}
            <MagnifyingGlassSolid class="text-base" aria-hidden="true" />
          {/if}
          <span class="sr-only">Cari</span>
        </button>

        {#if showSuggestions && SuggestionController.hasSuggestions}
          <div
            class="absolute left-0 sm:left-1/2 sm:-translate-x-1/2 top-full mt-2 min-w-[12rem] w-full sm:w-auto max-w-full sm:max-w-xs md:max-w-md z-20"
          >
            <div class="bg-[var(--wpl-global-color-5)] rounded-lg">
              <ul class="max-h-52 overflow-y-auto">
                {#each suggestions as suggestion, idx (suggestion + idx)}
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
            class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10"
            aria-hidden="true"
          />
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isLokasiOpen}
            aria-controls="lokasi-listbox"
            onclick={() =>
              toggleDropdown("lokasi", () =>
                taxonomyStore.fetchTerms("lokasi_pekerjaan"),
              )}><span class="pl-8">{lokasiLabel}</span></button
          >
          {#if isLokasiOpen}
            <CustomDropdown
              id="lokasi"
              value={searchStore.filters["lokasi_pekerjaan"]}
              update={(payload: DropdownUpdatePayload) =>
                updateTaxonomyFilter("lokasi_pekerjaan", payload)}
              options={SearchUtils.mapTerms(
                taxonomyStore.getTerms("lokasi_pekerjaan"),
                "Semua lokasi",
              )}
              multiple={true}
              open={isLokasiOpen}
              close={() => (isLokasiOpen = false)}
            />
          {/if}
        </div>

        <div class="relative">
          <VenusMarsSolid
            class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10"
            aria-hidden="true"
          />
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isGenderOpen}
            aria-controls="gender-listbox"
            onclick={() =>
              toggleDropdown("gender", () =>
                taxonomyStore.fetchTerms("gender"),
              )}><span class="pl-8">{genderLabel}</span></button
          >
          {#if isGenderOpen}
            <CustomDropdown
              id="gender"
              value={searchStore.filters["gender"]}
              update={(payload: DropdownUpdatePayload) =>
                updateTaxonomyFilter("gender", payload)}
              options={SearchUtils.mapTerms(
                taxonomyStore.getTerms("gender"),
                "Semua gender",
              )}
              multiple={true}
              open={isGenderOpen}
              close={() => (isGenderOpen = false)}
            />
          {/if}
        </div>

        <div class="relative">
          <GraduationCapSolid
            class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10"
            aria-hidden="true"
          />
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isPendidikanOpen}
            aria-controls="pendidikan-listbox"
            onclick={() =>
              toggleDropdown("pendidikan", () =>
                taxonomyStore.fetchTerms("pendidikan"),
              )}
            ><span class="pl-8">{pendidikanLabel}</span>
          </button>
          {#if isPendidikanOpen}
            <CustomDropdown
              id="pendidikan"
              value={searchStore.filters["pendidikan"]}
              update={(payload: DropdownUpdatePayload) =>
                updateTaxonomyFilter("pendidikan", payload)}
              options={SearchUtils.mapTerms(
                taxonomyStore.getTerms("pendidikan"),
                "Semua pendidikan",
              )}
              multiple={true}
              open={isPendidikanOpen}
              close={() => (isPendidikanOpen = false)}
            />
          {/if}
        </div>

        <div class="relative">
          {#if sortIsAsc}
            <SortAmountUpSolid
              class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10 w-5 h-5 transform transition-transform duration-150"
              aria-hidden="true"
            />
          {:else}
            <SortAmountDownSolid
              class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10 w-5 h-5 transform transition-transform duration-150"
              aria-hidden="true"
            />
          {/if}
          <button
            type="button"
            class="w-full text-left px-4 py-3 border rounded-full h-12 bg-[var(--wpl-global-color-5)]"
            aria-expanded={isSortOpen}
            aria-controls="sort-listbox"
            onclick={() => toggleDropdown("sort")}
            ><span class="pl-8"
              >{searchStore.filters.sort?.label ?? "Urutkan"}</span
            ></button
          >
          {#if isSortOpen}
            <CustomDropdown
              id="sort"
              value={searchStore.filters.sort}
              update={(payload: DropdownUpdatePayload) => {
                updateSortFilter(payload);
              }}
              options={sortOptions}
              multiple={false}
              open={isSortOpen}
              close={() => (isSortOpen = false)}
            />
          {/if}
        </div>
      </div>

      {#if SearchFormController.selectedFiltersWithNames && SearchFormController.selectedFiltersWithNames.length}
        <div class="mb-4 flex flex-wrap items-center gap-2 animate-fade-in">
          <span
            class="font-semibold text-[var(--wpl-global-color-1)] flex items-center justify-center w-full mr-2"
            ><FilterSolid class="mr-1 inline-block" aria-hidden="true" />Filter
            aktif:</span
          >
          {#each SearchFormController.selectedFiltersWithNames as filter (filter.key)}
            {#each filter.values as val, idx (val + idx)}
              <span
                class="badge badge-lg gap-2 bg-[var(--wpl-global-color-5)] shadow-sm transition-all duration-150"
              >
                {#if filter.key === "lokasi_pekerjaan"}
                  <MapMarkerAltSolid
                    class="text-[var(--wpl-global-color-1)]"
                    aria-hidden="true"
                  />
                {:else if filter.key === "gender"}
                  <VenusMarsSolid class="text-pink-500" aria-hidden="true" />
                {:else}
                  <GraduationCapSolid
                    class="text-green-500"
                    aria-hidden="true"
                  />
                {/if}
                {filter.label}: {filter.names[idx]}
                <button
                  type="button"
                  class="btn btn-ghost btn-xs btn-circle text-[var(--wpl-global-color-1)] hover:text-red-600 transition-colors duration-150 ml-1"
                  onclick={() => removeFilter(filter.key, val)}
                  aria-label="Hapus filter"
                >
                  <XmarkSolid class="text-xs" aria-hidden="true" />
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
            class="btn btn-outline rounded-full bg-[var(--wpl-global-color-5)] hover:border-[var(--wpl-global-color-1)]"
            disabled={searchStore.loading || taxonomyStore.getLoadingStatus}
            onclick={() =>
              SearchFormController.resetFiltersAndSearch(
                searchResults,
                searchError,
              )}
          >
            <RotateLeftSolid class="mr-2" aria-hidden="true" />Reset Filter
          </button>
        </div>
      {/if}

      {#if searchStore.error || taxonomyStore.anyError}
        <div class="alert alert-error">
          <TriangleExclamationSolid
            class="mr-2 inline-block text-red-600"
            aria-hidden="true"
          />
          <span>{searchStore.error || taxonomyStore.anyError}</span>
        </div>
      {/if}

      {#if searchStore.loading || suggestionsLoading}
        <div class="text-center py-4 flex flex-col items-center justify-center">
          <LoadingSpinner srLabel="Memuat..." size="md" />
          <span class="mt-2"
            >{suggestionsLoading ? "Mencari saran..." : "Mencari..."}</span
          >
        </div>
      {/if}

      {#if searchStore.recentSearches && searchStore.recentSearches.length}
        <div class="mt-4">
          <span class="text-lg font-semibold"> Pencarian Terakhir: </span>
          <div class="flex flex-wrap font-semibold gap-2 mt-4">
            {#each searchStore.recentSearches as item (item)}
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
