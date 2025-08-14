import { ref, computed, watch, type Ref, type ComputedRef } from "vue";
import { debounce } from '@/utils/debounce'
import type { SearchFilters } from "@/types";
import { useTaxonomyStore } from "@/stores/Taxonomy";
import { useBreadcrumb } from "./useDropdownBreadcrumb";
// Dropdown composable types
export type SelectedItem = { value: string; label: string }

export type Option = {
  value: string
  label: string
  children?: Option[]
  isLoading?: boolean
  hasMoreChildren?: boolean
  loadChildren?: () => Promise<Option[]>
  __breadcrumbs?: string[]
  __key?: string
}

export interface UseDropdownReturn {
  open: Ref<boolean>;
  activeIndex: Ref<number>;
  search: Ref<string>;
  breadcrumb: ComputedRef<string[]>;
  selectedValues: ComputedRef<SelectedItem[]>;
  multiSelectLabel: ComputedRef<string>;
  isSelected: (value: string) => boolean;
  toggleValue: (value: string) => void;
  isMultiple: ComputedRef<boolean>;
  getLabel: () => string;
  SEMUA_VALUE: string;
  toggle: () => void;
  close: () => void;
  select: (option: Option) => void;
  goBack: () => void;
  navigateChildren: (children: Option[], label: string, parentOption?: Option) => void;
  goToBreadcrumb: (idx: number) => void;
  filteredOptions: ComputedRef<Option[]>;
  highlightMatch: (label: string, query: string) => string;
}

