<script module lang="ts">
  import type { DropdownOption } from "@/types";
  import { SearchUtils } from "@/utils/search";

  type DropdownSelectionValue = string | DropdownOption;
  type ValueProp =
    | DropdownSelectionValue
    | DropdownSelectionValue[]
    | undefined
    | null;
  type UpdatePayload = string | string[] | undefined | null;
  type HighlightPart = { text: string; match: boolean };
  type SelectedItem = { value: string; label: string };
  type BreadcrumbDropdownOption = DropdownOption & {
    __breadcrumbs?: string[];
    __key?: string;
  };

  type SupportedKey =
    | "ArrowDown"
    | "ArrowUp"
    | "ArrowRight"
    | "ArrowLeft"
    | "Enter"
    | "Escape";

  interface Props {
    id?: string;
    value?: ValueProp;
    options?: DropdownOption[];
    multiple?: boolean;
    open?: boolean;
    update?: (payload: UpdatePayload) => void;
    close?: () => void;
  }

  class CustomDropdownHelpers {
    static highlightParts = (label: string, query: string): HighlightPart[] => {
      const parts: HighlightPart[] = [];
      if (!query) return [{ text: label, match: false }];
      const escapeRegex = (s: string) =>
        s.replace(/[.*+?^${}()|[\\]\\]/g, "\\$&");
      const regex = new RegExp(escapeRegex(query), "gi");
      let lastIndex = 0;
      let m: RegExpExecArray | null;
      while ((m = regex.exec(label))) {
        const idx = m.index;
        if (idx > lastIndex) {
          parts.push({ text: label.slice(lastIndex, idx), match: false });
        }
        parts.push({ text: m[0], match: true });
        lastIndex = regex.lastIndex;
      }
      if (lastIndex < label.length) {
        parts.push({ text: label.slice(lastIndex), match: false });
      }
      return parts.length ? parts : [{ text: label, match: false }];
    };

    static flattenOptions(
      optionsList: DropdownOption[],
      breadcrumbs: string[] = [],
    ): BreadcrumbDropdownOption[] {
      return (optionsList ?? []).flatMap((opt): BreadcrumbDropdownOption[] => {
        if (!opt) return [];
        const key = [opt.value, ...breadcrumbs].join(">");
        const baseOption: BreadcrumbDropdownOption = {
          ...opt,
          __breadcrumbs: breadcrumbs,
          __key: key,
          children: undefined,
        };
        const nested: BreadcrumbDropdownOption[] = opt.children?.length
          ? CustomDropdownHelpers.flattenOptions(opt.children, [
              ...breadcrumbs,
              opt.label,
            ])
          : [];
        return [baseOption, ...nested];
      });
    }

    static flattenOptionsToList(
      options: DropdownOption[],
    ): BreadcrumbDropdownOption[] {
      return CustomDropdownHelpers.flattenOptions(options ?? []);
    }

    static resolveSelectedValues(
      value: ValueProp,
      options: DropdownOption[],
    ): SelectedItem[] {
      const valArr = SearchUtils.normalizeStringOrArray(
        value as string | string[] | null,
      );
      const flat = CustomDropdownHelpers.flattenOptionsToList(options ?? []);
      return valArr.map((selectedValue) => {
        const found =
          flat.find((option) => option.value === selectedValue) ??
          options?.find((option) => option.value === selectedValue);
        return found
          ? { value: String(found.value), label: String(found.label) }
          : { value: selectedValue, label: selectedValue };
      });
    }

    static isSelected(v: string, selectedValues: SelectedItem[]): boolean {
      return selectedValues.some((item) => item.value === v);
    }

    static toggleValueLocal(
      v: string,
      value: ValueProp,
      multiple: boolean,
    ): string[] {
      const arr = SearchUtils.normalizeStringOrArray(
        value as string | string[] | null,
      );
      const exists = arr.includes(v);
      if (!multiple) return arr;
      return exists ? arr.filter((item) => item !== v) : [...arr, v];
    }
  }

  class CustomDropdownBreadcrumbController {
    static async navigateChildren(
      children: DropdownOption[] | undefined,
      label: string | undefined,
      parentOption: DropdownOption | undefined,
      stack: DropdownOption[][],
      breadcrumbLabels: string[],
    ): Promise<void> {
      if (parentOption?.loadChildren && (!children || !children.length)) {
        parentOption.isLoading = true;
        try {
          parentOption.children = (await parentOption.loadChildren()) ?? [];
        } finally {
          parentOption.isLoading = false;
        }
        stack.push(parentOption.children);
      } else {
        stack.push(children ?? []);
      }
      if (label) breadcrumbLabels.push(label);
    }

    static goBack(stack: DropdownOption[][], breadcrumbLabels: string[]): void {
      if (stack.length) {
        stack.pop();
        breadcrumbLabels.pop();
      }
    }

    static goToBreadcrumb(
      idx: number,
      stack: DropdownOption[][],
      breadcrumbLabels: string[],
    ): void {
      stack.splice(idx + 1);
      breadcrumbLabels.splice(idx + 1);
    }

    static resetBreadcrumb(
      stack: DropdownOption[][],
      breadcrumbLabels: string[],
    ): void {
      stack.length = 0;
      breadcrumbLabels.length = 0;
    }
  }

  class CustomDropdownController {
    static callUpdate(
      payload: UpdatePayload,
      update?: (payload: UpdatePayload) => void,
    ) {
      try {
        if (typeof update === "function") {
          update(payload);
        }
      } catch (err) {
        console.error("CustomDropdown update handler error", err);
      }
    }

    static callClose(close?: () => void) {
      try {
        if (typeof close === "function") {
          close();
          return;
        }
      } catch (err) {
        console.error("CustomDropdown close handler error", err);
      }
    }

    static select(
      option: DropdownOption,
      multiple: boolean,
      value: ValueProp,
      update?: (payload: UpdatePayload) => void,
      close?: () => void,
      resetBreadcrumb?: () => void,
    ): void {
      if (multiple) {
        CustomDropdownController.callUpdate(
          CustomDropdownHelpers.toggleValueLocal(option.value, value, multiple),
          update,
        );
      } else {
        CustomDropdownController.callUpdate(String(option.value), update);
        CustomDropdownController.callClose(close);
        if (resetBreadcrumb) resetBreadcrumb();
      }
    }

    static clearFilters(update?: (payload: UpdatePayload) => void): void {
      CustomDropdownController.callUpdate([], update);
    }

    static handleClickOutside(
      e: MouseEvent,
      dropdownRef: HTMLElement | null,
      isOpen: boolean,
      close?: () => void,
    ): void {
      if (!isOpen) return;
      if (dropdownRef && !dropdownRef.contains(e.target as Node)) {
        CustomDropdownController.callClose(close);
      }
    }

    static handleFocusOut(
      e: FocusEvent,
      dropdownRef: HTMLElement | null,
      close?: () => void,
    ): void {
      const related = e.relatedTarget as Node | null;
      if (!dropdownRef) return;
      if (!related || !dropdownRef.contains(related)) {
        CustomDropdownController.callClose(close);
      }
    }
  }
