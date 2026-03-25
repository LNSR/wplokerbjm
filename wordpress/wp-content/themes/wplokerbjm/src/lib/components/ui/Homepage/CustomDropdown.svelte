<script module lang="ts">
  import type { DropdownOption } from "@/types";
  import { tick } from "svelte";
  import {
    Virtualization,
    type ListVirtualizationState,
  } from "$lib/utils/Virtualization.svelte";
  import { SvelteMap } from "svelte/reactivity";

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
  type VirtualizedDropdownOption = BreadcrumbDropdownOption & { id: number };
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
    placeholder?: string;
    multiple?: boolean;
    disabled?: boolean;
    open?: boolean;
    update?: (payload: UpdatePayload) => void;
    close?: () => void;
  }

  class VirtualizationManager {
    static computeVirtualState(
      filteredNonEmpty: BreadcrumbDropdownOption[],
      scrollTop: number,
      itemHeight: number,
      containerHeight: number,
    ): ListVirtualizationState<VirtualizedDropdownOption> {
      const jobsWithId: VirtualizedDropdownOption[] = filteredNonEmpty.map(
        (job, id) => ({ ...job, id }),
      );
      const cardHeights = new SvelteMap<number, number>(
        jobsWithId.map(({ id }) => [id, itemHeight]),
      );
      const opts = {
        displayJobs: jobsWithId,
        scrollY: scrollTop,
        containerHeight,
        cardHeights,
        fallbackHeight: itemHeight,
        gap: 0,
        buffer: 3,
      };
      return Virtualization.computeList(opts);
    }

    static updateContainerHeight(scrollContainer: HTMLElement | null): number {
      return scrollContainer?.clientHeight ?? 384;
    }

    static async measureItemHeight(
      listboxEl: HTMLElement | null,
      currentItemHeight: number,
    ): Promise<number> {
      await tick();
      try {
        const lis = listboxEl?.querySelectorAll("li");
        if (!lis || lis.length === 0) return currentItemHeight;
        let maxH = 0;
        const originals: string[] = [];
        lis.forEach((li) => {
          originals.push((li as HTMLElement).style.height || "");
          (li as HTMLElement).style.height = "auto";
        });
        for (const li of Array.from(lis)) {
          if (!(li instanceof HTMLElement)) continue;
          const h = Math.ceil(li.getBoundingClientRect().height);
          if (h > maxH) maxH = h;
        }
        lis.forEach((li, idx) => {
          if (li instanceof HTMLElement) li.style.height = originals[idx] || "";
        });
        return maxH > 0 && maxH !== currentItemHeight
          ? maxH
          : currentItemHeight;
      } catch {
        return currentItemHeight;
      }
    }
  }

  class CustomDropdownHelpers {
    static isDropdownOption(value: unknown): value is DropdownOption {
      return (
        typeof value === "object" &&
        value !== null &&
        "value" in value &&
        typeof value.value === "string"
      );
    }

    static toStringValue(value: DropdownSelectionValue): string {
      return CustomDropdownHelpers.isDropdownOption(value)
        ? String(value.value)
        : String(value);
    }

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

    static normalizeToStringArray(incoming: ValueProp): string[] {
      if (!incoming) return [];
      if (Array.isArray(incoming)) {
        return incoming.map((item) =>
          CustomDropdownHelpers.toStringValue(item),
        );
      }
      return [CustomDropdownHelpers.toStringValue(incoming)];
    }

    static isSelected(v: string, selectedValues: SelectedItem[]): boolean {
      return selectedValues.some((item) => item.value === v);
    }

    static toggleValueLocal(
      v: string,
      value: ValueProp,
      multiple: boolean,
    ): string[] {
      const arr = CustomDropdownHelpers.normalizeToStringArray(value);
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
  import { onMount, onDestroy } from "svelte";
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
    placeholder = "Cari...",
    disabled = false,
    open = false,
    update = undefined,
    close = undefined,
  }: Props = $props();

  let search = $state("");
  const breadcrumbLabels = $state<string[]>([]);
  const stack = $state<DropdownOption[][]>([]);
  let activeIndex = $state(0);
  let scrollTop = $state(0);
  let itemHeight = $state(40);
  let scrollContainer = $state<HTMLElement | null>(null);
  let containerHeight = $state(384);
  let isKeyboard = $state(false);
  let scrollUpdateTimeout: ReturnType<typeof setTimeout> | null = null;

  // DOM ref
  let dropdownRef: HTMLElement | null = null;
  let listboxEl = $state<HTMLElement | null>(null);
  const listboxId = $derived(String(id ?? "custom-dropdown") + "-listbox");

  const currentOptions = $derived.by((): DropdownOption[] =>
    stack.length > 0 ? stack[stack.length - 1] : (options ?? []),
  );

  const filteredOptions = $derived.by((): BreadcrumbDropdownOption[] => {
    if (String(search).trim()) {
      const q = String(search).trim().toLowerCase();
      return CustomDropdownHelpers.flattenOptions(options ?? []).filter(
        (opt) => opt.label.toLowerCase().includes(q),
      );
    }
    return (currentOptions ?? []).map((opt): BreadcrumbDropdownOption => ({
      ...opt,
      __key: String(opt.value) + (breadcrumbLabels.join(">") || ""),
    }));
  });

  const filteredNonEmpty = $derived.by((): BreadcrumbDropdownOption[] =>
    (filteredOptions ?? []).filter((opt) => String(opt.value).trim() !== ""),
  );

  const selectedValues = $derived.by((): SelectedItem[] => {
    const valArr = CustomDropdownHelpers.normalizeToStringArray(value).filter(
      (selectedValue) => String(selectedValue).trim() !== "",
    );
    const flat = CustomDropdownHelpers.flattenOptions(options ?? []);
    return valArr.map((selectedValue) => {
      const found =
        flat.find((option) => option.value === selectedValue) ??
        (options ?? []).find((option) => option.value === selectedValue);
      return found
        ? { value: String(found.value), label: String(found.label) }
        : { value: selectedValue, label: selectedValue };
    });
  });

  const virtualState = $derived.by(
    (): ListVirtualizationState<VirtualizedDropdownOption> =>
    VirtualizationManager.computeVirtualState(
      filteredNonEmpty,
      scrollTop,
      itemHeight,
      containerHeight,
    ),
  );

  function resetNavigationState(): void {
    CustomDropdownBreadcrumbController.resetBreadcrumb(stack, breadcrumbLabels);
    activeIndex = 0;
    search = "";
  }

  function updateContainerHeight(): void {
    containerHeight =
      VirtualizationManager.updateContainerHeight(scrollContainer);
  }

  const onKeyDown = (e: KeyboardEvent): void => {
    if (!open) return;
    isKeyboard = true;
    const list = filteredNonEmpty ?? [];

    const keyHandlers: Partial<Record<SupportedKey, () => void>> = {
      ArrowDown: () => {
        e.preventDefault();
        activeIndex = Math.min((activeIndex ?? 0) + 1, (list.length || 0) - 1);
      },
      ArrowUp: () => {
        e.preventDefault();
        activeIndex =
          (activeIndex ?? 0) > 0
            ? (activeIndex ?? 0) - 1
            : (list.length || 1) - 1;
      },
      ArrowRight: () => {
        e.preventDefault();
        const opt = list[activeIndex];
        if (opt && opt.children?.length && !search) {
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
        e.preventDefault();
        if (breadcrumbLabels.length && !search) {
          CustomDropdownBreadcrumbController.goBack(stack, breadcrumbLabels);
          activeIndex = 0;
        }
      },
      Enter: () => {
        e.preventDefault();
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

    keyHandlers[e.key as SupportedKey]?.();
  };

  let ro: ResizeObserver | null = null;

  async function measureItemHeight() {
    itemHeight = await VirtualizationManager.measureItemHeight(
      listboxEl,
      itemHeight,
    );
  }

  onMount(() => {
    // reference listboxEl to satisfy TS/linter (it's bound in template)
    if (listboxEl) {
      void listboxEl;
    }

    // setup resize observer to detect dynamic changes
    try {
      ro = new ResizeObserver(() => {
        void measureItemHeight();
      });
      if (listboxEl) ro.observe(listboxEl);
    } catch {
      // ResizeObserver unavailable
    }

    // initial measurement
    void measureItemHeight();
    // initial container height measurement
    setTimeout(() => updateContainerHeight(), 0);
  });

  onDestroy(() => {
    if (ro) ro.disconnect();
    if (scrollUpdateTimeout) clearTimeout(scrollUpdateTimeout);
  });

  $effect(() => {
    filteredNonEmpty;
    scrollTop = 0;
    void measureItemHeight();
    // content changed, re-measure viewport height
    requestAnimationFrame(() => updateContainerHeight());
    if (ro && listboxEl) {
      try {
        ro.disconnect();
        ro.observe(listboxEl);
      } catch {}
    }
  });

  $effect(() => {
    breadcrumbLabels;
    stack;
    scrollTop = 0;
    void measureItemHeight();
  });

  $effect(() => {
    activeIndex;
    itemHeight;
    if (!isKeyboard) return;
    const sc = scrollContainer;
    if (!sc) return;
    const vh = sc.clientHeight;
    const targetTop = virtualState.itemPositions[activeIndex] ?? 0;
    const row = itemHeight || 40;
    if (targetTop < sc.scrollTop) sc.scrollTop = targetTop;
    else if (targetTop + row > sc.scrollTop + vh)
      sc.scrollTop = targetTop + row - vh;
  });
</script>

<svelte:document
  on:mousedown={(e) => {
    CustomDropdownController.handleClickOutside(e, dropdownRef, open, close);
  }}
/>

<svelte:window on:resize={updateContainerHeight} />

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
      class="absolute left-0 right-0 mt-2 rounded-lg shadow-lg z-30 pt-2 bg-[var(--wpl-global-color-5)]"
    >
      <!-- Search input (static header above scrollable options) -->
      <div class="px-5 py-2 bg-[var(--wpl-global-color-5)]">
        <input
          bind:value={search}
          type="text"
          class="w-full search-input border rounded px-3 py-2 text-sm ring-1 ring-[var(--wpl-global-color-1)]"
          placeholder="Cari..."
          onkeydown={(e) => e.stopPropagation()}
          oninput={() => {
            activeIndex = 0;
            scrollTop = 0;
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
      {#if breadcrumbLabels.length && !search}
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
      <div
        class="max-h-96 overflow-y-auto pt-2"
        bind:this={scrollContainer}
        onscroll={(e) => {
          const target = e.currentTarget as HTMLElement;
          if (scrollUpdateTimeout) clearTimeout(scrollUpdateTimeout);
          scrollUpdateTimeout = setTimeout(() => {
            scrollTop = target.scrollTop;
          }, 50);
        }}
      >
        <ul
          id={listboxId}
          role="listbox"
          bind:this={listboxEl}
          style="height: {virtualState.totalHeight}px; position: relative;"
          class="!pt-0 !pb-2"
        >
          {#each virtualState.visibleJobs as option, index (option.__key)}
            <li
              style="position: absolute; transform: translate3d(0, {virtualState
                .itemPositions[
                virtualState.startIndex + index
              ]}px, 0); width: 100%; height: {itemHeight}px;"
              class={[
                "flex items-center px-5 py-2 cursor-pointer select-none transition rounded text-left",
                virtualState.startIndex + index === activeIndex
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
                  activeIndex = virtualState.startIndex + index;
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
                  <label class="flex items-center cursor-pointer"
                    ><input
                      type="checkbox"
                      class="mr-3 w-6 h-6 accent-blue-500"
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
                {#if search && String(search).trim()}
                  {#each CustomDropdownHelpers.highlightParts(option.label, String(search)) as part, partIndex (part.text + part.match + partIndex)}
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
              {#if option.children?.length && !search}
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
