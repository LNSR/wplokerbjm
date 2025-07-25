// Component types

export interface ComponentConfig {
  selector: string
  component: any
}

export interface SortOption {
  value: 'desc' | 'asc'
  label: string
}

// Component props interfaces
export interface SearchFormProps {
  currentSearch?: string
  currentLokasi?: string[]
  currentGender?: string[]
  currentPendidikan?: string[]
  currentSort: SortOption
  archiveLink?: string
}

// Dropdown composable

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

import type { Ref, ComputedRef } from 'vue'

export interface UseDropdownReturn {
  open: Ref<boolean>
  activeIndex: Ref<number>
  search: Ref<string>
  breadcrumb: ComputedRef<string[]>
  selectedValues: ComputedRef<SelectedItem[]>
  multiSelectLabel: ComputedRef<string>
  isSelected: (value: string) => boolean
  toggleValue: (value: string) => void
  isMultiple: ComputedRef<boolean>
  SEMUA_VALUE: string
  toggle: () => void
  close: () => void
  select: (option: Option) => void
  goBack: () => void
  navigateChildren: (children: Option[], label: string, parentOption?: Option) => void
  goToBreadcrumb: (idx: number) => void
  filteredOptions: ComputedRef<Option[]>
  highlightMatch: (label: string, query: string) => string
}