</script>

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

  let {
    id,
    value = undefined,
    options = [],
    multiple = false,
    open = false,
    update = undefined,
    close = undefined,
  }: Props = $props();

  let search = $state("");
  const breadcrumbLabels = $state<string[]>([]);
  const stack = $state<DropdownOption[][]>([]);
  let activeIndex = $state(0);
  let isKeyboard = $state(false);

  // DOM ref
  let dropdownRef: HTMLElement | null = null;
  let listboxEl = $state<HTMLElement | null>(null);
  const listboxId = $derived(String(id ?? "custom-dropdown") + "-listbox");

  const normalizedSearch = $derived(String(search).trim());

  const currentOptions = $derived(
    stack.length > 0 ? stack[stack.length - 1] : (options ?? []),
  );

  const filteredOptions = $derived.by((): BreadcrumbDropdownOption[] => {
    if (normalizedSearch) {
      const q = normalizedSearch.toLowerCase();
      return CustomDropdownHelpers.flattenOptions(options ?? []).filter((opt) =>
        opt.label.toLowerCase().includes(q),
      );
    }
    return (currentOptions ?? []).map(
      (opt): BreadcrumbDropdownOption => ({
        ...opt,
        __key: String(opt.value) + (breadcrumbLabels.join(">") || ""),
      }),
    );
  });

  const filteredNonEmpty = $derived<BreadcrumbDropdownOption[]>(
    (filteredOptions ?? []).filter((opt) => String(opt.value).trim() !== ""),
  );

  const selectedValues = $derived.by((): SelectedItem[] =>
    CustomDropdownHelpers.resolveSelectedValues(value, options),
  );

  function resetNavigationState(): void {
    CustomDropdownBreadcrumbController.resetBreadcrumb(stack, breadcrumbLabels);
    activeIndex = 0;
    search = "";
  }

  const onKeyDown = (e: KeyboardEvent): void => {
    if (!open) return;
    isKeyboard = true;
    const list = filteredNonEmpty ?? [];

    const keyHandlers: Partial<Record<SupportedKey, () => void>> = {
      ArrowDown: () => {
        activeIndex = Math.min((activeIndex ?? 0) + 1, (list.length || 0) - 1);
      },
      ArrowUp: () => {
        activeIndex =
          (activeIndex ?? 0) > 0
            ? (activeIndex ?? 0) - 1
            : (list.length || 1) - 1;
      },
      ArrowRight: () => {
        const opt = list[activeIndex];
        if (opt && opt.children?.length && !normalizedSearch) {
          CustomDropdownBreadcrumbController.navigateChildren(
            opt.children,
            opt.label,
            opt,
            stack,
            breadcrumbLabels,
          );
          activeIndex = 0;
        }
      },
      ArrowLeft: () => {
        if (breadcrumbLabels.length && !normalizedSearch) {
          CustomDropdownBreadcrumbController.goBack(stack, breadcrumbLabels);
          activeIndex = 0;
        }
      },
      Enter: () => {
        const opt = list[activeIndex];
        if (opt)
          CustomDropdownController.select(
            opt,
            multiple,
            value,
            update,
            close,
            resetNavigationState,
          );
      },
      Escape: () => CustomDropdownController.callClose(close),
    };
    e.preventDefault();
    keyHandlers[e.key as SupportedKey]?.();
  };

  $effect(() => {
    // Explicit dependency tracking for scroll behavior
    const currentActiveIndex = activeIndex;
    const isKeyboardNav = isKeyboard;

    if (!isKeyboardNav) return;
    const el = listboxEl;
    if (!el) return;
    try {
      const items = el.querySelectorAll("li");
      const node = items[currentActiveIndex] as HTMLElement | undefined;
      if (node) node.scrollIntoView({ block: "nearest" });
    } catch (e) {
      void e;
    }
  });
