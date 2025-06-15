import { ref, computed, watch, type Ref, type ComputedRef } from 'vue'
import type { SearchFilters  } from '@/types'
import { useTaxonomyStore } from '@/stores/taxonomy'

export type SelectedItem = { value: string; label: string }
export type Option = {
  value: string
  label: string
  children?: Option[]
  isLoading?: boolean           // <-- Add this
  hasMoreChildren?: boolean     // <-- Add this if you want to support "load more"
  loadChildren?: () => Promise<Option[]> // <-- Add this for lazy loading
  __breadcrumbs?: string[]
  __key?: string
}
interface UseDropdownReturn {
  open: Ref<boolean>
  activeIndex: Ref<number>
  stack: Ref<Option[][]>
  breadcrumbLabels: Ref<string[]>
  search: Ref<string>
  currentOptions: ComputedRef<Option[]>
  breadcrumb: ComputedRef<string[]>
  selectedLabel: ComputedRef<string>
  selectedValues: ComputedRef<SelectedItem[]>
  multiSelectLabel: ComputedRef<string>
  isSelected: (value: string) => boolean
  toggleValue: (value: string, label: string) => void
  isMultiple: ComputedRef<boolean>
  SEMUA_VALUE: string
  toggle: () => void
  close: () => void
  handleOption: (option: Option) => void
  select: (option: Option) => void
  goBack: () => void
  navigate: (dir: number) => void
  selectActive: () => void
  navigateChildren: (children: Option[], label: string, parentOption?: Option) => void
  goToBreadcrumb: (idx: number) => void
  filteredOptions: ComputedRef<Option[]>
  highlightMatch: (label: string, query: string) => string
}

