<script lang="ts">
  import { onMount, type Component } from "svelte";
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
  import { useRIC } from "@/utils/window";
  import type {
    SearchFilters,
    TaxonomyType,
    SearchResponse,
    SortOption,
    TaxonomyGroup,
    KeyboardKeysEvent,
  } from "@/types";
  import { WPPostType } from "@/types";
  import { jobListingStore } from "$lib/stores/JobListingStore.svelte";
  import { isJobGridEl } from "$lib/utils/elements.svelte";
  import { SearchUtils } from "@/utils/search";
  import { componentRegistry } from "@/lib/stores/ComponentRegistry.svelte";
  import { APIServiceBrowser } from "@/services/graphql/APIService";
  import { debounce } from "es-toolkit";
  import typia from "typia";

  interface SearchFormResultsPayload extends SearchResponse {
    shouldScroll: boolean;
    filters: SearchFilters;
  }

  type LocalSearchFormProps = {
    currentSearch?: string;
    currentLokasi?: string | string[];
    currentGender?: string | string[];
    currentPendidikan?: string | string[];
    currentSort?: SortOption;
    archiveLink?: string;
    searchResults?: (payload: SearchFormResultsPayload) => void;
    searchError?: (message: string) => void;
  };

  const sortOptions: SortOption[] = [
    { value: "desc", label: "Terbaru" },
    { value: "asc", label: "Terlama" },
  ];

  // unused
  // props that can be passed from the server
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

  /**
   * Handles search suggestions logic,
   * including fetching suggestions based on user input,
   * navigating through suggestions with keyboard,
   * and selecting suggestions to populate the search input.
   */
  class SuggestionHandler {
    public selectedSuggestionIndex = $state(-1);
    public suggestionsLoading = $state(false);
    public showSuggestions = $state(false);
    public suggestions = $state<string[]>([]);
    public get hasSuggestions(): boolean {
      return this.suggestions.length > 0;
    }

    #debouncedFetchSuggestions = debounce(
      async (query: SearchFilters["cari"]) => {
        const cleanQuery = SearchUtils.sanitizeString(String(query));
        if (SearchUtils.isValidQuery(cleanQuery)) {
          this.suggestionsLoading = true;
          try {
            const data =
              await APIServiceBrowser.getAutoSuggestionsGraphQL(cleanQuery);
            this.suggestions = data || [];
            this.showSuggestions = this.suggestions.length > 0;
          } catch {
            this.suggestions = [];
            this.showSuggestions = false;
          } finally {
            this.suggestionsLoading = false;
            return;
          }
        }
        this.suggestions = [];
        this.showSuggestions = false;
      },
      500,
    );

    /**
     * Show the suggestion panel when the input receives focus.
     * Resets the selection index so the first suggestion is not pre-selected.
     */
    public handleFocus() {
      if (this.hasSuggestions) {
        this.showSuggestions = true;
        this.selectedSuggestionIndex = -1;
      }
    }

    /**
     * Move the highlighted suggestion up or down in the suggestion list.
     * Wraps around when reaching the start or end of the list.
     * @param direction Positive to move down, negative to move up.
     */
    public navigateSuggestions(direction: number) {
      if (!this.showSuggestions || !this.hasSuggestions) return;
      const maxIndex = this.hasSuggestions ? this.suggestions.length - 1 : 0;
      if (direction > 0) {
        this.selectedSuggestionIndex =
          this.selectedSuggestionIndex < maxIndex
            ? this.selectedSuggestionIndex + 1
            : 0;
        return;
      }
      this.selectedSuggestionIndex =
        this.selectedSuggestionIndex > 0
          ? this.selectedSuggestionIndex - 1
          : maxIndex;
    }

    /**
     * Apply the selected suggestion to the search input and close the suggestion panel.
     * @param suggestion The suggestion text to apply.
     */
    public selectSuggestion(suggestion: string): void {
      jobListingStore.filters.cari = SearchUtils.sanitizeString(suggestion);
      this.showSuggestions = false;
      this.suggestions = [];
    }

    /**
     * Select the given suggestion and then invoke the provided submission callback.
     * This is used when a suggestion is clicked by the user.
     * @param suggestion The suggestion to select.
     * @param onSubmit Callback to execute after selection.
     * Delegate to @see SearchFormHandler.submitSuggestionSearch for handling the actual submission logic.
     */
    public async submitSuggestion(
      suggestion: string,
      onSubmit: () => Promise<void>,
    ) {
      this.selectSuggestion(suggestion);
      this.selectedSuggestionIndex = -1;
      await onSubmit();
    }

    /**
     * Immediately hide the suggestion list and reset the selection index.
     */
    public hideSuggestionsImmediate() {
      this.showSuggestions = false;
      this.selectedSuggestionIndex = -1;
    }

    /**
     * Request suggestions for the current query using a debounce timer.
     * @param query The current search query from the input field.
     */
    public getSuggestions(query: SearchFilters["cari"]): void {
      this.#debouncedFetchSuggestions(query);
    }

    /**
     * Hide the suggestion list after a short delay to allow click events
     * within the suggestion panel to complete.
     */
    public hideSuggestions(): void {
      setTimeout(() => {
        this.showSuggestions = false;
      }, 150);
    }
  }

  class DropdownHandler {
    public isLokasiOpen = $state(false);
    public isGenderOpen = $state(false);
    public isPendidikanOpen = $state(false);
    public isSortOpen = $state(false);

    /**
     * Get the display label for a taxonomy filter based on current selection, with a fallback if no selection is made.
     * @param key The taxonomy type
     * @param emptyLabel The label to display if no selection is made
     * @returns The display label
     */
    public taxonomyLabel(key: TaxonomyType, emptyLabel: string): string {
      return SearchUtils.getTaxonomyLabel(
        key,
        jobListingStore.filters[key],
        taxonomyStore,
        emptyLabel,
      );
    }

    /**
     * Clear a specific value from a taxonomy filter in the search store, effectively removing that filter and triggering a new search if needed.
     * @param key The taxonomy type
     * @param value The value to remove from the filter
     */
    public clearDropdownFilter(key: TaxonomyType, value: string): void {
      const current = SearchUtils.sanitizeTaxonomyValue(
        jobListingStore.filters[key],
      );
      const idx = current.indexOf(value);
      if (idx !== -1) {
        jobListingStore.filters[key] = current.filter((_, i) => i !== idx);
      }
    }

    public closeDropdowns(): void {
      this.isLokasiOpen &&= false;
      this.isGenderOpen &&= false;
      this.isPendidikanOpen &&= false;
      this.isSortOpen &&= false;
    }

    /**
     * Compute the currently selected filters along with their display names for all taxonomy groups.
     * This is used to show active filters in the UI and provide a way to clear them.
     */
    public get selectedFiltersWithNames() {
      const filters: {
        key: TaxonomyType;
        label: string;
        values: string[];
        names: string[];
      }[] = [];

      interface TaxonomyItem {
        key: TaxonomyType;
        label: Capitalize<TaxonomyGroup>;
      }

      const TaxonomyGroups: TaxonomyItem[] = [
        { key: "lokasi_pekerjaan", label: "Lokasi" },
        { key: "gender", label: "Gender" },
        { key: "pendidikan", label: "Pendidikan" },
      ];

      return TaxonomyGroups.reduce((acc, { key, label }) => {
        const values = SearchUtils.sanitizeTaxonomyValue(
          jobListingStore.filters[key],
        );
        if (values && values.length > 0) {
          const names = values.map((slug) =>
            taxonomyStore.getTermNameBySlug(key, slug),
          );
          acc.push({ key, label, values, names });
        }
        return acc;
      }, filters);
    }

    /**
     * Toggle dropdown visibility based on type, ensuring only one dropdown is open at a time.
     * @param type The dropdown type
     * @param fetchFnApi APIService function to fetch terms for the dropdown
     */
    public toggleDropdown(
      type: TaxonomyGroup | "sort",
      fetchtype?: TaxonomyType,
    ): void {
      void componentRegistry.loadComponentByName("CustomDropdown");

      this.closeDropdowns();

      switch (type) {
        case "lokasi":
          this.isLokasiOpen = true;
          break;
        case "gender":
          this.isGenderOpen = true;
          break;
        case "pendidikan":
          this.isPendidikanOpen = true;
          break;
        case "sort":
          this.isSortOpen = true;
          break;
      }

      if (fetchtype) taxonomyStore.fetchTerms(fetchtype);
    }

    /**
     * Update the search store filters for a given taxonomy type based on dropdown selection, handling both single and multiple selection cases.
     * @param taxonomyType The taxonomy type to update
     * @param payload The selected value(s) from the dropdown
     */
    public updateTaxonomyFilter(
      taxonomyType: TaxonomyType,
      payload: unknown,
    ): void {
      if (!Array.isArray(payload) || payload.length === 0) {
        jobListingStore.filters[taxonomyType] = [];
        return;
      }
      jobListingStore.filters[taxonomyType] =
        SearchUtils.sanitizeArr(payload) ?? [];
    }

    /**
     * Normalize and apply the selected sort option to the search filters.
     * Falls back to the default sort option when the payload is invalid.
     * @param payload The raw dropdown value or selection payload.
     */
    public updateSortFilter = (payload: unknown): void => {
      const defaultSort: SortOption = {
        value: "desc",
        label: "Terbaru",
      };
      if (!payload || Array.isArray(payload)) {
        jobListingStore.filters.sort = defaultSort;
        return;
      }
      // payload is the underlying value (string)
      const valStr = String(payload);
      if (typia.is<SortOption["value"]>(valStr)) {
        jobListingStore.filters.sort = {
          value: valStr as SortOption["value"],
          label: valStr === "desc" ? "Terbaru" : "Terlama",
        };
        return;
      }
      jobListingStore.filters.sort = defaultSort;
    };
  }

  /**
   * search form controller for submit, reset, and callback orchestration.
   *
   * Mediate between Suggestion and Dropdown handlers that share $props callbacks to execute searches.
   *
   * The controller is static because its doesn't have it's own state;
   */
  class SearchFormController {
    /**
     * Handle the search form submission event.
     * Prevents default form navigation and invokes the search logic.
     * @param e Optional submit event to prevent default browser behavior.
     */
    public static async handleSubmit(e?: Event): Promise<void> {
      e?.preventDefault?.();
      try {
        const response = await SearchFormController.performSearch();
        suggestionHandler.hideSuggestionsImmediate();
        SearchFormController.callSearchResults({
          ...response,
          shouldScroll: true,
          filters: { ...jobListingStore.filters },
        });
      } catch (err) {
        const errorMessage =
          err instanceof Error ? err.message : "Search failed";
        jobListingStore.error = errorMessage;
        SearchFormController.callSearchIsError(errorMessage);
      }
    }

    /**
     * Submit a search based on a selected suggestion, ensuring the suggestion is applied to the search filters before executing the search logic.
     * @param suggestion The suggestion text to apply and search for.
     * @returns void
     */
    public static async submitSuggestionSearch(
      suggestion: string,
    ): Promise<void> {
      await suggestionHandler.submitSuggestion(
        suggestion,
        SearchFormController.handleSubmit,
      );
    }

    /**
     * Scroll the job grid into view after a search submission if shouldScroll is true.
     * @param shouldScroll Whether the grid should be scrolled into view.
     */
    private static scrollAfterSubmit(
      shouldScroll: SearchFormResultsPayload["shouldScroll"],
    ): void {
      const grid = isJobGridEl();
      if (grid && shouldScroll) {
        useRIC(
          () => {
            try {
              grid.scrollIntoView({ behavior: "smooth", block: "start" });
            } catch (err) {
              console.error(
                "Scroll into view failed, fallback to instant scroll",
                err,
              );
            }
          },
          { fallbackDelay: 300, fallback: "timeout" },
        );
      }
    }

    /**
     * Reset search filters to defaults and execute a fresh search.
     * The result is forwarded to the parent callback without scrolling.
     */
    public static async resetFiltersAndSearch(): Promise<void> {
      try {
        const response = await SearchFormController.performReset();
        suggestionHandler.hideSuggestionsImmediate();
        SearchFormController.callSearchResults({
          ...response,
          shouldScroll: false,
          filters: { ...jobListingStore.filters },
        });
      } catch (err) {
        const errorMessage =
          err instanceof Error ? err.message : "Search failed";
        jobListingStore.error = errorMessage;
        SearchFormController.callSearchIsError(errorMessage);
      }
    }

    /**
     * Execute the active search using the current filters and suggestion state.
     * If a suggestion is currently highlighted, select it before searching.
     * @throws Error when filters are invalid or missing.
     * @returns The search response from the job listing store.
     */
    private static async performSearch(): Promise<SearchResponse> {
      if (!jobListingStore.hasFilters)
        throw new Error("Terjadi kesalahan pada filter");
      jobListingStore.filters.context = "search";
      if (
        suggestionHandler.selectedSuggestionIndex >= 0 &&
        suggestionHandler.hasSuggestions
      ) {
        const suggestion =
          suggestionHandler.suggestions[
            suggestionHandler.selectedSuggestionIndex
          ];
        if (suggestion) {
          suggestionHandler.selectSuggestion(suggestion);
          return await jobListingStore.searchJobs();
        }
      }
      return await jobListingStore.searchJobs();
    }

    /**
     * Refresh all filters and load the latest job results.
     * Also updates the store and response metadata to the default "latest" state.
     * @returns The search response after resetting filters.
     */
    private static async performReset(): Promise<SearchResponse> {
      jobListingStore.resetFilters();
      const response = await jobListingStore.searchJobs();
      jobListingStore.title = "Lowongan Terbaru";
      jobListingStore.context = "latest";
      response.title = "Lowongan Terbaru";
      response.context = "latest";
      return response;
    }

    /**
     * Forward search result payload to the optional parent callback.
     * If scrolling is requested, schedule it after render.
     * @param payload The search results payload to pass to the handler.
     */
    private static callSearchResults(payload: SearchFormResultsPayload): void {
      try {
        searchResults?.(payload);
        if (payload.shouldScroll) {
          SearchFormController.scrollAfterSubmit(payload.shouldScroll);
        }
      } catch (err) {
        console.error("SearchForm searchResults handler error", err);
      }
    }

    /**
     * Handle keyboard events inside the search input field.
     * Supports submission, suggestion navigation, and closing the suggestion list.
     * @param event The keyboard event from the input field.
     */
    public static handleInputKeyDown = (event: KeyboardEvent): void => {
      const keyHandlers: Partial<Record<KeyboardKeysEvent, () => void>> = {
        Enter: () => {
          event.preventDefault();
          SearchFormController.handleSubmit(undefined);
        },
        ArrowDown: () => {
          event.preventDefault();
          suggestionHandler.navigateSuggestions(1);
        },
        ArrowUp: () => {
          event.preventDefault();
          suggestionHandler.navigateSuggestions(-1);
        },
        Escape: () => {
          event.preventDefault();
          suggestionHandler.hideSuggestionsImmediate();
        },
      };
      keyHandlers[event.key as KeyboardKeysEvent]?.();
    };

    /**
     * Forward error payload to the optional parent error callback.
     * @param payload Error message string from the search flow.
     */
    private static callSearchIsError(payload: string): void {
      try {
        searchError?.(payload);
      } catch (err) {
        console.error("SearchForm searchError handler error", err);
      }
    }
  }

  const suggestionHandler = new SuggestionHandler();
  const dropdownHandler = new DropdownHandler();

  onMount(() => {
    jobListingStore.setFilters({
      cari: currentSearch ?? "",
      ["lokasi_pekerjaan"]: SearchUtils.normalizeStringOrArray(currentLokasi),
      ["gender"]: SearchUtils.normalizeStringOrArray(currentGender),
      ["pendidikan"]: SearchUtils.normalizeStringOrArray(currentPendidikan),
      sort: currentSort,
    });
  });
