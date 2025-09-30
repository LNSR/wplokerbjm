import { ref, computed, type Ref, type ComputedRef } from "vue";
import { debounce } from '@/utils/debounce'
import { TaxonomyType } from "@/types";
import { useTaxonomyStore } from "@/stores";
import type { DropdownOption } from '@/types/Component';
export type SelectedItem = { value: string; label: string }

export function useDropdown(props: {
  modelValue: Ref<unknown>;
  options: Ref<DropdownOption[]>;
  multiple?: boolean;
  placeholder?: string;
}): {
  open: Ref<boolean>;
  activeIndex: Ref<number>;
  search: Ref<string>;
  breadcrumb: ComputedRef<string[]>;
  breadcrumbLabels: Ref<string[]>;
  stack: Ref<DropdownOption[][]>;
  selectedValues: ComputedRef<SelectedItem[]>;
  multiSelectLabel: ComputedRef<string>;
  isSelected: (value: string) => boolean;
  toggleValue: (value: string) => string[];
  isMultiple: ComputedRef<boolean>;
  getLabel: () => string;
  SEMUA_VALUE: string;
  currentOptions: ComputedRef<DropdownOption[]>;
  filteredOptions: ComputedRef<DropdownOption[]>;
  filteredNonEmpty: ComputedRef<DropdownOption[]>;
  highlightMatch: (label: string, query: string) => string;
  flattenOptions: (options: DropdownOption[], breadcrumbs?: string[]) => DropdownOption[];
} {
  const open = ref(false);
  const search = ref("");
  const SEMUA_VALUE = "";

  const isMultiple = computed(() => !!props.multiple);
  const taxonomyStore = useTaxonomyStore();

  // Breadcrumb state (moved from useDropdownBreadcrumb)
  const breadcrumbLabels = ref<string[]>([])
  const stack = ref<DropdownOption[][]>([])
  const activeIndex = ref(0)
  const breadcrumb = computed(() => breadcrumbLabels.value)

  const selectedValues = computed<SelectedItem[]>(() => {
    const val = props.modelValue.value;
    const findOption = (v: string): DropdownOption | undefined => props.options.value.find((o) => o.value === v);
    if (Array.isArray(val)) {
      return val.map((v) => {
        const opt = findOption(v);
        return opt ? { value: opt.value, label: opt.label } : { value: v, label: v };
      });
    }
    if (val && typeof val === "object" && "value" in val && "label" in val) {
      return [val as SelectedItem];
    }
    return [];
  });

  const multiSelectLabel = computed(() => {
    if (!isMultiple.value) {
      const firstItem = selectedValues.value.length > 0 ? selectedValues.value[0] : null;
      return firstItem ? firstItem.label : "";
    }
    const filtered = selectedValues.value.filter(
      (v) => v.value !== SEMUA_VALUE && v.value !== ""
    );
    if (filtered.length === 0) return props.placeholder || "Pilih";
    if (filtered.length === 1) {
      const item = filtered[0];
      if (!item) return props.placeholder || "Pilih";
      let name = item.label;
      if (props.options.value.length && taxonomyStore) {
        if (props.options.value.some((opt) => opt.value === item.value)) {
          name = item.label;
        } else {
          const taxKeys: TaxonomyType[] = [TaxonomyType.lokasi, TaxonomyType.gender, TaxonomyType.pendidikan];
          for (let i = 0; i < taxKeys.length; i++) {
            const t = taxKeys[i] as Parameters<typeof taxonomyStore.getTermNameBySlug>[0];
            const n = taxonomyStore.getTermNameBySlug(t, item.value);
            if (n && n !== item.value) {
              name = n;
              break;
            }
          }
        }
      }
      return name;
    }
    return `${filtered.length} filter dipilih`;
  });

  function toggleValue(value: string): string[] {
    let arr = Array.isArray(props.modelValue.value)
      ? [...(props.modelValue.value as string[])]
      : [];
    const exists = arr.includes(value);
    if (!isMultiple.value) return arr;
    arr = exists ? arr.filter((item) => item !== value) : [...arr, value];
    return arr;
  }

  function isSelected(value: string): boolean {
    return selectedValues.value.some((item) => item.value === value);
  }

  const currentOptions = computed<DropdownOption[]>(() => {
    if (stack.value.length > 0) {
      const lastStack = stack.value[stack.value.length - 1];
      return lastStack || [];
    }
    return props.options.value ?? [];
  });

  function getLabel(): string {
    return (multiSelectLabel && multiSelectLabel.value) || (props && props.placeholder) || ''
  }

  function flattenOptions(
    options: DropdownOption[],
    breadcrumbs: string[] = []
  ): DropdownOption[] {
    const result: DropdownOption[] = [];
    for (let i = 0; i < options.length; i++) {
      const opt = options[i];
      if (!opt) continue;
      const key = [opt.value, ...breadcrumbs].join(">");
      result.push({ ...opt, __breadcrumbs: breadcrumbs, __key: key, children: undefined });
      if (opt.children && opt.children.length) {
        const nested = flattenOptions(opt.children, [...breadcrumbs, opt.label]);
        for (let j = 0; j < nested.length; j++) {
          const nestedOpt = nested[j];
          if (nestedOpt) {
            result.push(nestedOpt);
          }
        }
      }
    }
    return result;
  }

  const filteredOptions = computed<DropdownOption[]>(() => {
    if (search.value.trim()) {
      const q = search.value.trim().toLowerCase();
      return flattenOptions(props.options.value).filter((opt) =>
        opt.label.toLowerCase().includes(q)
      );
    }
    return currentOptions.value.map((opt) => ({
      ...opt,
      __key: opt.value + (breadcrumb.value.join(">") || ""),
    }));
  });

  const filteredNonEmpty = computed(() => filteredOptions.value.filter((opt) => opt.value !== ''))

  function highlightMatch(label: string, query: string): string {
    if (!query) return label;
    const escapeRegex = (s: string): string => s.replace(/[.*+?^${}()|[\\]\\]/g, "\\$&");
    const regex = new RegExp(`(${escapeRegex(query)})`, "gi");
    return label.replace(regex, '<b class="bg-[var(--ast-global-color-5)] font-bold rounded px-1">$1</b>');
  }

  return {
    open,
    activeIndex,
    search,
    breadcrumb,
    breadcrumbLabels,
    stack,
    selectedValues,
    multiSelectLabel,
    isSelected,
    getLabel,
    toggleValue,
    isMultiple,
    SEMUA_VALUE,
    currentOptions,
    filteredOptions,
    filteredNonEmpty,
    highlightMatch,
    flattenOptions,
  };
}
export const DROPDOWN_CONTROLLER = Symbol('dropdownController');
export function useDropdownController(): {
  controller: {
    register: (key: TaxonomyType | "sort", handle: { toggle: () => void; close: () => void; getLabel: () => string; open: boolean | Ref<boolean>; }) => () => void;
    toggleRef: (key: TaxonomyType | "sort") => void;
  };
  lokasiRef: Ref<{ toggle: () => void; close: () => void; getLabel: () => string; open: boolean | Ref<boolean>; } | null>;
  genderRef: Ref<{ toggle: () => void; close: () => void; getLabel: () => string; open: boolean | Ref<boolean>; } | null>;
  pendidikanRef: Ref<{ toggle: () => void; close: () => void; getLabel: () => string; open: boolean | Ref<boolean>; } | null>;
  sortRef: Ref<{ toggle: () => void; close: () => void; getLabel: () => string; open: boolean | Ref<boolean>; } | null>;
  lokasiLoaded: Ref<boolean>;
  genderLoaded: Ref<boolean>;
  pendidikanLoaded: Ref<boolean>;
  sortLoaded: Ref<boolean>;
  isLokasiOpen: ComputedRef<boolean>;
  isGenderOpen: ComputedRef<boolean>;
  isPendidikanOpen: ComputedRef<boolean>;
  isSortOpen: ComputedRef<boolean>;
  lokasiLabel: ComputedRef<string>;
  genderLabel: ComputedRef<string>;
  pendidikanLabel: ComputedRef<string>;
  sortLabel: ComputedRef<string>;
  toggleLokasi: () => void;
  toggleGender: () => void;
  togglePendidikan: () => void;
  toggleSort: () => void;
} {
  interface DropdownHandle {
    toggle: () => void
    close: () => void
    getLabel: () => string
    open: boolean | Ref<boolean>
  }

  interface DropdownMeta {
    ref: Ref<DropdownHandle | null>;
    loaded: Ref<boolean>;
    label: string;
    fallback: string;
    pendingToggle?: Ref<boolean>;
  };
  type DropdownKey = TaxonomyType | "sort";

  const dropdowns: Record<DropdownKey, DropdownMeta> = {
    [TaxonomyType.lokasi]: { ref: ref(null), loaded: ref(false), label: "Semua Lokasi", fallback: "Semua Lokasi", pendingToggle: ref(false) },
    [TaxonomyType.gender]: { ref: ref(null), loaded: ref(false), label: "Semua Gender", fallback: "Semua Gender", pendingToggle: ref(false) },
    [TaxonomyType.pendidikan]: { ref: ref(null), loaded: ref(false), label: "Semua Pendidikan", fallback: "Semua Pendidikan", pendingToggle: ref(false) },
    sort: { ref: ref(null), loaded: ref(false), label: "Urutkan", fallback: "Urutkan", pendingToggle: ref(false) },
  };

  function getOpen(key: DropdownKey): ComputedRef<boolean> {
    return computed(() => {
      const o = dropdowns[key].ref.value?.open;
      if (typeof o === "boolean") return o;
      if (o && typeof (o as Ref<boolean>).value !== "undefined") return (o as Ref<boolean>).value;
      return false;
    });
  }

  function getLabel(key: DropdownKey): ComputedRef<string> {
    return computed(() => {
      try {
        return dropdowns[key].ref.value?.getLabel?.() ?? dropdowns[key].fallback;
      } catch {
        return dropdowns[key].fallback;
      }
    });
  }

  function toggleRef(key: DropdownKey): void {
    const r = dropdowns[key].ref;
    const o = r.value?.open;
    const isOpen = typeof o === "boolean" ? o : o ? (o as Ref<boolean>).value : false;
    if (!isOpen) {
      for (const k in dropdowns) {
        if (k !== key) dropdowns[k as DropdownKey].ref.value?.close?.();
      }
    }
    r.value?.toggle?.();
  }

  // Debounced toggleRef to avoid rapid duplicate toggles (e.g., mount/register race)
  const debouncedToggleRef = debounce((key: DropdownKey) => {
    toggleRef(key)
  }, 100, { leading: true, trailing: false });

  const controller = {
    register(key: DropdownKey, handle: DropdownHandle) {
      const meta = dropdowns[key];
      meta.ref.value = handle;
      // If a toggle was requested while the child wasn't registered yet, perform it now (debounced)
      if (meta.pendingToggle?.value) {
        meta.pendingToggle.value = false;
        debouncedToggleRef(key);
      }
      return (): void => {
        if (dropdowns[key].ref.value === handle) dropdowns[key].ref.value = null;
      };
    },
    toggleRef,
  } as const;

  function toggleLokasi(): void {
    const meta = dropdowns[TaxonomyType.lokasi];
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef(TaxonomyType.lokasi);
      else meta.pendingToggle!.value = true;
    } else {
      meta.loaded.value = true;
    }
  }
  function toggleGender(): void {
    const meta = dropdowns[TaxonomyType.gender];
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef(TaxonomyType.gender);
      else meta.pendingToggle!.value = true;
    } else {
      meta.loaded.value = true;
    }
  }
  function togglePendidikan(): void {
    const meta = dropdowns[TaxonomyType.pendidikan];
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef(TaxonomyType.pendidikan);
      else meta.pendingToggle!.value = true;
    } else {
      meta.loaded.value = true;
    }
  }
  function toggleSort(): void {
    const meta = dropdowns.sort;
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef('sort');
      else meta.pendingToggle!.value = true;
    } else {
      meta.loaded.value = true;
    }
  }

  return {
    controller,
    lokasiRef: dropdowns.lokasi.ref,
    genderRef: dropdowns.gender.ref,
    pendidikanRef: dropdowns.pendidikan.ref,
    sortRef: dropdowns.sort.ref,

    lokasiLoaded: dropdowns.lokasi.loaded,
    genderLoaded: dropdowns.gender.loaded,
    pendidikanLoaded: dropdowns.pendidikan.loaded,
    sortLoaded: dropdowns.sort.loaded,

    isLokasiOpen: getOpen(TaxonomyType.lokasi),
    isGenderOpen: getOpen(TaxonomyType.gender),
    isPendidikanOpen: getOpen(TaxonomyType.pendidikan),
    isSortOpen: getOpen("sort"),

    lokasiLabel: getLabel(TaxonomyType.lokasi),
    genderLabel: getLabel(TaxonomyType.gender),
    pendidikanLabel: getLabel(TaxonomyType.pendidikan),
    sortLabel: getLabel("sort"),

    toggleLokasi,
    toggleGender,
    togglePendidikan,
    toggleSort,

  };
}
