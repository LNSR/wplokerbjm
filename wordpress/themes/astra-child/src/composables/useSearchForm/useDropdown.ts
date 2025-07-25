import { ref, computed, watch, type Ref } from "vue";
import type { SearchFilters } from "@/types";
import { useTaxonomyStore } from "@/stores/Taxonomy";
import type {
  SelectedItem,
  Option,
  UseDropdownReturn,
} from "@/types/Component";
import { useBreadcrumb } from "./useDropdownBreadcrumb";

export function useDropdown(props: {
  modelValue: Ref<SearchFilters>;
  options: Ref<Option[]>;
  emit: (event: string, ...args: any[]) => void;
  multiple?: boolean;
  placeholder?: string;
}): UseDropdownReturn {
  const open = ref(false);
  const activeIndex = ref(0);
  const search = ref("");
  const SEMUA_VALUE = "";

  const isMultiple = computed(() => !!props.multiple);
  const taxonomyStore = useTaxonomyStore();

  // Use breadcrumb composable for stack and breadcrumbLabels
  const {
    breadcrumb,
    stack,
    activeIndex: breadcrumbActiveIndex,
    goBack,
    goToBreadcrumb,
    pushBreadcrumb,
    resetBreadcrumb,
  } = useBreadcrumb();

  // Proxy activeIndex to local ref for keyboard navigation
  watch(activeIndex, (val) => {
    breadcrumbActiveIndex.value = val;
  });
  watch(breadcrumbActiveIndex, (val) => {
    activeIndex.value = val;
  });

  const selectedValues = computed<SelectedItem[]>(() => {
    const val = props.modelValue.value;
    if (Array.isArray(val)) {
      return val.map((v) => {
        const opt = props.options.value.find((o) => o.value === v);
        return opt
          ? { value: opt.value, label: opt.label }
          : { value: v, label: v };
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
          for (const t of ["lokasi", "gender", "pendidikan"] as const) {
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
    if (parentOption?.loadChildren && (!children || children.length === 0)) {
      parentOption.isLoading = true;
      parentOption.loadChildren().then((loadedChildren) => {
        parentOption.children = loadedChildren;
        parentOption.isLoading = false;
        pushBreadcrumb(label, parentOption.children);
        activeIndex.value = 0;
      });
    } else {
      pushBreadcrumb(label, children);
      activeIndex.value = 0;
    }
  }

  function flattenOptions(
    options: Option[],
    breadcrumbs: string[] = []
  ): Option[] {
    let result: Option[] = [];
    for (const opt of options) {
      const key = [opt.value, ...breadcrumbs].join(">");
      result.push({
        ...opt,
        __breadcrumbs: breadcrumbs,
        __key: key,
        children: undefined,
      });
      if (opt.children && opt.children.length) {
        result = result.concat(
          flattenOptions(opt.children, [...breadcrumbs, opt.label])
        );
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
    // Use breadcrumb for key
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
    const regex = new RegExp(
      `(${query.replace(/[.*+?^${}()|[\\]\\]/g, "\\$&")})`,
      "gi"
    );
    return label.replace(
      regex,
      '<b class="bg-[var(--ast-global-color-5)] font-bold rounded px-1">$1</b>'
    );
  }

  return {
    open,
    activeIndex,
    search,
    breadcrumb,
    selectedValues,
    multiSelectLabel,
    isSelected,
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