export function useDropdown(props: {
  modelValue: Ref<SearchFilters>
  options: Ref<Option[]>
  emit: (event: string, ...args: any[]) => void
  multiple?: boolean
  placeholder?: string
}): UseDropdownReturn {
  const open = ref<boolean>(false)
  const activeIndex = ref<number>(0)
  const stack = ref<Option[][]>([])
  const breadcrumbLabels = ref<string[]>([])
  const search = ref<string>('')

  const SEMUA_VALUE = ''

  const isMultiple = computed(() => !!props.multiple)
  const selectedValues = computed<SelectedItem[]>(() => {
    const val = props.modelValue.value
    if (Array.isArray(val)) {
      return val
        .map(v => {
          const opt = props.options.value.find(o => o.value === v)
          return opt ? { value: opt.value, label: opt.label } : { value: v, label: v }
        })
    }
    if (val && typeof val === 'object' && 'value' in val && 'label' in val) {
      return [val as SelectedItem]
    }
    return []
  })

  const taxonomyStore = useTaxonomyStore()

  const multiSelectLabel = computed(() => {
    if (!isMultiple.value) return selectedLabel.value
    // Filter out empty string and SEMUA_VALUE
    const filtered = selectedValues.value.filter(
      v => v.value !== SEMUA_VALUE && v.value !== ''
    )
    if (filtered.length === 0) return props.placeholder || 'Pilih'
    if (filtered.length === 1) {
      // Try to get the name from taxonomyStore if possible
      const item = filtered[0]
      // Try to detect type by option list
      let name = item.label
      if (props.options.value.length && props.options.value[0].value && taxonomyStore) {
        // Try to guess type
        if (props.options.value[0].value && props.options.value[0].label) {
          // Try to match known types
          if (props.options.value.some(opt => opt.value === item.value)) {
            // fallback to label
            name = item.label
          } else {
            // Try all types
            for (const t of ['lokasi', 'gender', 'pendidikan'] as const) {
              const n = taxonomyStore.getTermNameBySlug(t, item.value)
              if (n && n !== item.value) {
                name = n
                break
              }
            }
          }
        }
      }
      return name
    }
    return `${filtered.length} filter dipilih`
  })

  function toggleValue(value: string) {
    let arr = Array.isArray(props.modelValue.value) ? [...props.modelValue.value as string[]] : []
    const exists = arr.includes(value)
    if (!isMultiple.value) {
      // Do nothing here; single-select handled by select(option)
      return
    }
    if (exists) {
      arr = arr.filter(item => item !== value)
    } else {
      arr.push(value)
    }
    props.emit('update:modelValue', arr)
  }

  function isSelected(value: string) {
    return selectedValues.value.some(item => item.value === value)
  }

  const currentOptions = computed<Option[]>(() => {
    if (stack.value.length) {
      return stack.value[stack.value.length - 1]
    }
    return props.options.value
  })

  const breadcrumb = computed<string[]>(() => breadcrumbLabels.value)

  const selectedLabel = computed<string>(() => {
    if (selectedValues.value.length) {
      return selectedValues.value[0].label
    }
    return ''
  })

  function toggle(): void {
    open.value = !open.value
    if (open.value) {
      activeIndex.value = 0
      props.emit('open')
    }
  }
  function close(): void {
    open.value = false
    stack.value = []
    breadcrumbLabels.value = []
    activeIndex.value = 0
    search.value = ''
  }
  function handleOption(option: Option): void {
    select(option)
  }
  function select(option: Option): void {
    props.emit('update:modelValue', option)
    close()
  }
  function goBack(): void {
    if (stack.value.length) {
      stack.value.pop()
      breadcrumbLabels.value.pop()
      activeIndex.value = 0
    }
  }
  function navigate(dir: number): void {
    if (!open.value) {
      open.value = true
      activeIndex.value = 0
      return
    }
    const opts = filteredOptions.value
    let idx = activeIndex.value + dir
    if (idx < 0) idx = opts.length - 1
    if (idx >= opts.length) idx = 0
    activeIndex.value = idx
  }
  function selectActive(): void {
    if (open.value && activeIndex.value >= 0) {
      const option = filteredOptions.value[activeIndex.value]
      handleOption(option)
    }
  }
  function navigateChildren(children: Option[], label: string, parentOption?: Option): void {
    if (parentOption?.loadChildren && (!children || children.length === 0)) {
      parentOption.isLoading = true
      parentOption.loadChildren().then(loadedChildren => {
        parentOption.children = loadedChildren
        parentOption.isLoading = false
        stack.value.push(parentOption.children)
        breadcrumbLabels.value.push(label)
        activeIndex.value = 0
      })
    } else {
      stack.value.push(children)
      breadcrumbLabels.value.push(label)
      activeIndex.value = 0
    }
  }
  function goToBreadcrumb(idx: number): void {
    stack.value = stack.value.slice(0, idx + 1)
    breadcrumbLabels.value = breadcrumbLabels.value.slice(0, idx + 1)
    activeIndex.value = 0
  }

  function flattenOptions(
    options: Option[],
    breadcrumbs: string[] = []
  ): Option[] {
    let result: Option[] = []
    for (const opt of options) {
      const key = [opt.value, ...breadcrumbs].join('>')
      result.push({
        ...opt,
        __breadcrumbs: breadcrumbs,
        __key: key,
        children: undefined
      })
      if (opt.children && opt.children.length) {
        result = result.concat(flattenOptions(opt.children, [...breadcrumbs, opt.label]))
      }
    }
    return result
  }

  const filteredOptions = computed<Option[]>(() => {
    if (search.value.trim()) {
      const q = search.value.trim().toLowerCase()
      return flattenOptions(props.options.value)
        .filter(opt => opt.label.toLowerCase().includes(q))
    } else {
      return currentOptions.value.map(opt => ({
        ...opt,
        __key: opt.value + (breadcrumbLabels.value.join('>') || '')
      }))
    }
  })

  watch(open, (val: boolean) => {
    if (!val) {
      activeIndex.value = 0
      stack.value = []
      breadcrumbLabels.value = []
      search.value = ''
    }
  })
  watch(search, () => {
    activeIndex.value = 0
  })

  function highlightMatch(label: string, query: string): string {
    if (!query) return label
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
    // Use both light and dark mode classes
    return label.replace(
      regex,
      '<b class="bg-[var(--ast-global-color-5)] font-bold rounded px-1">$1</b>'
    )
  }

  return {
    open,
    activeIndex,
    stack,
    breadcrumbLabels,
    search,
    currentOptions,
    breadcrumb,
    selectedLabel,
    selectedValues,
    multiSelectLabel,
    isSelected,
    toggleValue,
    isMultiple,
    SEMUA_VALUE,
    toggle,
    close,
    handleOption,
    select,
    goBack,
    navigate,
    selectActive,
    navigateChildren,
    goToBreadcrumb,
    filteredOptions,
    highlightMatch,
  }
}