</script>

{#snippet taxonomyDropdownButton(
  groupKey: TaxonomyGroup, // normalized internal WP for UI grouping, e.g. "lokasi"
  storeKey: TaxonomyType, // internal WP taxonomy name, e.g. "lokasi_pekerjaan"
  Icon: Component, // icon component to display inside the button
  emptyLabel: string, // initial and fallback label when no selection is made
  aria: {
    expanded: boolean;
    controls: string;
  },
  CustomDropdown = componentRegistry.getComponentByName("CustomDropdown"),
)}
  <div class="relative min-w-0">
    <Icon
      class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--wpl-global-color-1)] pointer-events-none z-10"
      aria-hidden="true"
    />
    <button
      type="button"
      class="min-h-12 h-auto w-full min-w-0 rounded-full border bg-[var(--wpl-global-color-5)] px-4 py-3 text-left"
      aria-expanded={aria.expanded}
      aria-controls={aria.controls}
      onclick={() => dropdownHandler.toggleDropdown(groupKey, storeKey)}
    >
      <span
        class="block min-w-0 break-words pl-8 leading-tight"
        >{dropdownHandler.taxonomyLabel(storeKey, emptyLabel)}</span
      >
    </button>
    {#if aria.expanded}
      <CustomDropdown
        id={aria.controls}
        value={jobListingStore.filters[storeKey]}
        update={(payload: unknown) =>
          dropdownHandler.updateTaxonomyFilter(storeKey, payload)}
        options={SearchUtils.mapTerms(
          taxonomyStore.getTerms(storeKey),
          emptyLabel,
        )}
        multiple={true}
        open={aria.expanded}
        close={() => dropdownHandler.closeDropdowns()}
      />
    {/if}
  </div>
{/snippet}

<section class="mx-auto mb-16 p-2 text-center sm:p-4">
  <div
    class="min-w-0 rounded-xl border-2 border-[var(--wpl-global-color-1)] p-3 sm:min-h-[260px] sm:p-5 md:min-h-[300px] lg:mx-[calc(50vw-50%)]"
  >
    <form
      class="space-y-4"
      action={archiveLink}
      method="get"
      onsubmit={(e) => SearchFormController.handleSubmit(e)}
    >
      <input type="hidden" name="post_type" value={WPPostType.Lowongan} />

      <!-- Search Input -->
      <div class="relative grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-start gap-2">
        <input
          type="text"
          placeholder="Masukkan Pekerjaan atau Perusahaan"
          class="input input-bordered search-input min-w-0 w-full bg-[var(--wpl-global-color-5)] sm:rounded-full"
          name="cari"
          bind:value={jobListingStore.filters.cari}
          oninput={() =>
            suggestionHandler.getSuggestions(jobListingStore.filters.cari)}
          onfocus={() => suggestionHandler.handleFocus()}
          onblur={() => suggestionHandler.hideSuggestions()}
          onkeydown={SearchFormController.handleInputKeyDown}
          disabled={jobListingStore.loading || taxonomyStore.getLoadingStatus}
          autocomplete="off"
        />
        <button
          type="submit"
          class="rounded-full btn-circle border hover:border p-2 sm:px-4 sm:py-2"
          class:opacity-75={jobListingStore.loading ||
            taxonomyStore.getLoadingStatus}
          disabled={jobListingStore.loading || taxonomyStore.getLoadingStatus}
        >
          {#if jobListingStore.loading || taxonomyStore.getLoadingStatus}
            <LoadingSpinner size="sm" srLabel="Memuat..." />
          {:else}
            <MagnifyingGlassSolid class="text-base" aria-hidden="true" />
          {/if}
          <span class="sr-only">Cari</span>
        </button>

        {#if suggestionHandler.showSuggestions && suggestionHandler.hasSuggestions}
          <div
            class="absolute left-0 sm:left-1/2 sm:-translate-x-1/2 top-full mt-2 w-full sm:w-auto sm:max-w-xs md:max-w-md z-30"
          >
            <div class="bg-[var(--wpl-global-color-5)] rounded-lg">
              <ul class="max-h-60 overflow-y-auto">
                {#each suggestionHandler.suggestions as suggestion, idx (suggestion + idx)}
                  <li>
                    <button
                      type="button"
                      class="w-full cursor-pointer break-words px-4 py-2 text-justify text-sm transition-colors"
                      onclick={async () =>
                        await SearchFormController.submitSuggestionSearch(
                          suggestion,
                        )}
                      onmouseenter={() =>
                        (suggestionHandler.selectedSuggestionIndex = idx)}
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

      <!-- Taxonomy Filters -->
      <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
        {@render taxonomyDropdownButton(
          "lokasi",
          "lokasi_pekerjaan",
          MapMarkerAltSolid,
          "Semua lokasi",
          {
            expanded: dropdownHandler.isLokasiOpen,
            controls: "lokasi-listbox",
          },
        )}

        {@render taxonomyDropdownButton(
          "gender",
          "gender",
          VenusMarsSolid,
          "Semua gender",
          {
            expanded: dropdownHandler.isGenderOpen,
            controls: "gender-listbox",
          },
        )}

        {@render taxonomyDropdownButton(
          "pendidikan",
          "pendidikan",
          GraduationCapSolid,
          "Semua pendidikan",
          {
            expanded: dropdownHandler.isPendidikanOpen,
            controls: "pendidikan-listbox",
          },
        )}

        <!-- Sort Dropdown (not a taxonomy but shares similar UI), not included in taxonomyDropdownButton -->
        <div class="relative min-w-0">
          {#if jobListingStore.filters.sort?.value === "asc"}
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
            class="min-h-12 h-auto w-full min-w-0 rounded-full border bg-[var(--wpl-global-color-5)] px-4 py-3 text-left"
            aria-expanded={dropdownHandler.isSortOpen}
            aria-controls="sort-listbox"
            onclick={() => dropdownHandler.toggleDropdown("sort")}
            ><span
              class="block min-w-0 break-words pl-8 leading-tight"
              >{jobListingStore.filters.sort?.label ?? "Urutkan"}</span
            ></button
          >
          {#if dropdownHandler.isSortOpen}
            {@const CustomDropdown =
              componentRegistry.getComponentByName("CustomDropdown")}
            <CustomDropdown
              id="sort"
              value={jobListingStore.filters.sort}
              update={(payload: unknown) => {
                dropdownHandler.updateSortFilter(payload);
              }}
              options={sortOptions}
              multiple={false}
              open={dropdownHandler.isSortOpen}
              close={() => (dropdownHandler.isSortOpen = false)}
            />
          {/if}
        </div>
      </div>

      {#if dropdownHandler.selectedFiltersWithNames && dropdownHandler.selectedFiltersWithNames.length}
        <div class="mb-4 flex min-w-0 flex-wrap items-start gap-2 animate-fade-in">
          <span
            class="font-semibold text-[var(--wpl-global-color-1)] flex items-center justify-center w-full mr-2"
            ><FilterSolid class="mr-1 inline-block" aria-hidden="true" />Filter
            aktif:</span
          >
          {#each dropdownHandler.selectedFiltersWithNames as filter (filter.key)}
            {#each filter.values as val, idx (val + idx)}
              <span
                class="grid w-full min-w-0 max-w-full grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-2 rounded-2xl bg-[var(--wpl-global-color-5)] px-3 py-2 text-left shadow-sm transition-all duration-150 sm:w-auto"
              >
                {#if filter.key === "lokasi_pekerjaan"}
                  <MapMarkerAltSolid
                    class="mt-0.5 shrink-0 text-[var(--wpl-global-color-1)]"
                    aria-hidden="true"
                  />
                {:else if filter.key === "gender"}
                  <VenusMarsSolid
                    class="mt-0.5 shrink-0 text-pink-500"
                    aria-hidden="true"
                  />
                {:else}
                  <GraduationCapSolid
                    class="mt-0.5 shrink-0 text-green-500"
                    aria-hidden="true"
                  />
                {/if}
                <span
                  class="min-w-0 break-words leading-tight"
                  >{filter.names[idx]}</span
                >
                <button
                  type="button"
                  class="btn btn-ghost btn-xs btn-circle self-start text-[var(--wpl-global-color-1)] transition-colors duration-150 hover:text-red-600"
                  onclick={() =>
                    dropdownHandler.clearDropdownFilter(filter.key, val)}
                  aria-label="Hapus filter"
                >
                  <XmarkSolid class="text-xs" aria-hidden="true" />
                </button>
              </span>
            {/each}
          {/each}
        </div>
      {/if}

      {#if jobListingStore.context === "search"}
        <div class="flex justify-end mt-2">
          <button
            type="button"
            class="btn btn-outline rounded-full bg-[var(--wpl-global-color-5)] hover:border-[var(--wpl-global-color-1)]"
            disabled={jobListingStore.loading || taxonomyStore.getLoadingStatus}
            onclick={() => SearchFormController.resetFiltersAndSearch()}
          >
            <RotateLeftSolid class="mr-2" aria-hidden="true" />Reset Filter
          </button>
        </div>
      {/if}

      {#if jobListingStore.error || taxonomyStore.anyError}
        <div class="alert alert-error">
          <TriangleExclamationSolid
            class="mr-2 inline-block text-red-600"
            aria-hidden="true"
          />
          <span>{jobListingStore.error || taxonomyStore.anyError}</span>
        </div>
      {/if}

      {#if jobListingStore.loading || suggestionHandler.suggestionsLoading}
        <div class="text-center py-4 flex flex-col items-center justify-center">
          <LoadingSpinner srLabel="Memuat..." size="md" />
          <span class="mt-2"
            >{suggestionHandler.suggestionsLoading
              ? "Mencari saran..."
              : "Mencari..."}</span
          >
        </div>
      {/if}

      {#if jobListingStore.recentSearches && jobListingStore.recentSearches.length}
        <div class="mt-4">
          <span class="text-lg font-semibold"> Pencarian Terakhir: </span>
          <div class="flex flex-wrap font-semibold gap-2 mt-4">
            {#each jobListingStore.recentSearches as item (item)}
              <button
                type="button"
                class="px-3 py-1 text-xs bg-[var(--wpl-global-color-5)] hover:bg-[var(--wpl-global-color-7)] rounded-full"
                onclick={() => jobListingStore.setFilters({ cari: item })}
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