</script>

<svelte:document
  on:mousedown={(e) => {
    CustomDropdownController.handleClickOutside(e, dropdownRef, open, close);
  }}
/>

<div
  class="relative"
  role="combobox"
  aria-haspopup="listbox"
  aria-controls={listboxId}
  aria-expanded={open}
  tabindex="0"
  onfocusout={(e) =>
    CustomDropdownController.handleFocusOut(e, dropdownRef, close)}
  bind:this={dropdownRef}
  onkeydown={onKeyDown}
>
  {#if open}
    <div
      class="absolute left-0 right-0 mt-2 rounded-lg shadow-lg z-30 pt-2 bg-[var(--wpl-global-color-5)] transition-all duration-300"
    >
      <!-- Search input (static header above scrollable options) -->
      <div class="px-5 py-2 bg-[var(--wpl-global-color-5)]">
        <input
          bind:value={search}
          type="text"
          class="input input-sm input-bordered w-full search-input bg-transparent"
          placeholder="Cari..."
          onkeydown={(e) => e.stopPropagation()}
          oninput={() => {
            activeIndex = 0;
          }}
        />
      </div>

      <!-- Taxonomy loading shown inside dropdown to avoid parent re-renders -->
      {#if taxonomyStore.loading}
        <div class="px-5 py-2 text-center text-sm text-gray-500">
          <div class="inline-flex items-center justify-center">
            <LoadingSpinner size="sm" srLabel="Memuat..." />
            <span class="ml-2">Memuat data...</span>
          </div>
        </div>
      {/if}

      <!-- Breadcrumb + Clear filter & Back button combined -->
      {#if breadcrumbLabels.length && !normalizedSearch}
        <div
          class="rounded-t bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)]"
        >
          <!-- breadcrumb header (static under search) -->
          <div
            class="px-5 py-2 pb-2 flex items-center text-sm text-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)] z-40"
          >
            <div class="flex items-center min-w-0 overflow-x-auto pb-2">
              <SitemapSolid class="mr-3 shrink-0" aria-hidden="true" />
              {#each breadcrumbLabels as crumb, idx (crumb + idx)}
                {#if idx < breadcrumbLabels.length - 1}
                  <button
                    type="button"
                    class="cursor-pointer hover:underline font-medium flex items-center whitespace-nowrap"
                    onmousedown={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      CustomDropdownBreadcrumbController.goToBreadcrumb(
                        idx,
                        stack,
                        breadcrumbLabels,
                      );
                      activeIndex = 0;
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
              onmousedown={(e) => {
                e.preventDefault();
                e.stopPropagation();
                CustomDropdownBreadcrumbController.goBack(
                  stack,
                  breadcrumbLabels,
                );
                activeIndex = 0;
              }}
              tabindex="-1"
            >
              <ArrowLeftSolid
                class="text-xs no-underline mr-2"
                aria-hidden="true"
              />
              <span class="group-hover:underline">Kembali</span>
            </button>

            {#if multiple && selectedValues.length > 0}
              <button
                type="button"
                class="dropdown-btn hover:underline border rounded-full"
                onclick={() => CustomDropdownController.clearFilters(update)}
              >
                <TrashAltSolid class="mr-2" aria-hidden="true" />Hapus filter
              </button>
            {/if}
          </div>
        </div>
      {:else if multiple && selectedValues.length > 0}
        <div
          class="flex justify-end items-center px-5 py-1 z-40 bg-[var(--wpl-global-color-5)] text-[var(--wpl-global-color-1)] bg-opacity-100 border-t"
        >
          <button
            type="button"
            class="dropdown-btn hover:underline border rounded-full"
            onclick={() => CustomDropdownController.clearFilters(update)}
          >
            <TrashAltSolid class="mr-2" aria-hidden="true" />Hapus filter
          </button>
        </div>
      {/if}

      <!-- Options list (scrollable) -->
      <div class="max-h-96 overflow-y-auto pt-2">
        <ul
          id={listboxId}
          role="listbox"
          bind:this={listboxEl}
          class="!pt-0 !pb-2"
        >
          {#each filteredNonEmpty as option, index (option.__key)}
            <li
              class={[
                "flex items-center px-5 py-2 cursor-pointer select-none transition rounded text-left",
                index === activeIndex
                  ? "bg-[var(--wpl-global-color-1)]/15"
                  : "",
                CustomDropdownHelpers.isSelected(option.value, selectedValues)
                  ? "font-bold"
                  : "",
              ].join(" ")}
            >
              <button
                type="button"
                class="flex-1 text-left flex items-center min-w-0 pr-12 break-words whitespace-normal"
                onmouseenter={() => {
                  isKeyboard = false;
                  activeIndex = index;
                }}
                onclick={() =>
                  !multiple &&
                  CustomDropdownController.select(
                    option,
                    multiple,
                    value,
                    update,
                    close,
                    resetNavigationState,
                  )}
              >
                {#if multiple}
                  <label class="flex items-center cursor-pointer mr-3"
                    ><input
                      type="checkbox"
                      class="checkbox checkbox-sm checkbox-primary"
                      checked={CustomDropdownHelpers.isSelected(
                        option.value,
                        selectedValues,
                      )}
                      onchange={() =>
                        CustomDropdownController.callUpdate(
                          CustomDropdownHelpers.toggleValueLocal(
                            option.value,
                            value,
                            multiple,
                          ),
                          update,
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
                {#if normalizedSearch}
                  {#each CustomDropdownHelpers.highlightParts(option.label, normalizedSearch) as part, partIndex (part.text + part.match + partIndex)}
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
                {#if option.__breadcrumbs && search}
                  <span class="ml-2 text-xs text-gray-400 italic"
                    >({option.__breadcrumbs.join(" / ")})</span
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
              {#if option.children?.length && !normalizedSearch}
                <button
                  class="ml-2 flex items-center justify-center w-10 h-10 rounded relative transition"
                  onclick={(e) => {
                    // stop propagation so the parent row's click/select handler does not run
                    e.stopPropagation();
                    CustomDropdownBreadcrumbController.navigateChildren(
                      option.children,
                      option.label,
                      option,
                      stack,
                      breadcrumbLabels,
                    );
                    activeIndex = 0;
                  }}
                  onmousedown={(e) => {
                    // prevent mousedown from triggering click outside handler
                    e.preventDefault();
                    e.stopPropagation();
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
        {#if filteredNonEmpty.length === 0 && !taxonomyStore.loading}
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