export function useDropdown(props: {
  modelValue: Ref<SearchFilters>;
  options: Ref<Option[]>;
  emit: (event: string, ...args: any[]) => void;
  multiple?: boolean;
  placeholder?: string;
}): UseDropdownReturn {
  const open = ref(false);
  const search = ref("");
  const SEMUA_VALUE = "";

  const isMultiple = computed(() => !!props.multiple);
  const taxonomyStore = useTaxonomyStore();

  // Use breadcrumb composable for stack and breadcrumbLabels
  const {
    breadcrumb,
    stack,
    activeIndex,
    goBack,
    goToBreadcrumb,
    pushBreadcrumb,
    resetBreadcrumb,
  } = useBreadcrumb();

  const selectedValues = computed<SelectedItem[]>(() => {
    const val = props.modelValue.value;
    const findOption = (v: string) => props.options.value.find((o) => o.value === v);
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
      return selectedValues.value.length ? selectedValues.value[0].label : "";
    }
    const filtered = selectedValues.value.filter(
      (v) => v.value !== SEMUA_VALUE && v.value !== ""
    );
    if (filtered.length === 0) return props.placeholder || "Pilih";
    if (filtered.length === 1) {
      const item = filtered[0];
      let name = item.label;
      if (props.options.value.length && taxonomyStore) {
        if (props.options.value.some((opt) => opt.value === item.value)) {
          name = item.label;
        } else {
          const taxKeys: string[] = ["lokasi", "gender", "pendidikan"];
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

  function toggleValue(value: string) {
    let arr = Array.isArray(props.modelValue.value)
      ? [...(props.modelValue.value as string[])]
      : [];
    const exists = arr.includes(value);
    if (!isMultiple.value) return;
    arr = exists ? arr.filter((item) => item !== value) : [...arr, value];
    props.emit("update:modelValue", arr);
  }

  function isSelected(value: string) {
    return selectedValues.value.some((item) => item.value === value);
  }

  const currentOptions = computed<Option[]>(() => {
    return stack.value.length
      ? stack.value[stack.value.length - 1]
      : props.options.value;
  });

  function getLabel() {
    return (multiSelectLabel && multiSelectLabel.value) || (props && (props as any).placeholder) || ''
  }

  function toggle() {
    open.value = !open.value;
    if (open.value) {
      activeIndex.value = 0;
      props.emit("open");
    }
  }
  function close() {
    open.value = false;
    resetBreadcrumb();
    search.value = "";
  }
  function select(option: Option) {
    props.emit("update:modelValue", option);
    close();
  }
  function navigateChildren(
    children: Option[],
    label: string,
    parentOption?: Option
  ) {
    (async () => {
      if (parentOption?.loadChildren && (!children || children.length === 0)) {
        parentOption.isLoading = true;
        try {
          const loaded = await parentOption.loadChildren();
          parentOption.children = loaded;
        } finally {
          parentOption.isLoading = false;
        }
        pushBreadcrumb(label, parentOption.children);
      } else {
        pushBreadcrumb(label, children);
      }
      activeIndex.value = 0;
    })();
  }

  function flattenOptions(
    options: Option[],
    breadcrumbs: string[] = []
  ): Option[] {
    const result: Option[] = [];
    for (let i = 0; i < options.length; i++) {
      const opt = options[i];
      const key = [opt.value, ...breadcrumbs].join(">");
      result.push({ ...opt, __breadcrumbs: breadcrumbs, __key: key, children: undefined });
      if (opt.children && opt.children.length) {
        const nested = flattenOptions(opt.children, [...breadcrumbs, opt.label]);
        for (let j = 0; j < nested.length; j++) {
          result.push(nested[j]);
        }
      }
    }
    return result;
  }

  const filteredOptions = computed<Option[]>(() => {
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

  watch(open, (val: boolean) => {
    if (!val) {
      activeIndex.value = 0;
      resetBreadcrumb();
      search.value = "";
    }
  });
  watch(search, () => {
    activeIndex.value = 0;
  });

  function highlightMatch(label: string, query: string): string {
    if (!query) return label;
    const escapeRegex = (s: string) => s.replace(/[.*+?^${}()|[\\]\\]/g, "\\$&");
    const regex = new RegExp(`(${escapeRegex(query)})`, "gi");
    return label.replace(regex, '<b class="bg-[var(--ast-global-color-5)] font-bold rounded px-1">$1</b>');
  }

  return {
    open,
    activeIndex,
    search,
    breadcrumb,
    selectedValues,
    multiSelectLabel,
    isSelected,
    getLabel,
    toggleValue,
    isMultiple,
    SEMUA_VALUE,
    toggle,
    close,
    select,
    goBack,
    navigateChildren,
    goToBreadcrumb,
    filteredOptions,
    highlightMatch,
  };
}
export const DROPDOWN_CONTROLLER = Symbol('dropdownController');
export function useDropdownController() {
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
  type DropdownKey = "lokasi" | "gender" | "pendidikan" | "sort";

  const dropdowns: Record<DropdownKey, DropdownMeta> = {
    lokasi: { ref: ref(null), loaded: ref(false), label: "Semua Lokasi", fallback: "Semua Lokasi", pendingToggle: ref(false) },
    gender: { ref: ref(null), loaded: ref(false), label: "Semua Gender", fallback: "Semua Gender", pendingToggle: ref(false) },
    pendidikan: { ref: ref(null), loaded: ref(false), label: "Semua Pendidikan", fallback: "Semua Pendidikan", pendingToggle: ref(false) },
    sort: { ref: ref(null), loaded: ref(false), label: "Urutkan", fallback: "Urutkan", pendingToggle: ref(false) },
  };

  function getOpen(key: DropdownKey) {
    return computed(() => {
      const o = dropdowns[key].ref.value?.open;
      if (typeof o === "boolean") return o;
      if (o && typeof (o as Ref<boolean>).value !== "undefined") return (o as Ref<boolean>).value;
      return false;
    });
  }

  function getLabel(key: DropdownKey) {
    return computed(() => {
      try {
        return dropdowns[key].ref.value?.getLabel?.() ?? dropdowns[key].fallback;
      } catch {
        return dropdowns[key].fallback;
      }
    });
  }

  function toggleRef(key: DropdownKey) {
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
      return () => {
        if (dropdowns[key].ref.value === handle) dropdowns[key].ref.value = null;
      };
    },
    toggleRef,
  } as const;

  function toggleLokasi(): void {
    const meta = dropdowns.lokasi;
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef('lokasi');
      else meta.pendingToggle!.value = true;
    } else {
      meta.loaded.value = true;
    }
  }
  function toggleGender(): void {
    const meta = dropdowns.gender;
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef('gender');
      else meta.pendingToggle!.value = true;
    } else {
      meta.loaded.value = true;
    }
  }
  function togglePendidikan(): void {
    const meta = dropdowns.pendidikan;
    if (meta.loaded.value) {
      if (meta.ref.value) debouncedToggleRef('pendidikan');
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

    isLokasiOpen: getOpen("lokasi"),
    isGenderOpen: getOpen("gender"),
    isPendidikanOpen: getOpen("pendidikan"),
    isSortOpen: getOpen("sort"),

    lokasiLabel: getLabel("lokasi"),
    genderLabel: getLabel("gender"),
    pendidikanLabel: getLabel("pendidikan"),
    sortLabel: getLabel("sort"),

    toggleLokasi,
    toggleGender,
    togglePendidikan,
    toggleSort,

  };
}
