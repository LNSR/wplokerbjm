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