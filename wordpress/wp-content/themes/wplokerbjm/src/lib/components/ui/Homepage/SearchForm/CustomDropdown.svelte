<script lang="ts">
  import { taxonomyStore } from "$lib/stores/Taxonomy.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import {
    SitemapSolid,
    FolderOpenSolid,
    FolderSolid,
    FileSolid,
    ChevronRightSolid,
    ArrowLeftSolid,
    TrashAltSolid,
  } from "svelte-awesome-icons";
  import type { Attachment } from "svelte/attachments";
  import type { DropdownOption, KeyboardKeysEvent } from "@/types";

  type DropdownSelectionValue = string | DropdownOption;
  type ValueProp =
    | DropdownSelectionValue
    | DropdownSelectionValue[]
    | undefined
    | null;
  type HighlightPart = { text: string; match: boolean };
  type SelectedItem = { value: string; label: string };

  interface Props {
    id?: string;
    value?: ValueProp;
    options?: DropdownOption[];
    multiple?: boolean;
    open?: boolean;
    update?: (payload: unknown) => void;
    close?: () => void;
  }

  let {
    id,
    value = undefined,
    options = [],
    multiple = false,
    open = false,
    update = undefined,
    close = undefined,
  }: Props = $props();

  const listboxId = $derived(String(id ?? "custom-dropdown") + "-listbox");
  let activeOptionIndex = $state(0); // Tracks the zero-based index of the currently highlighted option for keyboard navigation
  /**
   * Manages the dropdown's option catalog, including the current navigation stack,
   * selected items, and search query.
   */
  class CatalogHandler {
    public optionStack = $state<DropdownOption[][]>([]);
    public selectedItems = $derived(
      this.resolveSelectedItems(value, options ?? []),
    );
    public searchQuery = $state("");
    public normalizedSearchQuery = $derived(String(this.searchQuery).trim());

    /**
     * Computes the list of options to display based on the current search query.
     */
    #displayedOptions = $derived.by<DropdownOption[]>(() => {
      if (this.normalizedSearchQuery) {
        const loweredQuery = this.normalizedSearchQuery.toLowerCase();
        return this.flattenOptions(options ?? []).filter((option) =>
          option.label.toLowerCase().includes(loweredQuery),
        );
      }

      // When not searching, the displayed options are simply the current level's options
      return (this.#currentLevelOptions ?? []).map((option) => ({
        ...option,
        key: this.buildOptionKey(option, breadcrumbHandler.breadcrumbTrail),
      }));
    });

    /**
     * Computes the list of selectable options by filtering out options
     * with empty string values. This is used to determine which options
     * can be interacted with in the UI.
     */
    public selectableOptions: DropdownOption[] = $derived(
      this.#displayedOptions.filter(
        (option) => String(option.value).trim() !== "",
      ),
    );

    /**
     * Computes the current list of options to display based on the navigation stack.
     * If the user has drilled into one or more levels, returns the children of
     * the current level (last entry in `optionStack`); otherwise returns the
     * root-level options passed via props.
     */
    #currentLevelOptions = $derived(
      this.optionStack.length > 0
        ? this.optionStack[this.optionStack.length - 1]
        : (options ?? []),
    );

    /**
     * Computes the next multi-select value array after toggling the given option.
     * If the value is already selected it is removed; otherwise it is appended.
     * Returns the new array — it does NOT mutate state directly; the caller
     * @see InteractionController.toggleMultipleSelection is responsible
     * for passing the result to @see update().
     * @param optionValue The string value of the option being toggled.
     * @returns The updated array of selected string values.
     */
    public toggleSelectedValue(optionValue: string): string[] {
      const currentValues = this.normalizeSelectedValues(value);
      const hasValue = currentValues.includes(optionValue);

      return hasValue
        ? currentValues.filter((item) => item !== optionValue)
        : [...currentValues, optionValue];
    }

    /**
     * Checks whether a given option value is currently selected, based on
     * the resolved `selectedItems` derived list.
     * Used by the template to apply the checked/bold state on each list item.
     * @param optionValue The string value to check.
     * @returns `true` if the option is in the current selection.
     */
    public isSelected(optionValue: string): boolean {
      return this.selectedItems.some((item) => item.value === optionValue);
    }

    /**
     * Splits an option label into an ordered array of text segments indicating
     * which parts match the current search query (case-insensitive).
     * The template uses this to wrap matching segments in a `<span>` for visual
     * highlight without relying on `{@html}` and innerHTML injection.
     * @param label The full option label text to split.
     * @param query The raw (unescaped) search string to match against.
     * @returns An array of `{ text, match }` segments in order of appearance.
     */
    public highlightParts(label: string, query: string): HighlightPart[] {
      if (!query) return [{ text: label, match: false }];

      const parts: HighlightPart[] = [];
      const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      const matcher = new RegExp(escapedQuery, "gi");
      let currentIndex = 0;
      let match: RegExpExecArray | null;

      while ((match = matcher.exec(label))) {
        const matchIndex = match.index;

        if (matchIndex > currentIndex) {
          parts.push({
            text: label.slice(currentIndex, matchIndex),
            match: false,
          });
        }

        parts.push({ text: match[0], match: true });
        currentIndex = matcher.lastIndex;
      }

      if (currentIndex < label.length) {
        parts.push({ text: label.slice(currentIndex), match: false });
      }

      return parts.length ? parts : [{ text: label, match: false }];
    }

    /**
     * Builds a stable, unique key for an option by joining the current breadcrumb path
     * with the option's own value, separated by `>`.
     * Used as the Svelte `{#each}` key to avoid unnecessary DOM reconciliation when
     * the user navigates levels or searches.
     * @param option The option whose key should be built.
     * @param breadcrumbs The ancestor label path leading to this option.
     * @returns A `>`-delimited string key, e.g. `"Jawa Barat>Bandung"`.
     */
    private buildOptionKey(
      option: Pick<DropdownOption, "value">,
      breadcrumbs: string[] = [],
    ): string {
      return [...breadcrumbs, String(option.value)].join(">");
    }

    /**
     * Recursively flattens a hierarchical option tree into a single-level array.
     * Each entry carries a `breadcrumbs` array (ancestor labels) and a `key`
     * for stable rendering. Children are stripped from the flattened copy so
     * the flat list does not carry duplicate subtrees.
     * Used by the search-mode view to find and display matching options from any level in the tree.
     * @param optionsList The root or subtree of options to flatten.
     * @param breadcrumbs Accumulated ancestor labels passed down during recursion.
     * @returns A flat array of `DropdownOption` entries.
     */
    private flattenOptions(
      optionsList: DropdownOption[],
      breadcrumbs: string[] = [],
    ): DropdownOption[] {
      return (optionsList ?? []).flatMap((option): DropdownOption[] => {
        if (!option) return [];

        const flattenedOption: DropdownOption = {
          ...option,
          breadcrumbs,
          key: this.buildOptionKey(option, breadcrumbs),
          children: undefined,
        };

        const nestedOptions = option.children?.length
          ? this.flattenOptions(option.children, [...breadcrumbs, option.label])
          : [];

        return [flattenedOption, ...nestedOptions];
      });
    }

    /**
     * Coerces a raw selection value (string or `DropdownOption`) into a plain string.
     * Used internally to normalise heterogeneous value entries before comparison.
     * @param value A string or a DropdownOption object.
     * @returns The string representation of the value.
     */
    private toSelectionValue(value: DropdownSelectionValue): string {
      return typeof value === "string" ? value : String(value.value);
    }

    /**
     * Normalises the component's `value` prop into a deduplicated array of non-empty strings.
     * Handles all accepted input shapes: `undefined`, `null`, a single string/DropdownOption,
     * or an array of those types.
     * @param valueProp The raw value received from the parent.
     * @returns An array of non-empty string values.
     */
    private normalizeSelectedValues(valueProp: ValueProp): string[] {
      if (valueProp === undefined || valueProp === null) return [];

      if (Array.isArray(valueProp)) {
        return valueProp
          .map((entry) => this.toSelectionValue(entry))
          .filter((entry) => entry.trim() !== "");
      }

      const normalizedValue = this.toSelectionValue(valueProp);
      return normalizedValue.trim() ? [normalizedValue] : [];
    }

    /**
     * Resolves the current `value` prop into display-ready `{ value, label }` pairs
     * by looking up each selected string in the full (flattened) option tree.
     * Falls back to using the raw value string as the label when no matching option
     * is found (e.g. stale persisted values).
     * @param valueProp The raw value prop from the parent component.
     * @param optionsList The full unfiltered options tree to search in.
     * @returns An array of `{ value, label }` objects for the currently selected items.
     */
    private resolveSelectedItems(
      valueProp: ValueProp,
      optionsList: DropdownOption[],
    ): SelectedItem[] {
      const selectedValues = this.normalizeSelectedValues(valueProp);
      const flattenedOptions = this.flattenOptions(optionsList ?? []);

      return selectedValues.map((selectedValue) => {
        const matchedOption =
          flattenedOptions.find((option) => option.value === selectedValue) ??
          optionsList?.find((option) => option.value === selectedValue);

        return matchedOption
          ? {
              value: String(matchedOption.value),
              label: String(matchedOption.label),
            }
          : { value: selectedValue, label: selectedValue };
      });
    }
  }

  /**
   * Manages navigation state for the hierarchical option tree:
   * the option-level stack (`optionStack`) and the human-readable breadcrumb
   * trail (`breadcrumbTrail`). Each level deeper pushes onto both stacks;
   * going back or jumping to a breadcrumb node pops/splices them.
   */
  class BreadcrumbHandler {
    breadcrumbTrail = $state<string[]>([]);

    /**
     * Fully resets the navigator back to the root level.
     * Clears the option stack, breadcrumb trail, active highlight index, and
     * the search query. Called after a single-select confirmation so the
     * dropdown opens fresh on the next interaction.
     */
    public reset(): void {
      catalogHandler.optionStack.length = 0;
      this.breadcrumbTrail.length = 0;
      activeOptionIndex = 0;
      catalogHandler.searchQuery = "";
    }

    /**
     * Resets the keyboard-highlighted option index back to the first item (0).
     * Called whenever the visible option list changes (level navigation, search
     * input) so the highlight does not point to a stale position.
     */
    public resetActiveIndex(): void {
      activeOptionIndex = 0;
    }

    /**
     * Drills into the children of the given option, lazily fetching them first
     * if a `loadChildren` callback is provided and no children have been loaded yet.
     * Pushes the children array onto `optionStack` and appends the parent's label
     * to `breadcrumbTrail`, then resets the active index.
     * The `isLoading` flag on the option is toggled for the duration of the async
     * fetch so the UI can show a loading indicator inline.
     * @param option The option whose children should be opened.
     */
    public async openChildren(option: DropdownOption): Promise<void> {
      if (
        option.loadChildren &&
        (!option.children || !option.children.length)
      ) {
        option.isLoading = true;

        try {
          option.children = (await option.loadChildren()) ?? [];
        } finally {
          option.isLoading = false;
        }
      }

      catalogHandler.optionStack.push(option.children ?? []);

      if (option.label) {
        this.breadcrumbTrail.push(option.label);
      }

      this.resetActiveIndex();
    }

    /**
     * Navigates one level up in the option tree by popping the last entry from
     * both `optionStack` and `breadcrumbTrail`. No-ops if already at root level.
     * Resets the active index so the highlight returns to the top of the parent list.
     */
    public goBack(): void {
      if (!catalogHandler.optionStack.length) return;
      catalogHandler.optionStack.pop();
      this.breadcrumbTrail.pop();
      this.resetActiveIndex();
    }

    /**
     * Jumps directly to an ancestor level in the breadcrumb trail by splicing
     * both `optionStack` and `breadcrumbTrail` down to `index + 1` entries.
     * Used when the user clicks a breadcrumb node to skip multiple levels at once.
     * @param index The zero-based breadcrumb index to navigate to.
     */
    public goTo(index: number): void {
      catalogHandler.optionStack.splice(index + 1);
      this.breadcrumbTrail.splice(index + 1);
      this.resetActiveIndex();
    }
  }

  /**
   * Wires together user interactions (keyboard, mouse, focus) with the
   * * `update` / `close` prop callbacks.
   * centralize both handlers event interaction to avoid circular dependencies.
   */
  class InteractionController {
    #isKeyboardNavigation = $state(false);
    public optionElements = $state<HTMLElement[]>([]);
    public dropdownRoot: HTMLElement | null = null;

    /**
     * Clears the entire selection by calling `update` with an empty array.
     * Triggered by the "Hapus filter" (clear filter) button.
     */
    public clearSelection(): void {
      this.callUpdate([]);
    }

    /**
     * Toggles a single option in the multi-select list and propagates the
     * updated array to the parent via `update`.
     * Delegates the add/remove logic to `DropdownOptionCatalog.toggleSelectedValue`.
     * @param optionValue The string value of the option to toggle.
     */
    public toggleMultipleSelection(optionValue: string): void {
      this.callUpdate(catalogHandler.toggleSelectedValue(optionValue));
    }

    /**
     * Handles a definitive selection of an option.
     * - In multi-select mode: delegates to `toggleMultipleSelection` and keeps the
     *   dropdown open.
     * - In single-select mode: propagates the value via `update`, closes the
     *   dropdown via `close`, and resets the navigator back to root level.
     * @param option The option that was activated (clicked or Enter-pressed).
     */
    public select(option: DropdownOption): void {
      if (multiple) {
        this.toggleMultipleSelection(String(option.value));
        return;
      }

      this.callUpdate(String(option.value));
      this.callClose();
      breadcrumbHandler.reset();
    }

    /**
     * Body-level `mousedown` handler that closes the dropdown when the user
     * clicks outside the dropdown root element.
     * Attached via `<svelte:body onmousedown={...}>` so it covers the entire page.
     * @param event The native MouseEvent from the document.
     */
    public handleMouseDown = (event: MouseEvent) => {
      if (!open) return;
      if (
        this.dropdownRoot &&
        !this.dropdownRoot.contains(event.target as Node)
      ) {
        this.callClose();
      }
    };

    /**
     * `focusout` handler on the combobox root that closes the dropdown when
     * focus leaves the entire dropdown subtree (i.e. `relatedTarget` is outside
     * `dropdownRoot`). This covers keyboard Tab-out scenarios that `mousedown`
     * alone would not catch.
     * @param event The native FocusEvent.
     */
    public handleFocusOut = (event: FocusEvent): void => {
      const relatedTarget = event.relatedTarget as Node | null;

      if (!this.dropdownRoot) return;
      if (!relatedTarget || !this.dropdownRoot.contains(relatedTarget)) {
        this.callClose();
      }
    };

    /**
     * `mouseenter` handler for individual option list items.
     * Switches the interaction mode from keyboard to mouse and updates the
     * highlighted option index so the hovered row receives the active style.
     * @param index The zero-based index of the option that the cursor entered.
     */
    public handleOptionMouseEnter(index: number): void {
      this.#isKeyboardNavigation = false;
      activeOptionIndex = index;
    }
    

    /**
     * `keydown` handler for the combobox root element.
     * Implements full ARIA-compliant keyboard navigation:
     * - `ArrowDown` / `ArrowUp`: move the active highlight through the visible list.
     * - `ArrowRight`: drill into children of the highlighted option (when not searching).
     * - `ArrowLeft`: go up one level in the breadcrumb stack (when not searching).
     * - `Enter`: confirm/select the currently highlighted option.
     * - `Escape`: close the dropdown without selecting.
     * No-ops if the dropdown is currently closed.
     * @param event The native KeyboardEvent.
     */
    public handleKeydown = (event: KeyboardEvent): void => {
      if (!open) return;

      this.#isKeyboardNavigation = true;
      const activeOption =
        catalogHandler.selectableOptions[activeOptionIndex];

      const keyHandlers: Partial<Record<KeyboardKeysEvent, () => void>> = {
        ArrowDown: () => {
          event.preventDefault();
          activeOptionIndex = Math.min(
            activeOptionIndex + 1,
            (catalogHandler.selectableOptions.length || 0) - 1,
          );
        },
        ArrowUp: () => {
          event.preventDefault();
          activeOptionIndex =
            activeOptionIndex > 0
              ? activeOptionIndex - 1
              : (catalogHandler.selectableOptions.length || 1) - 1;
        },
        ArrowRight: () => {
          event.preventDefault();
          if (
            activeOption &&
            activeOption.children?.length &&
            !catalogHandler.normalizedSearchQuery
          ) {
            void breadcrumbHandler.openChildren(activeOption);
          }
        },
        ArrowLeft: () => {
          event.preventDefault();
          if (
            breadcrumbHandler.breadcrumbTrail.length &&
            !catalogHandler.normalizedSearchQuery
          ) {
            breadcrumbHandler.goBack();
          }
        },
        Enter: () => {
          event.preventDefault();
          if (activeOption) {
            this.select(activeOption);
          }
        },
        Escape: () => {
          event.preventDefault();
          this.callClose();
        },
      };

      keyHandlers[event.key as KeyboardKeysEvent]?.();
    };

    /**
     * Svelte `{@attach}` callback used on the `<ul>` listbox element.
     * Whenever the keyboard-highlighted index changes, scrolls the corresponding
     * `<li>` into view using `scrollIntoView({ block: 'nearest' })` so the
     * active item is always visible without disrupting the scroll position when
     * the user is navigating with the mouse.
     * Returns `undefined` (no cleanup needed) when mouse navigation is active.
     * @returns `undefined` — no teardown attachment is required.
     */
    public trackActiveOption(): Attachment | undefined {
      const currentOptionIndex = activeOptionIndex;

      if (!this.#isKeyboardNavigation) return;
      if (this.optionElements.length > 0) {
        this.optionElements[currentOptionIndex]?.scrollIntoView({
          block: "nearest",
        });
      }
    }

    /**
     * Safely invokes the `update` prop callback with the given payload.
     * Swallows and logs any exception thrown by the parent's handler so
     * errors in the consumer do not propagate into the dropdown's own logic.
     * @param payload The new selection value(s) to pass to the parent.
     */
    private callUpdate(payload: unknown): void {
      try {
        update?.(payload);
      } catch (error) {
        console.error("CustomDropdown update handler error", error);
      }
    }

    /**
     * Safely invokes the `close` prop callback.
     * Swallows and logs any exception thrown by the parent's handler so
     * errors in the consumer do not propagate into the dropdown's own logic.
     */
    private callClose(): void {
      try {
        close?.();
      } catch (error) {
        console.error("CustomDropdown close handler error", error);
      }
    }
  }

  const catalogHandler = new CatalogHandler();
  const breadcrumbHandler = new BreadcrumbHandler();
  const interactionController = new InteractionController();
</script>

<svelte:body onmousedown={interactionController.handleMouseDown} />

<div
  class="relative"
  role="combobox"
  aria-haspopup="listbox"
  aria-controls={listboxId}
  aria-expanded={open}
  tabindex="0"
  onfocusout={interactionController.handleFocusOut}
  bind:this={interactionController.dropdownRoot}
  onkeydown={interactionController.handleKeydown}
>
  {#if open}
    <div
      class="absolute left-0 right-0 mt-2 rounded-lg shadow-lg z-30 pt-2 bg-[var(--wpl-global-color-5)] transition-all duration-300"
    >
      <!-- Search input (static header above scrollable options) -->
      <div class="px-5 py-2 bg-[var(--wpl-global-color-5)]">
        <input
          bind:value={catalogHandler.searchQuery}
          type="text"
          class="input input-sm input-bordered w-full search-input bg-transparent"
          placeholder="Cari..."
          onkeydown={(event) => event.stopPropagation()}
          oninput={() => breadcrumbHandler.resetActiveIndex()}
        />
      </div>

      <!-- Taxonomy loading shown inside dropdown to avoid parent re-renders -->
      {#if taxonomyStore.getLoadingStatus}
        <div class="px-5 py-2 text-center text-sm text-gray-500">
          <div class="inline-flex items-center justify-center">
            <LoadingSpinner size="sm" srLabel="Memuat..." />
            <span class="ml-2">Memuat data...</span>
          </div>
        </div>
      {/if}

      <!-- Breadcrumb + Clear filter & Back button combined -->
      {#if breadcrumbHandler.breadcrumbTrail.length && !catalogHandler.normalizedSearchQuery}
        <div
          class="rounded-t bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)]"
        >
          <!-- breadcrumb header (static under search) -->
          <div
            class="px-5 py-2 pb-2 flex items-center text-sm text-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)] z-40"
          >
            <div class="flex items-center min-w-0 overflow-x-auto pb-2">
              <SitemapSolid class="mr-3 shrink-0" aria-hidden="true" />
              {#each breadcrumbHandler.breadcrumbTrail as crumb, idx (crumb + idx)}
                {#if idx < breadcrumbHandler.breadcrumbTrail.length - 1}
                  <button
                    type="button"
                    class="cursor-pointer hover:underline font-medium flex items-center whitespace-nowrap"
                    onmousedown={(event) => {
                      event.preventDefault();
                      event.stopPropagation();
                      breadcrumbHandler.goTo(idx);
                    }}
                  >
                    <FolderOpenSolid
                      class="mr-1 text-yellow-500"
                      aria-hidden="true"
                    />{crumb}
                  </button>
                  <span class="mx-1 shrink-0"
                    ><ChevronRightSolid aria-hidden="true" /></span
                  >
                {:else}
                  <span
                    class="font-bold flex items-center text-[var(--wpl-global-color-1)] whitespace-nowrap"
                    ><FolderSolid
                      class="mr-1"
                      aria-hidden="true"
                    />{crumb}</span
                  >
                {/if}
              {/each}
            </div>
          </div>

          <!-- controls (static below breadcrumb) -->
          <div
            class="flex justify-between items-center px-5 py-2 z-10 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] bg-opacity-100 border-t"
          >
            <!-- Back button (always available when breadcrumb is present) -->
            <button
              type="button"
              class="dropdown-btn group"
              onmousedown={(event) => {
                event.preventDefault();
                event.stopPropagation();
                breadcrumbHandler.goBack();
              }}
              tabindex="-1"
            >
              <ArrowLeftSolid
                class="text-xs no-underline mr-2"
                aria-hidden="true"
              />
              <span class="group-hover:underline">Kembali</span>
            </button>

            {#if multiple && catalogHandler.selectedItems.length > 0}
              <button
                type="button"
                class="dropdown-btn hover:underline border rounded-full"
                onclick={() => interactionController.clearSelection()}
              >
                <TrashAltSolid class="mr-2" aria-hidden="true" />Hapus filter
              </button>
            {/if}
          </div>
        </div>
      {:else if multiple && catalogHandler.selectedItems.length > 0}
        <div
          class="flex justify-end items-center px-5 py-1 z-40 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] bg-opacity-100 border-t"
        >
          <button
            type="button"
            class="dropdown-btn hover:underline border rounded-full"
            onclick={() => interactionController.clearSelection()}
          >
            <TrashAltSolid class="mr-2" aria-hidden="true" />Hapus filter
          </button>
        </div>
      {/if}

      <!-- Options list (scrollable) -->
      <div class="max-h-96 overflow-y-auto pt-2">
        <ul
          {@attach interactionController.trackActiveOption()}
          id={listboxId}
          role="listbox"
          class="!pt-0 !pb-2"
        >
          {#each catalogHandler.selectableOptions as option, index (option.key)}
            <li
              bind:this={interactionController.optionElements[index]}
              class={[
                "flex items-center px-5 py-2 cursor-pointer select-none transition rounded text-left",
                index === activeOptionIndex
                  ? "bg-[var(--wpl-global-color-1)]/15"
                  : "",
                catalogHandler.isSelected(option.value) ? "font-bold" : "",
              ].join(" ")}
            >
              <button
                type="button"
                class="flex-1 text-left flex items-center min-w-0 pr-12 break-words whitespace-normal"
                onmouseenter={() =>
                  interactionController.handleOptionMouseEnter(index)}
                onclick={() =>
                  !multiple && interactionController.select(option)}
              >
                {#if multiple}
                  <label class="flex items-center cursor-pointer mr-3"
                    ><input
                      type="checkbox"
                      class="checkbox checkbox-sm checkbox-primary"
                      checked={catalogHandler.isSelected(option.value)}
                      onchange={() =>
                        interactionController.toggleMultipleSelection(
                          String(option.value),
                        )}
                      tabindex="-1"
                    /></label
                  >
                {/if}
                {#if option.children?.length}
                  <FolderSolid
                    class="mr-2 flex-shrink-0 text-yellow-400"
                    aria-hidden="true"
                  />
                {:else}
                  <FileSolid
                    class="mr-2 flex-shrink-0 text-gray-400"
                    aria-hidden="true"
                  />
                {/if}
                {#if catalogHandler.normalizedSearchQuery}
                  {#each catalogHandler.highlightParts(option.label, catalogHandler.normalizedSearchQuery) as part, partIndex (part.text + part.match + partIndex)}
                    {#if part.match}
                      <span
                        class="bg-[var(--wpl-global-color-5)] font-bold rounded px-1"
                        >{part.text}</span
                      >
                    {:else}
                      {part.text}
                    {/if}
                  {/each}
                {:else}
                  {option.label}
                {/if}
                {#if option.breadcrumbs && catalogHandler.searchQuery}
                  <span class="ml-2 text-xs text-gray-400 italic"
                    >({option.breadcrumbs.join(" / ")})</span
                  >
                {/if}
                {#if option.isLoading}
                  <span
                    class="ml-2 text-xs text-gray-400 italic flex items-center"
                  >
                    <LoadingSpinner size="sm" srLabel="Memuat..." />
                    <span class="ml-2">Memuat...</span>
                  </span>
                {/if}
              </button>
              {#if option.children?.length && !catalogHandler.normalizedSearchQuery}
                <button
                  class="ml-2 flex items-center justify-center w-10 h-10 rounded relative transition"
                  onclick={(event) => {
                    // stop propagation so the parent row's click/select handler does not run
                    event.stopPropagation();
                    void breadcrumbHandler.openChildren(option);
                  }}
                  onmousedown={(event) => {
                    // prevent mousedown from triggering click outside handler
                    event.preventDefault();
                    event.stopPropagation();
                  }}
                  tabindex="-1"
                  aria-label="Lihat sub"
                >
                  <span
                    class="absolute left-6 -top-1 translate-y-1 bg-[var(--wpl-global-color-1)] text-[var(--wpl-global-color-5)] text-xs rounded-full px-1.5 py-0.1 z-20"
                    >{option.children.length}</span
                  >
                  <ChevronRightSolid
                    class="border border-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] rounded-full text-2xl p-1 h-10 w-10"
                    aria-hidden="true"
                  />
                </button>
              {/if}
            </li>
          {/each}
        </ul>
        {#if catalogHandler.selectableOptions.length === 0 && !taxonomyStore.loading}
          <div class="px-5 py-2 text-gray-400 text-center">Tidak ada hasil</div>
        {/if}
      </div>
    </div>
  {/if}
</div>

<style lang="postcss">
  @reference "@css/app.css";
  .dropdown-btn {
    @apply flex items-center px-2 p-1 border rounded-full text-sm font-medium;
  }
</style>
